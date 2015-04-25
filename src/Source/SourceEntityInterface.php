<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceEntityInterface.
 */

namespace Drupal\simplenews\Source;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\simplenews\SubscriberInterface;

/**
 * Source interface based on an entity.
 */
interface SourceEntityInterface extends SourceInterface {

  /**
   * Create a source based on an entity.
   */
  function __construct(ContentEntityInterface $entity, SubscriberInterface $subscriber);

  /**
   * Returns the actually used entity of this source.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   */
  function getEntity();

  /**
   * Returns the subscriber object.
   *
   * @return \Drupal\simplenews\SubscriberInterface
   */
  function getSubscriber();
}
