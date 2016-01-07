<?php
/**
 * @file
 * Contains \Drupal\simplenews\Subscription\SubscriptionManagerInterface.
 */

namespace Drupal\simplenews\Subscription;

/**
 * Subscription storage.
 */
interface SubscriptionStorageInterface {

  /**
   * Deletes subscriptions.
   *
   * @param array $conditions
   *   An associative array of conditions matching the records to be delete.
   *   Example: array('newsletter_id' => 5, 'snid' => 12)
   *   Delete the subscription of subscriber 12 to newsletter newsletter_id 5.
   *
   * @ingroup subscription
   */
  public function deleteSubscriptions($conditions = array());
}
