<?php

namespace Drupal\simplenews\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\simplenews\SubscriberInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the simplenews subscriber entity.
 *
 * @ContentEntityType(
 *   id = "simplenews_subscriber",
 *   label = @Translation("Simplenews subscriber"),
 *   handlers = {
 *     "storage" = "Drupal\simplenews\Subscription\SubscriptionStorage",
 *     "storage_schema" = "Drupal\simplenews\Subscription\SubscriptionStorageSchema",
 *     "access" = "Drupal\simplenews\SubscriberAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\simplenews\Form\SubscriberForm",
 *       "default" = "Drupal\simplenews\Form\SubscriberForm",
 *       "account" = "Drupal\simplenews\Form\SubscriptionsAccountForm",
 *       "block" = "Drupal\simplenews\Form\SubscriptionsBlockForm",
 *       "page" = "Drupal\simplenews\Form\SubscriptionsPageForm",
 *       "delete" = "Drupal\simplenews\Form\SubscriberDeleteForm",
 *     },
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\simplenews\SubscriberViewsData"
 *   },
 *   base_table = "simplenews_subscriber",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "mail"
 *   },
 *   field_ui_base_route = "simplenews.settings_subscriber",
 *   admin_permission = "administer simplenews subscriptions",
 *   links = {
 *     "edit-form" = "/admin/people/simplenews/edit/{simplenews_subscriber}",
 *     "delete-form" = "/admin/people/simplenews/delete/{simplenews_subscriber}",
 *   },
 *   token_type = "simplenews-subscriber"
 * )
 */
class Subscriber extends ContentEntityBase implements SubscriberInterface {

  /**
   * Subscriber created during user registration.
   *
   * Written in simplenews_user_profile_form_submit() and read in
   * simplenews_user_insert(). Unfortunately we have to use a static variable
   * because there is way to link the user and subscriber: the user doesn't yet
   * have an id, nor any field to link to a subscriber.
   *
   * @var \Drupal\simplenews\Entity\Subscriber;
   */
  public static $userRegSubscriber;

  /**
   * Whether currently copying field values to corresponding User.
   *
   * @var bool
   */
  protected static $syncing;

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage($message) {
    $this->set('message', $message);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return $this->getStatus() == self::ACTIVE;
  }

  /**
   * {@inheritdoc}
   */
  public function isConfirmed() {
    return $this->getStatus() != self::UNCONFIRMED;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(int $status) {
    if (!in_array($status, [self::INACTIVE, self::ACTIVE, self::UNCONFIRMED])) {
      throw new \LogicException('Status must be INACTIVE, ACTIVE, or UNCONFIRMED');
    }

    if ($status == self::ACTIVE && !$this->isConfirmed() && $existing = static::loadByMail($this->getMail())) {
      // Combine with existing confirmed subscription.
      foreach ($this->getSubscribedNewsletterIds() as $newsletter_id) {
        $existing->subscribe($newsletter_id);
      }
      foreach ($this->getFieldDefinitions() as $field_definition) {
        if (!$field_definition->getFieldStorageDefinition()->isBaseField()) {
          $field_name = $field_definition->getName();
          $item = $this->get($field_name);
          if (!$item->isEmpty()) {
            $existing->set($field_name, $item->getValue());
          }
        }
      }
      $existing->save();
      $this->delete();
      return $existing;
    }

    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMail() {
    return $this->get('mail')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMail($mail) {
    $this->set('mail', $mail);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    $uid = $this->getUserId();
    if ($uid && ($user = User::load($uid))) {
      return $user;
    }
    elseif ($mail = $this->getMail()) {
      return user_load_by_mail($mail) ?: NULL;
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    return $this->get('langcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLangcode($langcode) {
    $this->set('langcode', $langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function fillFromAccount(AccountInterface $account) {
    if (static::$syncing) {
      return $this;
    }

    static::$syncing = TRUE;
    $this->set('uid', $account->id());
    $this->setMail($account->getEmail());
    $this->setLangcode($account->getPreferredLangcode());
    if ($this->isConfirmed()) {
      $this->setStatus($account->isActive() ? self::ACTIVE : self::INACTIVE);
    }

    // Copy values for shared fields to existing subscriber.
    foreach ($this->getUserSharedFields($account) as $field_name) {
      $this->set($field_name, $account->get($field_name)->getValue());
    }

    static::$syncing = FALSE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function copyToAccount(AccountInterface $account) {
    // Copy values for shared fields to existing user.
    if (!static::$syncing && ($fields = $this->getUserSharedFields($account))) {
      static::$syncing = TRUE;
      foreach ($fields as $field_name) {
        $account->set($field_name, $this->get($field_name)->getValue());
      }
      if (!$account->isNew()) {
        $account->save();
      }
      static::$syncing = FALSE;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isSubscribed($newsletter_id) {
    foreach ($this->get('subscriptions') as $item) {
      if ($item->target_id == $newsletter_id) {
        return $item->status == SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isUnsubscribed($newsletter_id) {
    foreach ($this->get('subscriptions') as $item) {
      if ($item->target_id == $newsletter_id) {
        return $item->status == SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscription($newsletter_id) {
    foreach ($this->get('subscriptions') as $item) {
      if ($item->target_id == $newsletter_id) {
        return $item;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribedNewsletterIds() {
    $ids = [];
    foreach ($this->get('subscriptions') as $item) {
      if ($item->status == SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED) {
        $ids[] = $item->target_id;
      }
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe($newsletter_id, $deprecated = NULL, $source = 'unknown', $timestamp = REQUEST_TIME) {
    if (!is_null($deprecated) && $deprecated != SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED) {
      throw new \LogicException('Second parameter must be NULL or SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED');
    }

    if ($subscription = $this->getSubscription($newsletter_id)) {
      $subscription->status = SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED;
    }
    else {
      $data = [
        'target_id' => $newsletter_id,
        'status' => SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED,
        'source' => $source,
        'timestamp' => $timestamp,
      ];
      $this->subscriptions->appendItem($data);
    }

    \Drupal::moduleHandler()->invokeAll('simplenews_subscribe', [$this, $newsletter_id]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe($newsletter_id, $source = 'unknown', $timestamp = REQUEST_TIME) {
    if ($subscription = $this->getSubscription($newsletter_id)) {
      $subscription->status = SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED;
    }
    else {
      $data = [
        'target_id' => $newsletter_id,
        'status' => SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED,
        'source' => $source,
        'timestamp' => $timestamp,
      ];
      $this->subscriptions->appendItem($data);
    }
    // Clear any existing mail spool rows for this subscriber.
    \Drupal::service('simplenews.spool_storage')->deleteMails(['snid' => $this->id(), 'newsletter_id' => $newsletter_id]);

    \Drupal::moduleHandler()->invokeAll('simplenews_unsubscribe', [$this, $newsletter_id]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Copy values for shared fields to existing user.
    if ($this->isConfirmed() && $user = $this->getUser()) {
      $this->copyToAccount($user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage);

    // Fill from a User account with matching uid or email.
    if ($user = $this->getUser()) {
      $this->fillFromAccount($user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // If there is not already a linked user, fill from an account with
    // matching uid or email.
    if (!$this->isNew() && !$this->getUserId() && $user = $this->getUser()) {
      $this->fillFromAccount($user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendConfirmation() {
    $send = !$this->isConfirmed() && !static::skipConfirmation();
    if ($send) {
      \Drupal::service('simplenews.mailer')->sendSubscribeConfirmation($this);
    }
    return $send;
  }

  /**
   * Identifies configurable fields shared with a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to match fields against.
   *
   * @return string[]
   *   An indexed array of the names of each field for which there is also a
   *   field on the given user with the same name and type.
   */
  protected function getUserSharedFields(UserInterface $user) {
    $field_names = [];

    if (\Drupal::config('simplenews.settings')->get('subscriber.sync_fields')) {
      // Find any fields sharing name and type.
      foreach ($this->getFieldDefinitions() as $field_definition) {
        /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
        $field_name = $field_definition->getName();
        $user_field = $user->getFieldDefinition($field_name);
        if ($field_definition->getTargetBundle() && isset($user_field) && $user_field->getType() == $field_definition->getType()) {
          $field_names[] = $field_name;
        }
      }
    }

    return $field_names;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // Fields id, uuid, langcode are set by the parent.
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['status'] = BaseFieldDefinition::create('list_tiny_integer')
      ->setLabel(t('Status'))
      ->setDescription(t('Status of the subscriber.'))
      ->setDefaultValue(SubscriberInterface::ACTIVE)
      ->setRequired(TRUE)
      ->setSetting('allowed_values', simplenews_subscriber_status_options())
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t("The subscriber's email address."))
      ->setSetting('default_value', '')
      ->setRequired(TRUE)
      ->addConstraint('SubscriberUniqueField', [])
      ->setDisplayOptions('form', [
        'type' => 'email_default',
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The corresponding user.'))
      ->addConstraint('UniqueField', [])
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setDescription(t("The subscriber's preferred language."))
      ->setDisplayOptions('form', [
        'type' => 'language_select',
        'weight' => 2,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the subscriber was created.'))
      ->setDisplayConfigurable('form', TRUE);

    $fields['subscriptions'] = BaseFieldDefinition::create('simplenews_subscription')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setLabel(t('Subscriptions'))
      ->setDescription(t('Check the newsletters you want to subscribe to. Uncheck the ones you want to unsubscribe from.'))
      ->setSetting('target_type', 'simplenews_newsletter')
      ->setDisplayOptions('form', [
        'type' => 'simplenews_subscription_select',
        'weight' => '0',
        'settings' => [],
        'third_party_settings' => [],
      ]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByMail($mail, $create = FALSE, $default_langcode = NULL, $check_trust = FALSE) {
    $subscriber = FALSE;

    // Trusted if currently logged in, or if confirmations are disabled.
    $trusted = !$check_trust || static::skipConfirmation();

    if ($mail && $trusted) {
      $storage = \Drupal::entityTypeManager()->getStorage('simplenews_subscriber');
      $query = $storage->getQuery()
        ->condition('mail', $mail)
        ->accessCheck(FALSE)
        ->condition('status', self::UNCONFIRMED, '<>');
      $subscribers = $storage->loadMultiple($query->execute());
      $subscriber = reset($subscribers);
    }

    if ($create && !$subscriber) {
      $subscriber = static::create(['mail' => $mail]);
      if ($default_langcode) {
        $subscriber->setLangcode($default_langcode);
      }
      if (!$trusted) {
        $subscriber->setStatus(self::UNCONFIRMED);
      }
    }
    return $subscriber;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByUid($uid, $create = FALSE, $confirmed = TRUE) {
    $subscriber = FALSE;
    if ($uid) {
      $storage = \Drupal::entityTypeManager()->getStorage('simplenews_subscriber');
      $query = $storage->getQuery()->condition('uid', $uid)->accessCheck(FALSE);

      if ($confirmed) {
        $query->condition('status', self::UNCONFIRMED, '<>');
      }
      $subscribers = $storage->loadMultiple($query->execute());
      $subscriber = reset($subscribers);
    }

    if ($create && !$subscriber) {
      $subscriber = static::create(['uid' => $uid]);
    }
    return $subscriber;
  }

  /**
   * Checks if subscriber confirmation should be skipped.
   *
   * @return bool
   *   TRUE if confirmation should be skipped.
   */
  public static function skipConfirmation() {
    // Skip if logged in or if configured to skip.
    return \Drupal::currentUser()->id() || \Drupal::config('simplenews.settings')->get('subscription.skip_verification');
  }

}
