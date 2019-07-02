<?php

namespace Drupal\simplenews\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\user\UserInterface;

/**
 * Configure simplenews subscriptions of a user.
 */
class SubscriptionsAccountForm extends SubscriptionsFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    if (isset($user)) {
      $form_state->set('user', $user);
      // Load/create a subscriber from the user.
      $this->setEntity(Subscriber::loadByUid($user->id(), 'create'));
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitMessage(FormStateInterface $form_state, $op, $confirm) {
    $user = $form_state->get('user');
    if (\Drupal::currentUser()->id() == $user->id()) {
      return $this->t('Your newsletter subscriptions have been updated.');
    }
    return $this->t('The newsletter subscriptions for user %account have been updated.', array('%account' => $user->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $form_state->setRedirectUrl($this->entity->getUser()->toUrl());
  }

  /**
   * Checks access for the simplenews account form.
   *
   * @param \Drupal\user\UserInterface $user
   *   The account to use in the form.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result object.
   */
  public function checkAccess(UserInterface $user) {
    $account = $this->currentUser();

    // Deny access for anonymous user at /user/0/simplenews.
    if ($user->isAnonymous()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIfHasPermission($account, 'administer simplenews subscriptions')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'subscribe to newsletters')
        ->andIf(AccessResult::allowedIf($user->id() == $account->id())));
  }

}
