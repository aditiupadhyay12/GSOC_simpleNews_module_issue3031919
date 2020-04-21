<?php

namespace Drupal\simplenews\Subscription;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\Mail\MailerInterface;
use Drupal\simplenews\NewsletterInterface;
use Drupal\simplenews\SubscriberInterface;

/**
 * Default subscription manager.
 */
class SubscriptionManager implements SubscriptionManagerInterface, DestructableInterface {

  /**
   * Whether confirmations should be combined.
   *
   * @var bool
   */
  protected $combineConfirmations = FALSE;

  /**
   * Combined confirmations.
   *
   * @var array
   */
  protected $confirmations = [];

  /**
   * Subscribed cache.
   *
   * @var array
   */
  protected $subscribedCache = [];

  /**
   * The mailer.
   *
   * @var \Drupal\simplenews\Mail\MailerInterface
   */
  protected $mailer;

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
   * The token.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a SubscriptionManager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\simplenews\Mail\MailerInterface $mailer
   *   The simplenews manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The simplenews logger channel.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory, MailerInterface $mailer, Token $token, LoggerInterface $logger, AccountInterface $current_user) {
    $this->languageManager = $language_manager;
    $this->config = $config_factory->get('simplenews.settings');
    $this->mailer = $mailer;
    $this->token = $token;
    $this->logger = $logger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe($mail, $newsletter_id, $confirm = NULL, $source = 'unknown', $preferred_langcode = NULL) {
    // Get/create subscriber entity.
    $preferred_langcode = $preferred_langcode ?? $this->languageManager->getCurrentLanguage();
    $subscriber = Subscriber::loadByMail($mail, 'create', $preferred_langcode);
    $newsletter = Newsletter::load($newsletter_id);

    // If confirmation is not explicitly specified, use the newsletter
    // configuration.
    if ($confirm === NULL) {
      $confirm = $this->requiresConfirmation($newsletter, $subscriber->getUserId());
    }

    if ($confirm) {
      // Create an unconfirmed subscription object if it doesn't exist yet.
      if (!$subscriber->isSubscribed($newsletter_id)) {
        $subscriber->subscribe($newsletter_id, SIMPLENEWS_SUBSCRIPTION_STATUS_UNCONFIRMED, $source);
        $subscriber->save();
      }

      $this->addConfirmation('subscribe', $subscriber, $newsletter);
    }
    elseif (!$subscriber->isSubscribed($newsletter_id)) {
      // Subscribe the user if not already subscribed.
      $subscriber->subscribe($newsletter_id, SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $source);
      $subscriber->save();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe($mail, $newsletter_id, $confirm = NULL, $source = 'unknown') {
    $subscriber = Subscriber::loadByMail($mail);
    if (!$subscriber) {
      throw new \Exception('The subscriber does not exist.');
    }
    // The unlikely case that a user is unsubscribed from a non existing mailing
    // list is logged.
    if (!$newsletter = Newsletter::load($newsletter_id)) {
      $this->logger->error('Attempt to unsubscribe from non existing mailing list ID %id', ['%id' => $newsletter_id]);
      return $this;
    }

    // If confirmation is not explicitly specified, use the newsletter
    // configuration.
    if ($confirm === NULL) {
      $confirm = $this->requiresConfirmation($newsletter, $subscriber->getUserId());
    }

    if ($confirm) {
      $this->addConfirmation('unsubscribe', $subscriber, $newsletter);
    }
    elseif ($subscriber->isSubscribed($newsletter_id)) {
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
      $this->subscribedCache[$mail][$newsletter_id] = $subscriber && $subscriber->getStatus() && $subscriber->isSubscribed($newsletter_id);
    }
    return $this->subscribedCache[$mail][$newsletter_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getChangesList(SubscriberInterface $subscriber, array $changes = NULL, $langcode = NULL) {
    if (empty($langcode)) {
      $language = $this->languageManager->getCurrentLanguage();
      $langcode = $language->getId();
    }

    if (empty($changes)) {
      $changes = $subscriber->getChanges();
    }

    $changes_list = [];
    foreach ($changes as $newsletter_id => $action) {
      $subscribed = $subscriber->isSubscribed($newsletter_id);
      // Get text for each possible combination.
      if ($action == 'subscribe' && !$subscribed) {
        $line = $this->config->get('subscription.confirm_combined_line_subscribe_unsubscribed');
      }
      elseif ($action == 'subscribe' && $subscribed) {
        $line = $this->config->get('subscription.confirm_combined_line_subscribe_subscribed');
      }
      elseif ($action == 'unsubscribe' && !$subscribed) {
        $line = $this->config->get('subscription.confirm_combined_line_unsubscribe_unsubscribed');
      }
      elseif ($action == 'unsubscribe' && $subscribed) {
        $line = $this->config->get('subscription.confirm_combined_line_unsubscribe_subscribed');
      }
      $newsletter_context = [
        'simplenews_subscriber' => $subscriber,
        'newsletter' => Newsletter::load($newsletter_id),
      ];
      $changes_list[$newsletter_id] = simplenews_token_replace_body($line, $newsletter_context);
    }
    return $changes_list;
  }

  /**
   * {@inheritdoc}
   */
  public function sendConfirmations() {
    foreach ($this->confirmations as $mail => $changes) {
      $subscriber = Subscriber::loadByMail($mail, 'create', $this->languageManager->getCurrentLanguage());
      $subscriber->setChanges($changes);

      $this->mailer->sendCombinedConfirmation($subscriber);

      // Save changes in the subscriber if there is a real subscriber object.
      if ($subscriber->id()) {
        $subscriber->save();
      }
    }
    $sent = !empty($this->confirmations);
    $this->confirmations = [];
    return $sent;
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
  public function destruct() {
    // Ensure that confirmations are always sent even if API calls did not do it
    // explicitly. It is still possible to do so, e.g. to be able to know if
    // confirmations were sent or not.
    $this->sendConfirmations();
  }

  /**
   * Add a mail confirmation or fetch them.
   *
   * @param string $action
   *   The confirmation type, either subscribe or unsubscribe.
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber object.
   * @param \Drupal\simplenews\NewsletterInterface $newsletter
   *   The newsletter object.
   */
  protected function addConfirmation($action, SubscriberInterface $subscriber, NewsletterInterface $newsletter) {
    $this->confirmations[$subscriber->getMail()][$newsletter->id()] = $action;
  }

  /**
   * Checks whether confirmation is required for this newsletter and user.
   *
   * @param \Drupal\simplenews\NewsletterInterface $newsletter
   *   The newsletter entity.
   * @param int $uid
   *   The user ID that belongs to the email.
   *
   * @return bool
   *   TRUE if confirmation is required, FALSE if not.
   */
  protected function requiresConfirmation(NewsletterInterface $newsletter, $uid) {
    // If user is currently logged in, don't send confirmation.
    // Other addresses receive a confirmation if double opt-in is selected.
    if ($this->currentUser->id() && $uid && $this->currentUser->id() == $uid) {
      return FALSE;
    }
    else {
      return $newsletter->opt_inout == 'double';
    }
  }

}
