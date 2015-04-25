<?php

/**
 * @file
 * Contains \Drupal\simplenews\Mail\MailBuilder.
 */

namespace Drupal\simplenews\Mail;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\simplenews\Source\SourceInterface;

/**
 * Default mail builder.
 */
class MailBuilder implements MailBuilderInterface {

  /**
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a MailBuilder.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Token $token, ConfigFactoryInterface $config_factory) {
    $this->token = $token;
    $this->config = $config_factory->get('simplenews.settings');
  }

  /**
   * {@inheritdoc}
   */
  function buildNewsletterMail(array &$message, SourceInterface $source) {
    // Get message data from source.
    $message['headers'] = $source->getHeaders($message['headers']);
    $message['subject'] = $source->getSubject();
    $message['body']['body'] = $source->getBody();
    $message['body']['footer'] = $source->getFooter();

    if ($source->getFormat() == 'html') {
      // Set the necessary headers to detect this as an HTML mail. Set both the
      // Content-Type header, and the format (Swiftmailer) and plain (Mime Mail)
      // params.
      $message['headers']['Content-Type'] = 'text/html; charset=UTF-8';
      $message['params']['format'] = 'text/html';
      $message['params']['plain'] = NULL;

      // Provide a plain text version, both in params][plaintext (Mime Mail) and
      // plain (Swiftmailer).
      $message['params']['plaintext'] = $source->getPlainBody() . "\n" . $source->getPlainFooter();
      $message['plain'] = $message['params']['plaintext'];

      // Add attachments, again, both for the attachments key (Mime Mail) and
      // files (Swiftmailer).
      $message['params']['attachments'] = $source->getAttachments();
      $message['params']['files'] = $message['params']['attachments'];
    }
    else {
      // This is a plain text email, explicitly mark it as such, the default
      // Content-Type header already defaults to that.
      $message['params']['plain'] = TRUE;
      $message['params']['format'] = 'text/plain';
    }

  }

  /**
   * {@inheritdoc}
   */
  function buildSubscribeMail(array &$message, array $params) {
    $context = $params['context'];

    // Use formatted from address "name" <mail_address>
    $message['headers']['From'] = $params['from']['formatted'];

    $message['subject'] = $this->config->get('subscription.confirm_subscribe_subject');
    $message['subject'] = $this->token->replace($message['subject'], $context, array('sanitize' => FALSE));
    if ($context['simplenews_subscriber']->isSubscribed($context['newsletter']->id())) {
      $body = $this->config->get('subscription.confirm_subscribe_subscribed');
    }
    else {
      $body = $this->config->get('subscription.confirm_subscribe_unsubscribed');
    }
    $message['body'][] = $this->token->replace($body, $context, array('sanitize' => FALSE));
  }

  /**
   * {@inheritdoc}
   */
  function buildCombinedMail(&$message, $params) {
    $context = $params['context'];
    $subscriber = $context['simplenews_subscriber'];
    $langcode = $message['langcode'];

    // Use formatted from address "name" <mail_address>
    $message['headers']['From'] = $params['from']['formatted'];

    $message['subject'] = $this->config->get('subscription.confirm_combined_subject');
    $message['subject'] = $this->token->replace($message['subject'], $context, array('sanitize' => FALSE));

    $changes_list = '';
    $actual_changes = 0;
    foreach (simplenews_confirmation_get_changes_list($context['simplenews_subscriber'], $subscriber->getChanges(), $langcode) as $newsletter_id => $change) {
      $changes_list .= ' - ' . $change . "\n";

      // Count the actual changes.
      $subscribed = $context['simplenews_subscriber']->isSubscribed($newsletter_id);
      $changes = $subscriber->getChanges();
      if ($changes[$newsletter_id] == 'subscribe' && !$subscribed || $changes[$newsletter_id] == 'unsubscribe' && $subscribed) {
        $actual_changes++;
      }
    }

    // If there are actual changes, use the combined_body key otherwise use the
    // one without a confirmation link.
    $body_key = $actual_changes ? 'combined_body' : 'combined_body_unchanged';

    $body = $this->config->get('subscription.confirm_' .$body_key);
    // The changes list is not an actual token.
    $body = str_replace('[changes-list]', $changes_list, $body);
    $message['body'][] = $this->token->replace($body, $context, array('sanitize' => FALSE));
  }

  /**
   * {@inheritdoc}
   */
  function buildUnsubscribeMail(&$message, $params) {
    $context = $params['context'];

    // Use formatted from address "name" <mail_address>
    $message['headers']['From'] = $params['from']['formatted'];

    $message['subject'] = $this->config->get('subscription.confirm_subscribe_subject');
    $message['subject'] = $this->token->replace($message['subject'], $context, array('sanitize' => FALSE));

    if ($context['simplenews_subscriber']->isSubscribed($context['newsletter']->id())) {
      $body = $this->config->get('subscription.confirm_unsubscribe_subscribed');
      $message['body'][] = $this->token->replace($body, $context, array('sanitize' => FALSE));
    }
    else {
      $body = $this->config->get('subscription.confirm_unsubscribe_unsubscribed');
      $message['body'][] = $this->token->replace($body, $context, array('sanitize' => FALSE));
    }
  }

}
