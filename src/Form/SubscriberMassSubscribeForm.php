<?php

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Do a mass subscription for a list of email addresses.
 */
class SubscriberMassSubscribeForm extends FormBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new SubscriberMassSubscribeForm.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_subscriber_mass_subscribe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['emails'] = array(
      '#type' => 'textarea',
      '#title' => t('Email addresses'),
      '#cols' => 60,
      '#rows' => 5,
      '#description' => t('Email addresses must be separated by comma, space or newline.'),
    );

    $form['newsletters'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Subscribe to'),
      '#options' => simplenews_newsletter_list(),
      '#required' => TRUE,
    );

    foreach (simplenews_newsletter_get_all() as $id => $newsletter) {
      $form['newsletters'][$id]['#description'] = Html::escape($newsletter->description);
    }

    $form['resubscribe'] = array(
      '#type' => 'checkbox',
      '#title' => t('Force resubscription'),
      '#description' => t('If checked, previously unsubscribed e-mail addresses will be resubscribed. Consider that this might be against the will of your users.'),
    );

    // Include language selection when the site is multilingual.
    // Default value is the empty string which will result in receiving emails
    // in the site's default language.
    if ($this->languageManager->isMultilingual()) {
      $options[''] = t('Site default language');
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $langcode => $language) {
        $options[$langcode] = $language->getName();
      }
      $form['language'] = array(
        '#type' => 'radios',
        '#title' => t('Anonymous user preferred language'),
        '#default_value' => '',
        '#options' => $options,
        '#description' => t('New subscriptions will be subscribed with the selected preferred language. The language of existing subscribers is unchanged.'),
      );
    }
    else {
      $form['language'] = array(
        '#type' => 'value',
        '#value' => '',
      );
    }

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Subscribe'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $added = array();
    $invalid = array();
    $unsubscribed = array();
    $checked_newsletters = array_keys(array_filter($form_state->getValue('newsletters')));
    $langcode = $form_state->getValue('language');

    $emails = preg_split("/[\s,]+/", $form_state->getValue('emails'));
    foreach ($emails as $email) {
      $email = trim($email);
      if ($email == '') {
        continue;
      }
      if (valid_email_address($email)) {
        $subscriber = Subscriber::loadByMail($email);

        /** @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface $subscription_manager */
        $subscription_manager = \Drupal::service('simplenews.subscription_manager');

        foreach (Newsletter::loadMultiple($checked_newsletters) as $newsletter) {
          // If there is a valid subscriber, check if there is a subscription for
          // the current newsletter and if this subscription has the status
          // unsubscribed.
          $is_unsubscribed = $subscriber ? $subscriber->isUnsubscribed($newsletter->id()) : FALSE;
          if (!$is_unsubscribed || $form_state->getValue('resubscribe') == TRUE) {
            $subscription_manager->subscribe($email, $newsletter->id(), FALSE, 'mass subscribe', $langcode);
            $added[] = $email;
          }
          else {
            $unsubscribed[$newsletter->label()][] = $email;
          }
        }
      }
      else {
        $invalid[] = $email;
      }
    }
    if ($added) {
      $added = implode(", ", $added);
      $this->messenger()->addMessage(t('The following addresses were added or updated: %added.', array('%added' => $added)));

      $list_names = array();
      foreach (Newsletter::loadMultiple($checked_newsletters) as $newsletter) {
        $list_names[] = $newsletter->label();
      }
      $this->messenger()->addMessage(t('The addresses were subscribed to the following newsletters: %newsletters.', array('%newsletters' => implode(', ', $list_names))));
    }
    else {
      $this->messenger()->addMessage(t('No addresses were added.'));
    }
    if ($invalid) {
      $invalid = implode(", ", $invalid);
      $this->messenger()->addError(t('The following addresses were invalid: %invalid.', array('%invalid' => $invalid)));
    }

    foreach ($unsubscribed as $name => $subscribers) {
      $subscribers = implode(", ", $subscribers);
      $this->messenger()->addWarning(t('The following addresses were skipped because they have previously unsubscribed from %name: %unsubscribed.', array('%name' => $name, '%unsubscribed' => $subscribers)));
    }

    if (!empty($unsubscribed)) {
      $this->messenger()->addWarning(t("If you would like to resubscribe them, use the 'Force resubscription' option."));
    }

    // Return to the parent page.
    $form_state->setRedirect('view.simplenews_subscribers.page_1');
  }
}
