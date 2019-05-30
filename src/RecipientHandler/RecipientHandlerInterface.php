<?php

namespace Drupal\simplenews\RecipientHandler;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for Simplenews Recipient Handler Classes.
 */
interface RecipientHandlerInterface extends \Countable, PluginInspectionInterface {

  /**
   * Adds a newsletter issue to the mail spool.
   *
   * @return integer
   *   Number of recipients added.
   */
  function addToSpool();

}