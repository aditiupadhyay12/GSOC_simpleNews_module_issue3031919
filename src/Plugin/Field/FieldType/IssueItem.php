<?php

/**
 * @file
 * Contains \Drupal\simplenews\Plugin\Field\FieldType\IssueItem.
 */

namespace Drupal\simplenews\Plugin\Field\FieldType;

use Drupal\Core\TypedData\MapDataDefinition;
use \Drupal\entity_reference\ConfigurableEntityReferenceItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'issue' entity field type (extended entity_reference).
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - target_type: The entity type to reference. Required.
 * - target_bundle: (optional): If set, restricts the entity bundles which may
 *   may be referenced. May be set to an single bundle, or to an array of
 *   allowed bundles.
 * - handler: The issue handler.
 * - handler_settings: The issue handler settings.
 * - status: A flag indicating whether the issue is published (3), ready (2), pending (1) or
 *   not (0)
 * - sent_count: Counter of already sent newsletters.
 *
 * @FieldType(
 *   id = "simplenews_issue",
 *   label = @Translation("Simplenews issue"),
 *   description = @Translation("An entity field containing an extended entityreference."),
 *   no_ui = TRUE,
 *   default_widget = "options_select",
 *   constraints = {"ValidReference" = {}}
 * )
 */
class IssueItem extends ConfigurableEntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Call the parent to define the target_id and entity properties.
    $properties = parent::propertyDefinitions($field_definition);

    $properties['handler'] = DataDefinition::create('string')
      ->setLabel(t('Handler'));

    $properties['handler_settings'] = MapDataDefinition::create()
      ->setLabel(t('Handler settings'));

    $properties['status'] = DataDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setSetting('unsigned', TRUE);

    $properties['sent_count'] = DataDefinition::create('integer')
      ->setLabel(t('Sent count'))
      ->setSetting('unsigned', TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['handler'] = array(
      'description' => 'The issue handler.',
      'type' => 'varchar',
      'length' => 255,
      'not null' => FALSE,
    );
    $schema['columns']['handler_settings'] = array(
      'description' => 'The issue handler settings.',
      'type' => 'blob',
      'size' => 'big',
      'not null' => FALSE,
      'serialize' => TRUE,
    );
    $schema['columns']['status'] = array(
      'description' => 'A flag indicating whether the issue is published (3), ready (2), pending (1) or not (0).',
      'type' => 'int',
      'size' => 'tiny',
      'not null' => FALSE,
    );
    $schema['columns']['sent_count'] = array(
      'description' => 'Counter of already sent newsletters.',
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => FALSE,
    );
    return $schema;
  }
}