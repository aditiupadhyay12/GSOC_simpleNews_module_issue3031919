<?php

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the subscriber edit forms.
 *
 * The acting user is someone with administrative privileges managing any
 * subscriber.
 */
class SubscriberForm extends SubscriptionsFormBase {

  /**
   * {@inheritdoc}
   */
  protected $allowDelete = TRUE;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\simplenews\SubscriberInterface $subscriber */
    $subscriber = $this->entity;

    // Adjust form title dynamically based on subscriber's email.
    if ($mail = $subscriber->getMail()) {
      $form['#title'] = $this->t('Edit subscriber @mail', ['@mail' => $mail]);
    }

<<<<<<< HEAD
    // Add activation status fieldset.
    $form['activated'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Status'),
      '#description' => $this->t('Whether the subscription is active or blocked.'),
      '#weight' => 15,
    ];

    // Add active checkbox.
    $form['activated']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#default_value' => $subscriber->getStatus(),
      '#disabled' => !$subscriber->get('status')->access('edit'), // Disable if user cannot edit status.
    ];

    // If multilingual, add preferred language fieldset.
    $language_manager = \Drupal::languageManager();
    if ($language_manager->isMultilingual()) {
      $languages = $language_manager->getLanguages();
      foreach ($languages as $langcode => $language) {
        $language_options[$langcode] = $language->getName();
      }

      // Add language fieldset.
      $form['language'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Preferred language'),
        '#description' => $this->t('The emails will be localized in the chosen language.'),
        '#disabled' => FALSE,
      ];

      // Determine language selection method based on user status.
      if ($subscriber->getUserId()) {
        // Display user's preferred language if defined.
        $form['language']['langcode'] = [
          '#type' => 'item',
          '#title' => $this->t('User language'),
          '#markup' => $subscriber->language()->getName(),
        ];
      } else {
        // Allow selecting language for anonymous subscribers.
        $form['language']['langcode'] = [
          '#type' => 'select',
          '#default_value' => $subscriber->language()->getId(),
          '#options' => $language_options,
          '#required' => TRUE,
        ];
      }
=======
    if ($user = $subscriber->getUser()) {
      $form['user'] = [
        '#markup' => $this->t('This Subscription is linked to user @user. Edit the user to change the subscriber language, email and status.', ['@user' => $user->toLink(NULL, 'edit-form')->toString()]),
        '#weight' => -1,
      ];
>>>>>>> f268e5bf5b2c4ef1eed8d21dd22739a582eee72c
    }

    // Add email field for anonymous users.
    $user = \Drupal::currentUser();
    if ($user->isAnonymous()) {
      $form['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Email'),
        '#required' => TRUE,
      ];
    } else {
      // Check if user has an email address.
      $user_email = $user->getEmail();
      if (empty($user_email)) {
        // Provide a message indicating the subscription will be inactive until an email is set.
        $form['email_message'] = [
          '#markup' => $this->t('Your subscription will remain inactive until you set an email address.'),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitMessage(FormStateInterface $form_state, $confirm) {
    // Customize submit message based on form ID.
    if ($this->getFormId() == 'simplenews_subscriber_add_form') {
      return $this->t('Subscriber %label has been added.', ['%label' => $this->entity->label()]);
    } else {
      return $this->t('Subscriber %label has been updated.', ['%label' => $this->entity->label()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Process the subscription logic here.
    $user = \Drupal::currentUser();
    $email = $user->isAnonymous() ? $form_state->getValue('email') : $user->getEmail();

    if (empty($email)) {
      // Create subscription with inactive status
      $this->createSubscription($user, $email, 'inactive');
    } else {
      // Create subscription with active status
      $this->createSubscription($user, $email, 'active');
    }

    // Redirect to subscriber collection after form submission.
    $form_state->setRedirect('entity.simplenews_subscriber.collection');
  }

  private function createSubscription($user, $email, $status) {
    // Logic to create subscription with given status
    // ...
  }

}
