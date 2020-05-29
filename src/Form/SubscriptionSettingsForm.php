<?php

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure simplenews subscription settings.
 */
class SubscriptionSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_admin_settings_subscription';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['simplenews.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('simplenews.settings');

    $form['subscription_tidy'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tidy unconfirmed subscriptions'),
      '#collapsible' => FALSE,
    ];

    $form['subscription_tidy']['simplenews_tidy_unconfirmed'] = [
      '#type' => 'number',
      '#title' => t('Days'),
      '#default_value' => empty($database['port']) ? '' : $database['port'],
      '#min' => 0,
      '#max' => 365,
      '#description' => $this->t('Tidy unconfirmed subscriptions after a number of days (recommended), or zero to skip.'),
      '#default_value' => $config->get('subscription.tidy_unconfirmed'),
    ];

    $form['subscription_mail'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Confirmation emails'),
      '#collapsible' => FALSE,
    ];

    $form['subscription_mail']['simplenews_use_combined'] = [
      '#type' => 'select',
      '#title' => $this->t('Use combined confirmation mails'),
      '#options' => [
        'multiple' => $this->t('For multiple changes'),
        'always' => $this->t('Always'),
        'never' => $this->t('Never'),
      ],
      '#description' => $this->t('Combined confirmation mails allow subscribers to confirm multiple newsletter changes with single mail.'),
      '#default_value' => $config->get('subscription.use_combined'),
    ];

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['subscription_mail']['token_help'] = [
        '#title' => $this->t('Replacement patterns'),
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];

      $form['subscription_mail']['token_help']['browser'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['simplenews-newsletter', 'simplenews-subscriber'],
      ];
    }

    $form['subscription_mail']['single'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Single confirmation mails'),
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
      '#states' => [
        'invisible' => [
          ':input[name="simplenews_use_combined"]' => [
            'value' => 'always',
          ],
        ],
      ],
    ];

    $form['subscription_mail']['single']['simplenews_confirm_subscribe_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#default_value' => $config->get('subscription.confirm_subscribe_subject'),
      '#maxlength' => 180,
    ];
    $form['subscription_mail']['single']['simplenews_confirm_subscribe_unsubscribed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body text of subscribe email'),
      '#default_value' => $config->get('subscription.confirm_subscribe_unsubscribed'),
      '#rows' => 5,
    ];
    $form['subscription_mail']['single']['simplenews_confirm_subscribe_subscribed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body text for already subscribed visitor'),
      '#default_value' => $config->get('subscription.confirm_subscribe_subscribed'),
      '#rows' => 5,
    ];
    $form['subscription_mail']['single']['simplenews_confirm_unsubscribe_subscribed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body text of unsubscribe email'),
      '#default_value' => $config->get('subscription.confirm_unsubscribe_subscribed'),
      '#rows' => 5,
    ];
    $form['subscription_mail']['single']['simplenews_confirm_unsubscribe_unsubscribed'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body text for not yet subscribed visitor'),
      '#default_value' => $config->get('subscription.confirm_unsubscribe_unsubscribed'),
      '#rows' => 5,
    ];

    $form['subscription_mail']['combined'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Combined confirmation mails'),
      '#collapsed' => TRUE,
      '#collapsible' => TRUE,
      '#states' => [
        'invisible' => [
          ':input[name="simplenews_use_combined"]' => [
            'value' => 'never',
          ],
        ],
      ],
    ];

    $form['subscription_mail']['combined']['simplenews_confirm_combined_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject text for combined confirmation mail'),
      '#default_value' => $config->get('subscription.confirm_combined_subject'),
    ];

    $form['subscription_mail']['combined']['simplenews_confirm_combined_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body text for combined confirmation mail'),
      '#default_value' => $config->get('subscription.confirm_combined_body'),
      '#rows' => 5,
    ];

    $form['subscription_mail']['combined']['simplenews_confirm_combined_body_unchanged'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body text for unchanged combined confirmation mail'),
      '#default_value' => $config->get('subscription.confirm_combined_body_unchanged'),
      '#rows' => 5,
      '#description' => $this->t('This body is used when there are no change requests which have no effect, e.g trying to subscribe when already being subscribed to a newsletter.'),
    ];

    $form['subscription_mail']['combined']['simplenews_confirm_combined_line_subscribe_unsubscribed'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Change text for a new subscription'),
      '#default_value' => $config->get('subscription.confirm_combined_line_subscribe_unsubscribed'),
    ];

    $form['subscription_mail']['combined']['simplenews_confirm_combined_line_subscribe_subscribed'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Change text when already subscribed'),
      '#default_value' => $config->get('subscription.confirm_combined_line_subscribe_subscribed'),
    ];

    $form['subscription_mail']['combined']['simplenews_confirm_combined_line_unsubscribe_subscribed'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Change text for an unsubscription'),
      '#default_value' => $config->get('subscription.confirm_combined_line_unsubscribe_subscribed'),
    ];

    $form['subscription_mail']['combined']['simplenews_confirm_combined_line_unsubscribe_unsubscribed'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Change text when already unsubscribed'),
      '#default_value' => $config->get('subscription.confirm_combined_line_unsubscribe_unsubscribed'),
    ];

    $form['confirm_pages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Confirmation pages'),
      '#collapsible' => FALSE,
    ];
    $form['confirm_pages']['simplenews_confirm_subscribe_page'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subscribe confirmation'),
      '#description' => $this->t('Drupal path or URL of the destination page where after the subscription is confirmed (e.g. /node/123). Leave empty to go to the front page.'),
      '#default_value' => $config->get('subscription.confirm_subscribe_page'),
    ];
    $form['confirm_pages']['simplenews_confirm_unsubscribe_page'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unsubscribe confirmation'),
      '#description' => $this->t('Drupal path or URL of the destination page when the subscription removal is confirmed (e.g. /node/123). Leave empty to go to the front page.'),
      '#default_value' => $config->get('subscription.confirm_unsubscribe_page'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('simplenews.settings')
      ->set('subscription.tidy_unconfirmed', $form_state->getValue('simplenews_tidy_unconfirmed'))
      ->set('subscription.use_combined', $form_state->getValue('simplenews_use_combined'))
      ->set('subscription.confirm_subscribe_subject', $form_state->getValue('simplenews_confirm_subscribe_subject'))
      ->set('subscription.confirm_subscribe_unsubscribed', $form_state->getValue('simplenews_confirm_subscribe_unsubscribed'))
      ->set('subscription.confirm_subscribe_subscribed', $form_state->getValue('simplenews_confirm_subscribe_subscribed'))
      ->set('subscription.confirm_unsubscribe_subscribed', $form_state->getValue('simplenews_confirm_unsubscribe_subscribed'))
      ->set('subscription.confirm_unsubscribe_unsubscribed', $form_state->getValue('simplenews_confirm_unsubscribe_unsubscribed'))
      ->set('subscription.confirm_combined_subject', $form_state->getValue('simplenews_confirm_combined_subject'))
      ->set('subscription.confirm_combined_body', $form_state->getValue('simplenews_confirm_combined_body'))
      ->set('subscription.confirm_combined_body_unchanged', $form_state->getValue('simplenews_confirm_combined_body_unchanged'))
      ->set('subscription.confirm_combined_line_subscribe_unsubscribed', $form_state->getValue('simplenews_confirm_combined_line_subscribe_unsubscribed'))
      ->set('subscription.confirm_combined_line_subscribe_subscribed', $form_state->getValue('simplenews_confirm_combined_line_subscribe_subscribed'))
      ->set('subscription.confirm_combined_line_unsubscribe_subscribed', $form_state->getValue('simplenews_confirm_combined_line_unsubscribe_subscribed'))
      ->set('subscription.confirm_combined_line_unsubscribe_unsubscribed', $form_state->getValue('simplenews_confirm_combined_line_unsubscribe_unsubscribed'))
      ->set('subscription.confirm_subscribe_page', $form_state->getValue('simplenews_confirm_subscribe_page'))
      ->set('subscription.confirm_unsubscribe_page', $form_state->getValue('simplenews_confirm_unsubscribe_page'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
