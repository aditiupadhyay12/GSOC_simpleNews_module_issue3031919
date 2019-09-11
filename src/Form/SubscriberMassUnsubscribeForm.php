<?php

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Do a mass subscription for a list of email addresses.
 */
class SubscriberMassUnsubscribeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_subscriber_mass_unsubscribe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['emails'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email addresses'),
      '#cols' => 60,
      '#rows' => 5,
      '#description' => $this->t('Email addresses must be separated by comma, space or newline.'),
    ];

    $form['newsletters'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Unsubscribe from'),
      '#options' => simplenews_newsletter_list(),
      '#required' => TRUE,
    ];

    foreach (simplenews_newsletter_get_all() as $id => $newsletter) {
      $form['newsletters'][$id]['#description'] = Html::escape($newsletter->description);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unsubscribe'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $removed = [];
    $invalid = [];
    $checked_lists = array_keys(array_filter($form_state->getValue('newsletters')));

    /** @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface $subscription_manager */
    $subscription_manager = \Drupal::service('simplenews.subscription_manager');
    $emails = preg_split("/[\s,]+/", $form_state->getValue('emails'));
    foreach ($emails as $email) {
      $email = trim($email);
      if (valid_email_address($email)) {
        foreach ($checked_lists as $newsletter_id) {
          $subscription_manager->unsubscribe($email, $newsletter_id, FALSE, 'mass unsubscribe');
          $removed[] = $email;
        }
      }
      else {
        $invalid[] = $email;
      }
    }
    if ($removed) {
      $removed = implode(", ", $removed);
      $this->messenger()->addMessage($this->t('The following addresses were unsubscribed: %removed.', ['%removed' => $removed]));

      $newsletters = simplenews_newsletter_get_all();
      $list_names = [];
      foreach ($checked_lists as $newsletter_id) {
        $list_names[] = $newsletters[$newsletter_id]->label();
      }
      $this->messenger()->addMessage($this->t('The addresses were unsubscribed from the following newsletters: %newsletters.', ['%newsletters' => implode(', ', $list_names)]));
    }
    else {
      $this->messenger()->addMessage($this->t('No addresses were removed.'));
    }
    if ($invalid) {
      $invalid = implode(", ", $invalid);
      $this->messenger()->addError($this->t('The following addresses were invalid: %invalid.', ['%invalid' => $invalid]));
    }

    // Return to the parent page.
    $form_state->setRedirect('entity.simplenews_subscriber.collection');
  }

}
