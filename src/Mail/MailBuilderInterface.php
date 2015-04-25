<?php
/**
 * Created by PhpStorm.
 * User: berdir
 * Date: 4/25/15
 * Time: 1:47 PM
 */
namespace Drupal\simplenews\Mail;

use Drupal\simplenews\Source\SourceInterface;

/**
 * Builds newsletter and confirmation mails.
 */
interface MailBuilderInterface {

  /**
   * Build subject and body of the test and normal newsletter email.
   *
   * @param array $message
   *   Message array as used by hook_mail().
   * @param \Drupal\simplenews\Source\SourceInterface $source
   *   The Source instance.
   */
  function buildNewsletterMail(array &$message, SourceInterface $source);

  /**
   * Build subject and body of the subscribe confirmation email.
   *
   * @param array $message
   *   Message array as used by hook_mail().
   * @param array $params
   *   Parameter array as used by hook_mail().
   */
  function buildSubscribeMail(array &$message, array $params);

  /**
   * Build subject and body of the subscribe confirmation email.
   *
   * @param array $message
   *   Message array as used by hook_mail().
   * @param array $params
   *   Parameter array as used by hook_mail().
   */
  function buildCombinedMail(&$message, $params);

  /**
   * Build subject and body of the unsubscribe confirmation email.
   *
   * @param array $message
   *   Message array as used by hook_mail().
   * @param array $params
   *   Parameter array as used by hook_mail().
   */
  function buildUnsubscribeMail(&$message, $params);
}
