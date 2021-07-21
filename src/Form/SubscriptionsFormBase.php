<?php

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplenews\Entity\Newsletter;

/**
 * Entity form for Subscriber with common routines.
 */
abstract class SubscriptionsFormBase extends ContentEntityForm {

  /**
   * The newsletters available to select from.
   *
   * @var \Drupal\simplenews\Entity\Newsletter[]
   */
  protected $newsletters;

  /**
   * Allow delete button.
   *
   * @var bool
   */
  protected $allowDelete = FALSE;

  /**
   * Set the newsletters available to select from.
   *
   * Unless called otherwise, all newsletters will be available.
   *
   * @param string[] $newsletters
   *   An array of Newsletter IDs.
   */
  public function setNewsletterIds(array $newsletters) {
    $this->newsletters = Newsletter::loadMultiple($newsletters);
  }

  /**
   * Returns the newsletters available to select from.
   *
   * @return \Drupal\simplenews\Entity\Newsletter[]
   *   The newsletters available to select from, indexed by ID.
   */
  public function getNewsletters() {
    if (!isset($this->newsletters)) {
      $this->setNewsletterIds(array_keys(simplenews_newsletter_get_visible()));
    }
    return $this->newsletters;
  }

  /**
   * Returns the newsletters available to select from.
   *
   * @return string[]
   *   The newsletter IDs available to select from, as an indexed array.
   */
  public function getNewsletterIds() {
    return array_keys($this->getNewsletters());
  }

  /**
   * Convenience method for the case of only one available newsletter.
   *
   * @see ::setNewsletterIds()
   *
   * @return string|null
   *   If there is exactly one newsletter available in this form, this method
   *   returns its ID. Otherwise it returns NULL.
   */
  protected function getOnlyNewsletterId() {
    $newsletters = $this->getNewsletterIds();
    if (count($newsletters) == 1) {
      return array_shift($newsletters);
    }
    return NULL;
  }

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
  public function form(array $form, FormStateInterface $form_state) {
    $this->getSubscriptionWidget($form_state)
      ->setAvailableNewsletterIds(array_keys($this->getNewsletters()));

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    $actions['submit']['#submit'][] = '::submitExtra';

    if (!$this->allowDelete) {
      unset($actions['delete']);
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $mail = $form_state->getValue(['mail', 0, 'value']);
    // Users should login to manage their subscriptions.
    if (!$this->isAuthenticated() && $user = user_load_by_mail($mail)) {
      $message = $user->isBlocked() ?
        $this->t('The email address %mail belongs to a blocked user.', ['%mail' => $mail]) :
        $this->t('There is an account registered for the e-mail address %mail. Please log in to manage your newsletter subscriptions.', ['%mail' => $mail]);
      $form_state->setErrorByName('mail', $message);
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Check if there is an authenticated user who is viewing this form.
   */
  protected function isAuthenticated() {
    $user_loaded = $this->getEntity()->getUser();
    return ($user_loaded && $user_loaded->isAuthenticated());
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
   * Submit callback that (un)subscribes to newsletters based on selection.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitExtra(array $form, FormStateInterface $form_state) {
    // We first subscribe, then unsubscribe. This prevents deletion of
    // subscriptions when unsubscribed from the newsletter.
    /** @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface $subscription_manager */
    $subscription_manager = \Drupal::service('simplenews.subscription_manager');
    foreach ($this->extractNewsletterIds($form_state, TRUE) as $newsletter_id) {
      $subscription_manager->subscribe($this->entity->getMail(), $newsletter_id, FALSE, 'website');
    }

    if (!$this->entity->isNew()) {
      foreach ($this->extractNewsletterIds($form_state, FALSE) as $newsletter_id) {
        $subscription_manager->unsubscribe($this->entity->getMail(), $newsletter_id, FALSE, 'website');
      }
    }
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
