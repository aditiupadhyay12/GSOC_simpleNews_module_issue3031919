<?php

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure simplenews subscriptions of the logged user.
 */
class SubscriptionsBlockForm extends SubscriptionsFormBase {

  /**
   * Form unique ID.
   *
   * @var string
   */
  protected $uniqueId;

  /**
   * A message to use as description for the block.
   *
   * @var string
   */
  public $message;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    if (empty($this->uniqueId)) {
      throw new \Exception('Unique ID must be set with setUniqueId.');
    }
    return 'simplenews_subscriptions_block_' . $this->uniqueId;
  }

  /**
   * Setup unique ID.
   *
   * @param string $id
   *   Subscription block unique form ID.
   */
  public function setUniqueId($id) {
    $this->uniqueId = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Hide subscription widget if only one newsletter available.
    if (count($this->getNewsletters()) == 1) {
      $this->getSubscriptionWidget($form_state)->setHidden();
    }

    $form = parent::form($form, $form_state);

    if ($this->message) {
      $form['message'] = [
        '#type' => 'item',
        '#markup' => $this->message,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions[static::SUBMIT_UPDATE]['#value'] = $this->t('Update');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitMessage(FormStateInterface $form_state, $op, $confirm) {
    switch ($op) {
      case static::SUBMIT_UPDATE:
        return $this->t('The newsletter subscriptions for %mail have been updated.', ['%mail' => $form_state->getValue('mail')[0]['value']]);

      case static::SUBMIT_SUBSCRIBE:
        if ($confirm) {
          return $this->t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.');
        }
        return $this->t('You have been subscribed.');

      case static::SUBMIT_UNSUBSCRIBE:
        if ($confirm) {
          return $this->t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.');
        }
        return $this->t('You have been unsubscribed.');
    }
  }

}
