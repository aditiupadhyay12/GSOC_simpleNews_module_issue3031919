<?php

namespace Drupal\simplenews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\Entity\Newsletter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\simplenews\Subscription\SubscriptionManagerInterface;

/**
 * Returns responses for confirmation and subscriber routes.
 */
class ConfirmationController extends ControllerBase {

  /**
   * The subscription manager.
   *
   * @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface
   */
  protected $subscriptionManager;

  /**
   * Constructs a \Drupal\simplenews\Controller\ConfirmationController object.
   *
   * @param \Drupal\simplenews\Subscription\SubscriptionManagerInterface $subscription_manager
   *   The subscription manager service.
   */
  public function __construct(SubscriptionManagerInterface $subscription_manager) {
    $this->subscriptionManager = $subscription_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplenews.subscription_manager')
    );
  }

  /**
   * Menu callback: confirm a subscribe request.
   *
   * This function is called by clicking the confirm link in the confirmation
   * email. It acts on an existing unconfirmed subscriber to make it confirmed.
   *
   * @param int $snid
   *   The subscriber id.
   * @param int $timestamp
   *   The timestamp of the request.
   * @param bool $hash
   *   The confirmation hash.
   * @param bool $immediate
   *   Perform the action immediately if TRUE.
   *
   * @see simplenews_confirm_add_form()
   * @see simplenews_confirm_removal_form()
   */
  public function confirmSubscribe($snid, $timestamp, $hash, $immediate = FALSE) {
    $config = $this->config('simplenews.settings');

    // Prevent search engines from indexing this page.
    $html_head = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'robots',
          'content' => 'noindex',
        ],
      ],
      'simplenews-noindex',
    ];

    $subscriber = Subscriber::load($snid);

    if ($subscriber && $hash == simplenews_generate_hash($subscriber->getMail(), 'confirm', $timestamp)) {
      // If the hash is valid but timestamp is too old, display form to request
      // a new hash.
      if ($timestamp < \Drupal::time()->getRequestTime() - $config->get('hash_expiration')) {
        $context = [
          'simplenews_subscriber' => $subscriber,
        ];
        $build = $this->formBuilder()->getForm('\Drupal\simplenews\Form\RequestHashForm', 'confirm', $context);
        $build['#attached']['html_head'][] = $html_head;
        return $build;
      }
      // When not called with immediate parameter the user will be directed to
      // the (un)subscribe confirmation page.
      if (!$immediate) {
        $build = $this->formBuilder()->getForm('\Drupal\simplenews\Form\ConfirmMultiForm', $subscriber);
        $build['#attached']['html_head'][] = $html_head;
        return $build;
      }
      else {
        $this->messenger()->addMessage($this->t('Subscription changes confirmed for %user.', ['%user' => $subscriber->getMail()]));
        $subscriber->setStatus(SubscriberInterface::ACTIVE)->save();
        return $this->redirect('<front>');
      }
    }

    throw new NotFoundHttpException();
  }

  /**
   * Menu callback: handle (un)subscription request.
   *
   * This function is called by clicking a link from a subscribe token
   * (subscribe-url or unsubscribe-url). It acts on an existing confirmed
   * subscriber to add or remove a single newsletter subscription. The most
   * common case is the unsubscribe link in the footer of the newsletter.
   *
   * @param string $action
   *   Either add or remove.
   * @param int $snid
   *   The subscriber id.
   * @param int $newsletter_id
   *   The newsletter id.
   * @param int $timestamp
   *   The timestamp of the request.
   * @param string $hash
   *   The confirmation hash.
   * @param bool $immediate
   *   Perform the action immediately if TRUE.
   */
  public function handleSubscription($action, $snid, $newsletter_id, $timestamp, $hash, $immediate = FALSE) {
    $config = $this->config('simplenews.settings');

    // Prevent search engines from indexing this page.
    $html_head = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'robots',
          'content' => 'noindex',
        ],
      ],
      'simplenews-noindex',
    ];

    $subscriber = Subscriber::load($snid);
    if ($subscriber && $hash == simplenews_generate_hash($subscriber->getMail(), $action, $timestamp)) {
      $newsletter = Newsletter::load($newsletter_id);

      // If the hash is valid but timestamp is too old, display form to request
      // a new hash.
      if ($timestamp < \Drupal::time()->getRequestTime() - $config->get('hash_expiration')) {
        $context = [
          'simplenews_subscriber' => $subscriber,
          'newsletter' => $newsletter,
        ];
        $build = $this->formBuilder()->getForm('\Drupal\simplenews\Form\RequestHashForm', 'validate', $context);
        $build['#attached']['html_head'][] = $html_head;
        return $build;
      }
      // When called with additional arguments the user will be directed to the
      // (un)subscribe confirmation page. The additional arguments will be
      // passed on to the confirmation page.
      if (!$immediate) {
        if ($action == 'remove') {
          $build = $this->formBuilder()->getForm('\Drupal\simplenews\Form\ConfirmRemovalForm', $subscriber->getMail(), $newsletter);
          $build['#attached']['html_head'][] = $html_head;
          return $build;
        }
        elseif ($action == 'add') {
          $build = $this->formBuilder()->getForm('\Drupal\simplenews\Form\ConfirmAddForm', $subscriber->getMail(), $newsletter);
          $build['#attached']['html_head'][] = $html_head;
          return $build;
        }
      }
      else {

        if ($action == 'remove') {
          $this->subscriptionManager->unsubscribe($subscriber->getMail(), $newsletter_id, FALSE, 'website');
          if ($path = $config->get('subscription.confirm_unsubscribe_page')) {
            $url = Url::fromUri("internal:$path");
            return $this->redirect($url->getRouteName(), $url->getRouteParameters());
          }
          $this->messenger()->addMessage($this->t('%user was unsubscribed from the %newsletter mailing list.', ['%user' => $subscriber->getMail(), '%newsletter' => $newsletter->name]));
          return $this->redirect('<front>');
        }
        elseif ($action == 'add') {
          $this->subscriptionManager->subscribe($subscriber->getMail(), $newsletter_id, FALSE, 'website');
          if ($path = $config->get('subscription.confirm_subscribe_page')) {
            $url = Url::fromUri("internal:$path");
            return $this->redirect($url->getRouteName(), $url->getRouteParameters());
          }
          $this->messenger()->addMessage($this->t('%user was added to the %newsletter mailing list.', ['%user' => $subscriber->getMail(), '%newsletter' => $newsletter->name]));
          return $this->redirect('<front>');
        }
      }
    }
    throw new NotFoundHttpException();
  }

  /**
   * Redirects subscribers to the appropriate page.
   *
   * Redirect to the 'Newsletters' tab for authenticated users or the 'Access
   * your subscriptions' page otherwise.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the correct page.
   */
  public function subscriptionsPage() {
    $user = $this->currentUser();

    if ($user->isAuthenticated()) {
      return $this->redirect('simplenews.newsletter_subscriptions_user', ['user' => $user->id()]);
    }
    return $this->redirect('simplenews.newsletter_validate');
  }

}
