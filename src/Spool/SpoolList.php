<?php

/**
 * @file
 * Contains \Drupal\simplenews\Spool\SpoolList.
 */

namespace Drupal\simplenews\Spool;

/**
 * List of mail spool entries.
 */
class SpoolList implements SpoolListInterface {

  /**
   * Array with mail spool rows being processed.
   *
   * @var array
   */
  protected $mails;

  /**
   * Array of the processed mail spool rows.
   */
  protected $processed = array();

  /**
   * Creates a spool list.
   *
   * @param array $mails
   *   List of mail spool rows.
   */
  public function __construct(array $mails) {
    $this->mails = $mails;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return count($this->mails);
  }

  /**
   * {@inheritdoc}
   */
  public function nextSource() {
    // Get the current mail spool row and update the internal pointer to the
    // next row.
    $return = each($this->mails);
    // If we're done, return false.
    if (!$return) {
      return FALSE;
    }
    $spool_data = $return['value'];

    // Store this spool row as processed.
    $this->processed[$spool_data->msid] = $spool_data;

    $entity = entity_load($spool_data->entity_type, $spool_data->entity_id);
    if (!$entity) {
      // If the entity load failed, set the processed status done and proceed with
      // the next mail.
      $this->processed[$spool_data->msid]->result = array(
        'status' => SpoolStorageInterface::STATUS_DONE,
        'error' => TRUE
      );
      return $this->nextSource();
    }

    if ($spool_data->data) {
      $subscriber = $spool_data->data;
    }
    else {
      $subscriber = simplenews_subscriber_load_by_mail($spool_data->mail);
    }

    if (!$subscriber) {
      // If loading the subscriber failed, set the processed status done and
      // proceed with the next mail.
      $this->processed[$spool_data->msid]->result = array(
        'status' => SpoolStorageInterface::STATUS_DONE,
        'error' => TRUE
      );
      return $this->nextSource();
    }

    $source_class = $this->getSourceImplementation($spool_data);

    /** @var \Drupal\simplenews\Source\SourceEntityInterface $source */
    $source = new $source_class($entity, $subscriber, $spool_data->entity_type);

    // Set the langcode langcode.
    $this->processed[$spool_data->msid]->langcode = $source->getEntity()->language()->getId();
    return $source;
  }

  /**
   * {@inheritdoc}
   */
  function getProcessed() {
    $processed = $this->processed;
    $this->processed = array();
    return $processed;
  }

  /**
   * Return the source implementation for the given mail spool row.
   *
   * @return string
   *   Source class name.
   */
  protected function getSourceImplementation($spool_data) {
    $config = \Drupal::config('simplenews.settings');

    // First check if there is a class set for this entity type (default
    // 'source_node' to SourceNode.
    $class = $config->get('mail.source_' . $spool_data->entity_type);

    // If no class was found, fall back to the generic 'source'
    // variable.
    if (empty($class)) {
      $class = $config->get('mail.source');
    }

    return $class;
  }
}
