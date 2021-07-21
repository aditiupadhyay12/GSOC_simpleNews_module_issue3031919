<?php

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\simplenews\Entity\Subscriber;

/**
 * Add subscriptions for authenticated user or new subscriber.
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
    $form['subscriptions']['widget']['#title'] = $this->t('Manage your newsletter subscriptions');
    $form['subscriptions']['widget']['#description'] = $this->t('Select the newsletter(s) to which you want to subscribe.');

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
    $actions['submit']['#value'] = $this->t('Subscribe');

    $user = \Drupal::currentUser();
    $link = $user->isAuthenticated() ? Url::fromRoute('simplenews.newsletter_subscriptions_user', ['user' => $user->id()]) : Url::fromRoute('simplenews.newsletter_validate');
    $actions['manage'] = [
      '#title' => $this->t('Manage existing'),
      '#type' => 'link',
      '#url' => $link,
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // If the newsletter checkboxes are available, at least one must be checked.
    if (!$this->getSubscriptionWidget($form_state)->isHidden() && !count($form_state->getValue('subscriptions'))) {
      $form_state->setErrorByName('subscriptions', $this->t('You must select at least one newsletter.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // For an anonymous user the email is unknown in buildForm, but here we can
    // try again to load an existing subscriber.
    $mail = $form_state->getValue(['mail', 0, 'value']);
    if ($this->entity->isNew() && $subscriber = Subscriber::loadByMail($mail)) {
      $this->setEntity($subscriber);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit callback that subscribes to selected newsletters.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitExtra(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface $subscription_manager */
    $subscription_manager = \Drupal::service('simplenews.subscription_manager');
    foreach ($this->extractNewsletterIds($form_state, TRUE) as $newsletter_id) {
      $subscription_manager->subscribe($this->entity->getMail(), $newsletter_id, NULL, 'website');
    }
    $sent = $subscription_manager->sendConfirmations();
    $this->messenger()->addMessage($this->getSubmitMessage($form_state, $sent));
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitMessage(FormStateInterface $form_state, $confirm) {
    if ($confirm) {
      return $this->t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.');
    }
    return $this->t('You have been subscribed.');
  }

}
