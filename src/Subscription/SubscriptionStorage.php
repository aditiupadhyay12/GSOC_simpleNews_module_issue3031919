<?php
/**
 * @file
 * Contains \Drupal\simplenews\Subscription\SubscriptionStorage.
 */

namespace Drupal\simplenews\Subscription;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Default subscription storage.
 */
class SubscriptionStorage extends SqlContentEntityStorage implements SubscriptionStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function deleteSubscriptions($conditions = array()) {
    $table_name = 'simplenews_subscriber__subscriptions';
    if (!db_table_exists($table_name)) {
      // This can happen if this is called during uninstall.
      return;
    }
    $query = $this->database->delete($table_name);
    foreach ($conditions as $key => $condition) {
      $query->condition($key, $condition);
    }
    $query->execute();
    $this->resetCache();
  }
}
