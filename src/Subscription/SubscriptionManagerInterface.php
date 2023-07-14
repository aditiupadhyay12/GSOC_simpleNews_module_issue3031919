<?php

namespace Drupal\simplenews\Subscription;

/**
 * Subscription management; subscribe, unsubscribe and get subscription status.
 */
interface SubscriptionManagerInterface {

  /**
   * Subscribe a user to a newsletter or send a confirmation mail.
   *
   * The $confirm parameter determines the action:
   *   FALSE = The user is subscribed
   *   TRUE  = User receives an email to verify the address and complete the
   * subscription A new subscription account is created when the user is
   * subscribed to the first newsletter.
   *
   * @param string $mail
   *   The email address to subscribe to the newsletter.
   * @param string $newsletter_id
   *   The newsletter ID.
   * @param bool $deprecated
   *   Must be set to FALSE.
   * @param string $source
   *   Indication for source of subscription. Simplenews uses these sources:
   *    website: via any website form (with or without confirmation email)
   *    mass subscribe: mass admin UI
   *    mass unsubscribe: mass admin UI
   *    action: Drupal actions.
   * @param string $preferred_langcode
   *   The language code (i.e. 'en', 'nl') of the user preferred language.
   *   Use '' for the site default language.
   *   Use NULL for the language of the current page.
   *
   * @return $this
   */
  public function subscribe($mail, $newsletter_id, $deprecated, $source = 'unknown', $preferred_langcode = NULL);

  /**
   * Unsubscribe a user from a mailing list or send a confirmation mail.
   *
   * The $confirm parameter determines the action:
   *   FALSE = The user is unsubscribed
   *   TRUE  = User receives an email to verify the address and complete the
   *   subscription cancellation.
   *
   * @param string $mail
   *   The email address to unsubscribe from the mailing list.
   * @param string $newsletter_id
   *   The newsletter ID.
   * @param bool $deprecated
   *   Must be set to FALSE.
   * @param string $source
   *   Indicates the unsubscribe source. Simplenews uses these sources:
   *   - website: Via any website form (with or without confirmation email).
   *   - mass subscribe: Mass admin UI.
   *   - mass unsubscribe: Mass admin UI.
   *   - action: Drupal actions.
   *
   * @return $this
   */
  public function unsubscribe($mail, $newsletter_id, $deprecated, $source = 'unknown');

  /**
   * Check if the email address is subscribed to the given mailing list.
   *
   * @param string $mail
   *   The email address to be checked.
   * @param string $newsletter_id
   *   The mailing list id.
   *
   * @return bool
   *   TRUE if the email address is subscribed; otherwise false.
   *
   * @ingroup subscription
   *
   * @todo Caching should be done in simplenews_load_user_by_mail().
   */
  public function isSubscribed($mail, $newsletter_id);

  /**
   * Reset static caches.
   */
  public function reset();

  /**
   * Tidy unconfirmed subscriptions.
   */
  public function tidy();

}
