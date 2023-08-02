<?php

namespace Drupal\simplenews\Subscription;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\SubscriberInterface;

/**
 * Default subscription manager.
 */
class SubscriptionManager implements SubscriptionManagerInterface {

  /**
   * Subscribed cache.
   *
   * @var array
   */
  protected $subscribedCache = [];

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The subscriber storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $subscriberStorage;

  /**
   * Constructs a SubscriptionManager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->languageManager = $language_manager;
    $this->config = $config_factory->get('simplenews.settings');
    $this->subscriberStorage = $entity_type_manager->getStorage('simplenews_subscriber');
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe($mail, $newsletter_id, $deprecated, $source = 'unknown', $preferred_langcode = NULL) {
    if ($deprecated !== FALSE) {
      throw new \LogicException('Third parameter must be FALSE');
    }

    // Get/create subscriber entity.
    $preferred_langcode = $preferred_langcode ?? $this->languageManager->getCurrentLanguage();
    $subscriber = Subscriber::loadByMail($mail, 'create', $preferred_langcode);

    if (!$subscriber->isSubscribed($newsletter_id)) {
      // Subscribe the user if not already subscribed.
      $subscriber->subscribe($newsletter_id, NULL, $source);
      $subscriber->save();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe($mail, $newsletter_id, $deprecated, $source = 'unknown') {
    if ($deprecated !== FALSE) {
      throw new \LogicException('Third parameter must be FALSE');
    }

    $subscriber = Subscriber::loadByMail($mail);
    if ($subscriber && $subscriber->isSubscribed($newsletter_id)) {
      // Unsubscribe the user from the mailing list.
      $subscriber->unsubscribe($newsletter_id, $source);
      $subscriber->save();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isSubscribed($mail, $newsletter_id) {
    if (!isset($this->subscribedCache[$mail][$newsletter_id])) {
      $subscriber = Subscriber::loadByMail($mail);
      // Check that a subscriber was found, it is active and subscribed to the
      // requested newsletter_id.
      $this->subscribedCache[$mail][$newsletter_id] = $subscriber && $subscriber->isActive() && $subscriber->isSubscribed($newsletter_id);
    }
    return $this->subscribedCache[$mail][$newsletter_id];
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->subscribedCache = [];
  }

  /**
   * {@inheritdoc}
   */
  public function tidy() {
    $days = $this->config->get('subscription.tidy_unconfirmed');
    if (!$days) {
      return;
    }

    // Query unconfirmed subscribers.
    $max_age = strtotime("-$days days");
    $unconfirmed = \Drupal::entityQuery('simplenews_subscriber')
      ->condition('status', SubscriberInterface::UNCONFIRMED)
      ->condition('subscriptions.timestamp', $max_age, '<')
      ->accessCheck(FALSE)
      ->execute();

    $this->subscriberStorage->delete($this->subscriberStorage->loadMultiple($unconfirmed));
  }

}
