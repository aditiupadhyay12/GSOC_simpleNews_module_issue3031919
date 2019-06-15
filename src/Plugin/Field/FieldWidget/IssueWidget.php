<?php

namespace Drupal\simplenews\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\simplenews\recipientHandler\RecipientHandlerManager;
use Drupal\simplenews\Spool\SpoolStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'simplenews_issue' widget.
 *
 * @FieldWidget(
 *   id = "simplenews_issue",
 *   label = @Translation("Issue"),
 *   field_types = {
 *     "simplenews_issue",
 *   },
 *   multiple_values = TRUE
 * )
 */
class IssueWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

  /**
   * The spool storage.
   *
   * @var \Drupal\simplenews\Spool\SpoolStorageInterface
   */
  protected $spoolStorage;

  /**
   * The recipient handler plugin manager.
   *
   * @var \Drupal\simplenews\RecipientHandler\RecipientHandlerManager
   */
  protected $recipientHandlerManager;

  /**
   * Constructs an IssueWidget.
   *
   * @param \Drupal\simplenews\Spool\SpoolStorageInterface $spool_storage
   *   The spool storage.
   * @param \Drupal\simplenews\recipientHandler\recipientHandlerManager
   *   The recipient handler manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, SpoolStorageInterface $spool_storage, RecipientHandlerManager $recipient_handler_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->spoolStorage = $spool_storage;
    $this->recipientHandlerManager = $recipient_handler_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('simplenews.spool_storage'),
      $container->get('plugin.manager.simplenews_recipient_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['target_id'] = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['target_id']['#required'] = $this->required;

    $button = $form_state->getTriggeringElement();
    $values = $button ? $form_state->getValue($button['#array_parents'][0]) : NULL;
    $handler = $this->spoolStorage->getRecipientHandler($items->getEntity(), $values);
    $options = $this->recipientHandlerManager->getOptions();
    $element['handler'] = [
      '#type' => 'select',
      '#title' => t('Recipients'),
      '#description' => t('How recipients should be selected.'),
      '#options' => $options,
      '#default_value' => $handler->getPluginId(),
      '#access' => count($options) > 1,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxUpdateRecipientHandlerSettings'],
        'wrapper' => 'recipient-handler-settings',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    $element['handler_settings'] = $handler->settingsForm();
    $element['handler_settings']['#prefix'] = '<div id="recipient-handler-settings">';
    $element['handler_settings']['#suffix'] = '</div>';

    // Ensure that the extra properties are preserved.
    if (!$items->isEmpty()) {
      foreach ($items->first()->getValue() as $key => $value) {
        if (empty($element[$key])) {
          $element[$key] = [
            '#type' => 'value',
            '#value' => $value,
          ];
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    // OptionsWidgetBase uses '_none' as a special value.
    if ($element['#value'] == '_none') {
      if ($element['#required']) {
        $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
      }
      else {
        $form_state->setValueForElement($element, NULL);
      }
    }
  }

  /**
   * Return the updated recipient handler settings form.
   */
  public function ajaxUpdateRecipientHandlerSettings($form, FormStateInterface $form_state) {
    // Determine the field name from the triggering element.
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    return $element['handler_settings'];
  }

}
