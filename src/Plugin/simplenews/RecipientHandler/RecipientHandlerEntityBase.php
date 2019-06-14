<?php

namespace Drupal\simplenews\Plugin\simplenews\RecipientHandler;

/**
 * Base for Recipient Handler classes based on EntityQuery.
 */
abstract class RecipientHandlerEntityBase extends RecipientHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function addToSpool() {
    $ids = $this->buildEntityQuery()->execute();
    $this->addArrayToSpool('snid', $ids);
    return count($ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount() {
    return $this->buildEntityQuery()->count()->execute();
  }

  /**
   * Build the query that gets the list of subscribers.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Entity query on 'simplenews_subscriber'.
   */
  abstract protected function buildEntityQuery();

}
