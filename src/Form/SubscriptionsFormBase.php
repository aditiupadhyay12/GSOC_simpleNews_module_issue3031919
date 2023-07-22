<?php

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Entity form for Subscriber with common routines.
 */
abstract class SubscriptionsFormBase extends ContentEntityForm {

  /**
   * Allow delete button.
   *
   * @var bool
   */
  protected $allowDelete = FALSE;

  /**
   * Returns a message to display to the user upon successful form submission.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param bool $confirm
   *   Whether a confirmation mail is sent or not.
   *
   * @return string
   *   A HTML message.
   */
  abstract protected function getSubmitMessage(FormStateInterface $form_state, $confirm);

  /**
   * Returns the renderer for the 'subscriptions' field.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\simplenews\SubscriptionWidgetInterface
   *   The widget.
   */
  protected function getSubscriptionWidget(FormStateInterface $form_state) {
    return $this->getFormDisplay($form_state)->getRenderer('subscriptions');
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    if (!$this->allowDelete) {
      unset($actions['delete']);
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Subscriptions are handled later, in the submit callbacks through
    // ::getSelectedNewsletters(). Letting them be copied here would break
    // subscription management.
    $subsciptions_value = $form_state->getValue('subscriptions');
    $form_state->unsetValue('subscriptions');
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    $form_state->setValue('subscriptions', $subsciptions_value);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We first subscribe, then unsubscribe. This prevents deletion of
    // subscriptions when unsubscribed from the newsletter.
    $subscriber = $this->entity;

    foreach ($this->extractNewsletterIds($form_state, TRUE) as $newsletter_id) {
      if (!$subscriber->isSubscribed($newsletter_id)) {
        $subscriber->subscribe($newsletter_id, NULL, 'website');
      }
    }

    foreach ($this->extractNewsletterIds($form_state, FALSE) as $newsletter_id) {
      if ($subscriber->isSubscribed($newsletter_id)) {
        $subscriber->unsubscribe($newsletter_id, 'website');
      }
    }

    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage($this->getSubmitMessage($form_state, FALSE));
  }

  /**
   * Extracts selected/deselected newsletters IDs from the subscriptions widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param bool $selected
   *   Whether to extract selected (TRUE) or deselected (FALSE) newsletter IDs.
   *
   * @return string[]
   *   IDs of selected/deselected newsletters.
   */
  protected function extractNewsletterIds(FormStateInterface $form_state, $selected) {
    return $this->getSubscriptionWidget($form_state)
      ->extractNewsletterIds($form_state->getValue('subscriptions'), $selected);
  }

}
