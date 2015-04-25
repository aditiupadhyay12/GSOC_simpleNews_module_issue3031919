<?php
/**
 * @file
 * Contains \Drupal\simplenews\Mail\MailerInterface.
 */

namespace Drupal\simplenews\Mail;

use Drupal\simplenews\NewsletterInterface;
use Drupal\simplenews\Source\SourceInterface;
use Drupal\node\NodeInterface;
use Drupal\simplenews\Spool\SpoolStorageInterface;
use Drupal\simplenews\SubscriberInterface;

/**
 * Sends newsletter and subscription mails.
 */
interface MailerInterface {

  /**
   * Send mail spool immediatly if cron should not be used.
   *
   * @param array $conditions
   *   (Optional) Array of spool conditions which are applied to the query.
   * @param bool $use_batch
   *   (optional) Whether the batch API should be used or not.
   *
   * @return bool
   *   TRUE if the mails were sent or a batch was prepared, FALSE if not.
   */
  public function attemptImmediateSend(array $conditions = array(), $use_batch = TRUE);

  /**
   * Send simplenews newsletters from the spool.
   *
   * Individual newsletter emails are stored in database spool.
   * Sending is triggered by cron or immediately when the node is saved.
   * Mail data is retrieved from the spool, rendered and send one by one
   * If sending is successful the message is marked as send in the spool.
   *
   * @todo: Redesign API to allow language counter in multilingual sends.
   *
   * @param int $limit
   *   (Optional) The maximum number of mails to send. Defaults to
   *   unlimited.
   * @param array $conditions
   *   (Optional) Array of spool conditions which are applied to the query.
   *
   * @return int
   *   Returns the amount of sent mails.
   */
  public function sendSpool($limit = SpoolStorageInterface::UNLIMITED, array $conditions = array());

  /**
   * Send a node to an email address.
   *
   * @param \Drupal\simplenews\Source\SourceInterface $source
   *   The source object.
   *
   * @return bool
   *   TRUE if the email was successfully delivered; otherwise FALSE.
   */
  public function sendSource(SourceInterface $source);

  /**
   * Send test version of newsletter.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The newsletter node to be sent.
   * @param array $test_addresses
   *   List of addresses to send the newsletter to.
   */
  public function sendTest(NodeInterface $node, array $test_addresses);

  /**
   * Send collected confirmations.
   *
   * Depending on the settings, always sends a combined confirmation,
   * only when there are multiple changes for a subscriber or never.
   *
   * Calling this functions also resets the combine flag so that later
   * confirmations are sent separately. simplenews_combine_confirmations() needs
   * to be called again to re-enable combining.
   *
   * @return bool
   *   TRUE if any confirmation mails have been sent.
   *
   * @todo This function currently does not return information about which
   *       subscriber received a confirmation.
   */
  function sendCombinedConfirmation();

  /**
   * Send a confirmation mail.
   *
   * Either sends a mail immediatly or collects them for a combined mail.
   *
   * @param string $action
   *   The confirmation type, either subscribe or unsubscribe.
   * @param \Drupal\simplenews\SubscriberInterface $subscriber
   *   The subscriber object.
   * @param \Drupal\simplenews\NewsletterInterface $newsletter
   *   The newsletter object.
   */
  function sendConfirmation($action, SubscriberInterface $subscriber, NewsletterInterface $newsletter);

  /**
   * Update newsletter sent status.
   *
   * Set newsletter sent status based on email sent status in spool table.
   * Translated and untranslated nodes get a different treatment.
   *
   * The spool table holds data for emails to be sent and (optionally)
   * already send emails. The simplenews_newsletter table contains the overall
   * sent status of each newsletter issue (node).
   * Newsletter issues get the status pending when sending is initiated. As
   * long as unsend emails exist in the spool, the status of the newsletter
   * remains unsend. When no pending emails are found the newsletter status is
   * set 'send'.
   *
   * Translated newsletters are a group of nodes that share the same tnid
   * ({node}.tnid). Only one node of the group is found in the spool, but all
   * nodes should share the same state. Therefore they are checked for the
   * combined number of emails in the spool.
   */
  public function updateSendStatus();

  /**
   * Build formatted from-name and email for a mail object.
   *
   * @return array
   *   Associative array with (un)formatted from address
   *    'address'   => From address
   *    'formatted' => Formatted, mime encoded, from name and address
   */
  public function getFrom();
}
