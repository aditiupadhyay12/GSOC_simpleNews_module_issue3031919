<?php

namespace Drupal\simplenews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simplenews\Entity\Newsletter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\simplenews\Subscription\SubscriptionManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Time\TimeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The time service.
   *
   * @var \Drupal\Core\Time\TimeInterface
   */
  protected $time;

  /**
   * Constructs a \Drupal\simplenews\Controller\ConfirmationController object.
   *
   * @param \Drupal\simplenews\Subscription\SubscriptionManagerInterface $subscription_manager
   *   The subscription manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Time\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    SubscriptionManagerInterface $subscription_manager,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger,
    FormBuilderInterface $form_builder,
    TimeInterface $time
  ) {
    $this->subscriptionManager = $subscription_manager;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->formBuilder = $form_builder;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplenews.subscription_manager'),
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('form_builder'),
      $container->get('datetime.time')
    );
  }

  /**
   * Menu callback: Confirm the combined subscription request.
   *
   * This function is called to handle the confirmation of a combined
   * subscription request, which involves subscribing to multiple newsletters
   * in one action.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function confirmCombined(Request $request) {
    $snid = $request->query->get('snid');
    $timestamp = $request->query->get('timestamp');
    $hash = $request->query->get('hash');

    $config = $this->configFactory->get('simplenews.settings');

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
    // Check if subscriber exists and has a valid email address
    if ($subscriber && $subscriber->getEmail() && $hash == simplenews_generate_hash($subscriber->getMail(), 'add', $timestamp)) {
      // Example logic to handle combined subscription confirmation
      // You can implement your specific logic here for combined subscriptions

      // Example response.
      return new Response('Combined subscription confirmed.');
    }

    throw new NotFoundHttpException();
  }

  /**
   * Menu callback: confirm the user's (un)subscription request.
   *
   * This function is called by clicking the confirm link in the confirmation
   * email or the unsubscribe link in the footer of the newsletter. It handles
   * both subscription addition and subscription removal.
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
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Drupal\Core\Form\FormInterface
   *   Returns a redirect response or confirmation form.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the subscriber or hash is not found or invalid.
   */
  public function confirmSubscription($action, $snid, $newsletter_id, $timestamp, $hash, $immediate = FALSE) {
    $config = $this->configFactory->get('simplenews.settings');

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
    // Check if subscriber exists and has a valid email address
    if ($subscriber && $subscriber->getEmail() && $hash == simplenews_generate_hash($subscriber->getMail(), $action, $timestamp)) {
      $newsletter = Newsletter::load($newsletter_id);

      // If the hash is valid but timestamp is too old, display form to request a new hash
      if ($timestamp < $this->time->getRequestTime() - $config->get('hash_expiration')) {
        $context = [
          'simplenews_subscriber' => $subscriber,
          'newsletter' => $newsletter,
        ];
        $key = $action == 'add' ? 'subscribe_combined' : 'validate';
        $build = $this->formBuilder->getForm('\Drupal\simplenews\Form\RequestHashForm', $key, $context);
        $build['#attached']['html_head'][] = $html_head;
        return $build;
      }

      // Proceed with subscription or unsubscription
      if (!$immediate) {
        // Display confirmation form based on $action
        if ($action == 'remove') {
          $build = $this->formBuilder->getForm('\Drupal\simplenews\Form\ConfirmRemovalForm', $subscriber->getMail(), $newsletter);
          $build['#attached']['html_head'][] = $html_head;
          return $build;
        } elseif ($action == 'add') {
          $build = $this->formBuilder->getForm('\Drupal\simplenews\Form\ConfirmAddForm', $subscriber->getMail(), $newsletter);
          $build['#attached']['html_head'][] = $html_head;
          return $build;
        }
      } else {
        // Perform immediate action
        if ($action == 'remove') {
          // Check again before unsubscribing
          if ($subscriber->getEmail()) {
            $this->subscriptionManager->unsubscribe($subscriber->getMail(), $newsletter_id, FALSE, 'website');
            if ($path = $config->get('subscription.confirm_unsubscribe_page')) {
              $url = Url::fromUri("internal:$path");
              return $this->redirect($url->getRouteName(), $url->getRouteParameters());
            }
            $this->messenger->addMessage($this->t('%user was unsubscribed from the %newsletter mailing list.', ['%user' => $subscriber->getMail(), '%newsletter' => $newsletter->name]));
            return $this->redirect('<front>');
          } else {
            // Handle scenario where email is empty
            $this->messenger->addError($this->t('Cannot unsubscribe without a valid email address.'));
            return $this->redirect('<front>');
          }
        } elseif ($action == 'add') {
          // Check again before subscribing
          if ($subscriber->getEmail()) {
            $this->subscriptionManager->subscribe($subscriber->getMail(), $newsletter_id, FALSE, 'website');
            if ($path = $config->get('subscription.confirm_subscribe_page')) {
              $url = Url::fromUri("internal:$path");
              return $this->redirect($url->getRouteName(), $url->getRouteParameters());
            }
            $this->messenger->addMessage($this->t('%user was added to the %newsletter mailing list.', ['%user' => $subscriber->getMail(), '%newsletter' => $newsletter->name]));
            return $this->redirect('<front>');
          } else {
            // Handle scenario where email is empty
            $this->messenger->addError($this->t('Cannot subscribe without a valid email address.'));
            return $this->redirect('<front>');
          }
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

