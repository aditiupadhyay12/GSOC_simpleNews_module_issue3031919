<?php

namespace Drupal\simplenews\Plugin\simplenews\RecipientHandler;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\simplenews\RecipientHandler\RecipientHandlerInterface;
use Drupal\simplenews\Spool\SpoolStorageInterface;


/**
 * Base class for all Recipient Handler classes.
 */
abstract class RecipientHandlerBase extends PluginBase implements RecipientHandlerInterface {

  /**
   * The configuration.
   */
  protected $configuration;

  /**
   * The newsletter issue.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $issue;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The newsletter IDs.
   */
  protected $newsletterIds;

  /**
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration = $configuration;
    $this->issue = $configuration['_issue'];
    $this->connection = $configuration['_connection'];
    $this->newsletterIds = $configuration['_newsletter_ids'];
  }

  /**
   * Returns the newsletter ID.
   *
   * @return int
   *   Newsletter ID.
   *
   * @throws Exception if the configuration doesn't specify a single newsletter
   * ID.
   */
  protected function getNewsletterId() {
    if (count($this->newsletterIds) != 1) {
      throw new Exception("Recipient handler requires a single newsletter ID.");
    }
    return $this->newsletterIds[0];
  }

  /**
   * Adds an array of entries to the spool.
   *
   * The caller specifies the values for a field to define the recipient.  The
   * other fields are automatically defaulted based on the issue and
   * newsletter.
   *
   * @param string $field
   *   Field to set: snid or data
   * @param array $values
   *   Values to set for field.
   */
  protected function addArrayToSpool($field, $values) {
    if (empty($values)) {
      return 0;
    }

    $template = [
      'entity_type' => $this->issue->getEntityTypeId(),
      'entity_id' => $this->issue->id(),
      'status' => SpoolStorageInterface::STATUS_PENDING,
      'timestamp' => REQUEST_TIME,
      'newsletter_id' => $this->getNewsletterId(),
    ];

    $insert = $this->connection->insert('simplenews_mail_spool')
      ->fields(array_merge(array_keys($template), [$field]));

    foreach ($values as $value) {
      $row = $template;
      $row[$field] = $value;
      $insert->values($row);
    }

    $insert->execute();
  }

}
