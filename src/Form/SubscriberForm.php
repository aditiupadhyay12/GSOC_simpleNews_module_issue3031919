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

    if ($mail = $subscriber->getMail()) {
      $form['#title'] = $this->t('Edit subscriber @mail', ['@mail' => $mail]);
    }

    $language_manager = \Drupal::languageManager();
    if ($language_manager->isMultilingual()) {
      $languages = $language_manager->getLanguages();
      foreach ($languages as $langcode => $language) {
        $language_options[$langcode] = $language->getName();
      }
      $form['language'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Preferred language'),
        '#description' => $this->t('The e-mails will be localized in language chosen. Real users have their preference in account settings.'),
        '#disabled' => FALSE,
      ];
      if ($subscriber->getUserId()) {
        // Fallback if user has not defined a language.
        $form['language']['langcode'] = [
          '#type' => 'item',
          '#title' => $this->t('User language'),
          '#markup' => $subscriber->language()->getName(),
        ];
      }
      else {
        $form['language']['langcode'] = [
          '#type' => 'select',
          '#default_value' => $subscriber->language()->getId(),
          '#options' => $language_options,
          '#required' => TRUE,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitMessage(FormStateInterface $form_state, $confirm) {
    if ($this->getFormId() == 'simplenews_subscriber_add_form') {
      return $this->t('Subscriber %label has been added.', ['%label' => $this->entity->label()]);
    }
    return $this->t('Subscriber %label has been updated.', ['%label' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('entity.simplenews_subscriber.collection');
  }

}
