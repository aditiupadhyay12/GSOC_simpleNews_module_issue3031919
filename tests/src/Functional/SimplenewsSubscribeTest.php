<?php

namespace Drupal\Tests\simplenews\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;

/**
 * Un/subscription of anonymous and authenticated users.
 *
 * Subscription via block, subscription page and account page.
 *
 * @group simplenews
 */
class SimplenewsSubscribeTest extends SimplenewsTestBase {

  /**
   * Subscribe to multiple newsletters at the same time.
   */
  public function testSubscribeMultiple() {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer permissions',
      'administer newsletters',
      'administer simplenews subscriptions',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/services/simplenews');
    for ($i = 0; $i < 5; $i++) {
      $this->clickLink(t('Add newsletter'));
      $name = $this->randomMachineName();
      $edit = [
        'name' => $name,
        'id' => strtolower($name),
        'description' => $this->randomString(20),
        'access' => 'default',
      ];
      $this->submitForm($edit, 'Save');
    }

    $newsletters = simplenews_newsletter_get_all();

    $this->drupalLogout();

    $enable = array_rand($newsletters, 3);
    $mail = $this->randomEmail(8);
    $this->subscribe($enable, $mail);
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));
    $this->assertMailText(t('We have received a request to subscribe @user', ['@user' => $mail]));

    $mails = $this->getMails();
    $this->assertEqual($mails[0]['from'], 'simpletest@example.com');
    $this->assertEqual($mails[0]['headers']['From'], '"Drupal" <simpletest@example.com>');

    $confirm_url = $this->extractConfirmationLink($this->getMail(0));

    $this->drupalGet($confirm_url);
    $this->assertRaw(t('Are you sure you want to confirm your subscription for %user?', ['%user' => simplenews_mask_mail($mail)]));
    $this->submitForm([], 'Confirm');
    $this->assertRaw(t('Subscription changes confirmed for %user.', ['%user' => $mail]));

    /** @var \Drupal\simplenews\Subscription\SubscriptionManagerInterface $subscription_manager */
    $subscription_manager = \Drupal::service('simplenews.subscription_manager');
    $subscription_manager->reset();
    $subscriber_storage = \Drupal::entityTypeManager()->getStorage('simplenews_subscriber');
    $subscriber_storage->resetCache();

    // Verify subscription changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      $is_subscribed = $subscription_manager->isSubscribed($mail, $newsletter_id);
      $subscription_newsletter = $subscriber_storage->getSubscriptionsByNewsletter($newsletter_id);

      if (in_array($newsletter_id, $enable)) {
        $this->assertTrue($is_subscribed);
        $this->assertEquals(1, count($subscription_newsletter));
      }
      else {
        $this->assertFalse($is_subscribed);
        $this->assertEquals(0, count($subscription_newsletter));
      }
    }

    // Go to the manage page and submit without changes.
    $subscriber = Subscriber::loadByMail($mail);
    $hash = simplenews_generate_hash($subscriber->getMail(), 'manage');
    $this->drupalGet('newsletter/subscriptions/' . $subscriber->id() . '/' . REQUEST_TIME . '/' . $hash);
    $this->submitForm([], 'Update');
    $this->assertText(t('Your newsletter subscriptions have been updated.'));
    $this->assertEquals(1, count($this->getMails()), t('No confirmation mails have been sent.'));

    // Unsubscribe from two of the three enabled newsletters.
    $disable = array_rand(array_flip($enable), 2);

    $edit = [
      'mail[0][value]' => $mail,
    ];
    foreach ($disable as $newsletter_id) {
      $edit['subscriptions[' . $newsletter_id . ']'] = FALSE;
    }
    $this->drupalGet('newsletter/subscriptions/' . $subscriber->id() . '/' . REQUEST_TIME . '/' . $hash);
    $this->submitForm($edit, t('Update'));

    // Verify subscription changes.
    $subscriber_storage->resetCache();
    $subscription_manager->reset();
    $still_enabled = array_diff($enable, $disable);
    foreach ($newsletters as $newsletter_id => $newsletter) {
      $is_subscribed = $subscription_manager->isSubscribed($mail, $newsletter_id);
      if (in_array($newsletter_id, $still_enabled)) {
        $this->assertTrue($is_subscribed);
      }
      else {
        $this->assertFalse($is_subscribed);
      }
    }

    // Unsubscribe with no confirmed email.
    $subscription_manager = \Drupal::service('simplenews.subscription_manager');
    try {
      $subscription_manager->unsubscribe('new@email.com', $newsletter_id, FALSE);
      $this->fail('Exception not thrown.');
    }
    catch (\Exception $e) {
      $this->assertEqual($e->getMessage(), 'The subscriber does not exist.');
    }

    // Test expired confirmation links.
    $enable = array_rand($newsletters, 3);

    $mail = $this->randomEmail(8);
    foreach ($enable as $newsletter_id) {
      $edit['subscriptions[' . $newsletter_id . ']'] = TRUE;
    }
    $this->subscribe($enable, $mail);

    $subscriber = Subscriber::loadByMail($mail);
    $expired_timestamp = REQUEST_TIME - 86401;
    $hash = simplenews_generate_hash($subscriber->getMail(), 'combined' . serialize($subscriber->getChanges()), $expired_timestamp);
    $url = 'newsletter/confirm/combined/' . $subscriber->id() . '/' . $expired_timestamp . '/' . $hash;
    $this->drupalGet($url);
    $this->assertText(t('This link has expired.'));
    $this->submitForm([], 'Request new confirmation mail');

    $confirm_url = $this->extractConfirmationLink($this->getMail());

    $this->assertMailText(t('We have received a request to subscribe @user', ['@user' => $mail]));
    $this->drupalGet($confirm_url);
    $this->assertRaw(t('Are you sure you want to confirm your subscription for %user?', ['%user' => simplenews_mask_mail($mail)]));

    $this->drupalGet($confirm_url);
    $this->submitForm([], 'Confirm');
    $this->assertRaw(t('Subscription changes confirmed for %user.', ['%user' => $mail]));
  }

  /**
   * Extract a confirmation link from a mail body.
   */
  protected function extractConfirmationLink($body) {
    $pattern = '@newsletter/confirm/.+/.+/.+/.{20,}@';
    $found = preg_match($pattern, $body, $match);
    if (!$found) {
      $this->fail(t('No confirmation URL found in "@body".', ['@body' => $body]));
      return FALSE;
    }
    $confirm_url = $match[0];
    $this->pass(t('Confirmation URL found: @url', ['@url' => $confirm_url]));
    return $confirm_url;
  }

  /**
   * Extract a validation link from a mail body.
   */
  protected function extractValidationLink($body) {
    $pattern = '@newsletter/subscriptions/.+/.+/.{20,}@';
    $found = preg_match($pattern, $body, $match);
    if (!$found) {
      $this->fail(t('No validation URL found in "@body".', ['@body' => $body]));
      return FALSE;
    }
    $validate_url = $match[0];
    $this->pass(t('Validation URL found: @url', ['@url' => $validate_url]));
    return $validate_url;
  }

  /**
   * TestSubscribeAnonymous.
   *
   * Steps performed:
   *   0. Preparation
   *   1. Subscribe anonymous via block
   *   3. Subscribe anonymous via multi block.
   */
  public function testSubscribeAnonymous() {
    // 0. Preparation
    // Login admin
    // Set permission for anonymous to subscribe
    // Enable newsletter block
    // Logout admin.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer newsletters',
      'administer permissions',
    ]
    );
    $this->drupalLogin($admin_user);

    // Create some newsletters for multi-sign up block.
    $this->drupalGet('admin/config/services/simplenews');
    for ($i = 0; $i < 5; $i++) {
      $this->clickLink(t('Add newsletter'));
      $name = $this->randomMachineName();
      $edit = [
        'name' => $name,
        'id' => strtolower($name),
        'description' => $this->randomString(20),
        'access' => 'default',
      ];
      $this->submitForm($edit, 'Save');
    }

    $newsletter_id = $this->getRandomNewsletter();

    $this->drupalLogout();

    // 1. Subscribe anonymous via block
    // Subscribe + submit
    // Assert confirmation message
    // Assert outgoing email
    //
    // Confirm using mail link
    // Confirm using mail link
    // Assert confirmation message
    // Setup subscription block with subscription form.
    $block_settings = [
      'newsletters' => [$newsletter_id],
      'message' => $this->randomMachineName(4),
    ];
    $single_block = $this->setupSubscriptionBlock($block_settings);

    // Testing invalid email error message.
    $mail = '@example.com';
    $edit = [
      'mail[0][value]' => $mail,
    ];
    $this->drupalGet('');
    $this->submitForm($edit, 'Subscribe');
    $this->assertText(t('The email address @mail is not valid', ['@mail' => $mail]));

    // Now with valid email.
    $mail = $this->randomEmail(8);
    $edit = [
      'mail[0][value]' => $mail,
    ];
    $this->drupalGet('');
    $this->submitForm($edit, 'Subscribe');
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));

    $subscriber = Subscriber::loadByMail($mail);
    $this->assertNotNull($subscriber, 'New subscriber entity successfully loaded.');
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEquals(SIMPLENEWS_SUBSCRIPTION_STATUS_UNCONFIRMED, $subscription->status, t('Subscription is unconfirmed'));
    $confirm_url = $this->extractConfirmationLink($this->getMail(0));

    $this->drupalGet($confirm_url);
    $newsletter = Newsletter::load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to confirm your subscription for %user?', ['%user' => simplenews_mask_mail($mail)]));

    $this->submitForm([], 'Confirm');
    $this->assertRaw(t('Subscription changes confirmed for %user.', ['%user' => $mail]));
    $this->assertUrl(new Url('<front>'));

    // Test that it is possible to register with a mail address that is already
    // a subscriber.
    $site_config = $this->config('user.settings');
    $site_config->set('register', 'visitors');
    $site_config->set('verify_mail', FALSE);
    $site_config->save();

    $pass = $this->randomMachineName();
    $edit = [
      'name' => $this->randomMachineName(),
      'mail' => $mail,
      'pass[pass1]' => $pass,
      'pass[pass2]' => $pass,
    ];
    $this->drupalGet('user/register');
    $this->submitForm($edit, 'Create new account');

    // Verify confirmation messages.
    $this->assertText(t('Registration successful. You are now logged in.'));

    // Verify that the subscriber has been updated and references to the correct
    // user.
    \Drupal::entityTypeManager()->getStorage('simplenews_subscriber')->resetCache();
    $subscriber = Subscriber::loadByMail($mail);
    $account = user_load_by_mail($mail);
    $this->assertEqual($subscriber->getUserId(), $account->id());
    $this->assertEqual($account->getDisplayName(), $edit['name']);

    $this->drupalLogout();

    // Disable the newsletter block.
    $single_block->delete();

    // 3. Subscribe anonymous via multi block.
    // Try to submit multi-signup form without selecting a newsletter.
    $mail = $this->randomEmail(8);
    $this->subscribe([], $mail);
    $this->assertText(t('You must select at least one newsletter.'));

    // Now fill out the form and try again.
    $this->subscribe($newsletter_id, $mail);
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));

    $confirm_url = $this->extractConfirmationLink($this->getMail());

    $this->drupalGet($confirm_url);
    $newsletter = Newsletter::load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to confirm your subscription for %user?', ['%user' => simplenews_mask_mail($mail)]));

    $this->submitForm([], 'Confirm');
    $this->assertRaw(t('Subscription changes confirmed for %user.', ['%user' => $mail]));

    // Try to subscribe again, this should not re-set the status to unconfirmed.
    $this->subscribe($newsletter_id, $mail);
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));

    $subscriber = Subscriber::loadByMail($mail);
    $this->assertNotEqual($subscriber, FALSE, 'New subscriber entity successfully loaded.');
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEquals(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);

    // Visit the newsletter/subscriptions page with the hash.
    $subscriber = Subscriber::loadByMail($mail);

    $hash = simplenews_generate_hash($subscriber->getMail(), 'manage');
    $this->drupalGet('newsletter/subscriptions/' . $subscriber->id() . '/' . REQUEST_TIME . '/' . $hash);
    $this->assertText(t('Subscriptions for @mail', ['@mail' => $mail]));

    $edit = [
      'subscriptions[' . $newsletter_id . ']' => TRUE,
    ];
    $this->submitForm($edit, 'Update');

    $this->assertText(t('Your newsletter subscriptions have been updated.'));

    // Make sure the subscription is confirmed.
    \Drupal::entityTypeManager()->getStorage('simplenews_subscriber')->resetCache();
    $subscriber = Subscriber::loadByMail($mail);

    $this->assertTrue($subscriber->isSubscribed($newsletter_id));
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEquals(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);

    // Attempt to fetch the page using a wrong hash but correct format.
    $hash = simplenews_generate_hash($subscriber->getMail() . 'a', 'manage');
    $this->drupalGet('newsletter/subscriptions/' . $subscriber->id() . '/' . REQUEST_TIME . '/' . $hash);
    $this->assertResponse(404);

    // Test expired confirmation links.
    $mail = $this->randomEmail();
    $this->subscribe($newsletter_id, $mail);

    $subscriber = Subscriber::loadByMail($mail);
    $expired_timestamp = REQUEST_TIME - 86401;
    $hash = simplenews_generate_hash($subscriber->getMail(), 'add', $expired_timestamp);
    $url = 'newsletter/confirm/add/' . $subscriber->id() . '/' . $newsletter_id . '/' . $expired_timestamp . '/' . $hash;
    $this->drupalGet($url);
    $this->assertText(t('This link has expired.'));
    $this->submitForm([], 'Request new confirmation mail');

    $confirm_url = $this->extractConfirmationLink($this->getMail());

    $this->drupalGet($confirm_url);
    $newsletter = Newsletter::load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to confirm your subscription for %user?', ['%user' => simplenews_mask_mail($mail)]));

    $this->submitForm([], 'Confirm');
    $this->assertRaw(t('Subscription changes confirmed for %user.', ['%user' => $mail]));

    // Make sure the subscription is confirmed now.
    \Drupal::entityTypeManager()->getStorage('simplenews_subscriber')->resetCache();
    $subscriber = Subscriber::loadByMail($mail);

    $this->assertTrue($subscriber->isSubscribed($newsletter_id));
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEquals(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);
  }

  /**
   * Test anonymous subscription without verification.
   *
   * Steps performed:
   *   0. Preparation
   *   1. Subscribe anonymous via block.
   */
  public function testSubscribeAnonymousSingle() {
    // 0. Preparation
    // Set global skip_verification to TRUE
    // Login admin
    // Create single opt in newsletter.
    // Set permission for anonymous to subscribe
    // Enable newsletter block
    // Logout admin.
    $config = $this->config('simplenews.settings');
    $config->set('subscription.skip_verification', TRUE);
    $config->save();

    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer permissions',
      'administer newsletters',
    ]
    );
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/services/simplenews');
    $this->clickLink(t('Add newsletter'));
    $name = $this->randomMachineName();
    $edit = [
      'name' => $name,
      'id' => strtolower($name),
      'description' => $this->randomString(20),
      'access' => 'default',
    ];
    $this->submitForm($edit, 'Save');

    $this->drupalLogout();

    $newsletter_id = $edit['id'];

    // Setup subscription block with subscription form.
    $block_settings = [
      'newsletters' => [$newsletter_id],
      'message' => $this->randomMachineName(4),
    ];
    $this->setupSubscriptionBlock($block_settings);

    // 1. Subscribe anonymous via block
    // Subscribe + submit
    // Assert confirmation message
    // Verify subscription state.
    $mail = $this->randomEmail(8);
    $edit = [
      'mail[0][value]' => $mail,
    ];
    $this->drupalGet('');
    $this->submitForm($edit, 'Subscribe');
    $this->assertText(t('You have been subscribed.'));

    $subscriber = Subscriber::loadByMail($mail);
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEquals(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);

  }

  /**
   * TestSubscribeAuthenticated.
   *
   * Steps performed:
   *   0. Preparation
   *   1. Subscribe authenticated via block
   *   2. Unsubscribe authenticated via subscription page
   *   3. Subscribe authenticated via subscription page
   *   4. Unsubscribe authenticated via account page
   *   5. Subscribe authenticated via account page
   *   6. Subscribe authenticated via multi block.
   */
  public function testSubscribeAuthenticated() {
    // 0. Preparation
    // Login admin
    // Set permission for anonymous to subscribe
    // Enable newsletter block
    // Logout admin
    // Login Subscriber.
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer permissions',
      'administer newsletters',
    ]
    );
    $this->drupalLogin($admin_user);

    // Create some newsletters for multi-sign up block.
    $this->drupalGet('admin/config/services/simplenews');
    for ($i = 0; $i < 5; $i++) {
      $this->clickLink(t('Add newsletter'));
      $name = $this->randomMachineName();
      $edit = [
        'name' => $name,
        'id' => strtolower($name),
        'description' => $this->randomString(20),
        'access' => 'default',
      ];
      $this->submitForm($edit, 'Save');
    }

    $newsletter_id = $this->getRandomNewsletter();
    $this->drupalLogout();

    // Setup subscription block with subscription form.
    $block_settings = [
      'newsletters' => [$newsletter_id],
      'message' => $this->randomMachineName(4),
    ];
    $single_block = $this->setupSubscriptionBlock($block_settings);
    $subscriber_user = $this->drupalCreateUser(['subscribe to newsletters']);
    $this->drupalLogin($subscriber_user);
    $this->assertEqual($this->countSubscribers(), 0);

    // 1. Subscribe authenticated via block
    // Subscribe + submit
    // Assert confirmation message.
    $this->submitForm([], 'Subscribe');
    $this->assertText(t('You have been subscribed.'));
    $this->assertEqual($this->countSubscribers(), 1);

    // Disable the newsletter block.
    $single_block->delete();

    // 3. Subscribe authenticated via subscription page redirecting to account page.
    // Subscribe + submit
    // Assert confirmation message.
    $this->resetSubscribers();
    $edit = [
      "subscriptions[$newsletter_id]" => '1',
    ];
    $url = 'user/' . $subscriber_user->id() . '/simplenews';
    $this->drupalGet('newsletter/subscriptions');
    $this->assertSession()->addressEquals($url);
    $this->submitForm($edit, 'Save');
    $this->assertRaw(t('Your newsletter subscriptions have been updated.'));
    $this->assertEqual($this->countSubscribers(), 1);

    // 4. Unsubscribe authenticated via account page
    // Unsubscribe + submit
    // Assert confirmation message.
    $edit = [
      "subscriptions[$newsletter_id]" => FALSE,
    ];
    $this->drupalGet($url);
    $this->submitForm($edit, 'Save');
    $this->assertRaw(t('Your newsletter subscriptions have been updated.'));

    $subscriber = Subscriber::loadByMail($subscriber_user->getEmail());
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEquals(SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED, $subscription->status, t('Subscription is unsubscribed'));

    // 5. Subscribe authenticated via account page
    // Subscribe + submit
    // Assert confirmation message.
    $this->resetSubscribers();
    $edit = [
      "subscriptions[$newsletter_id]" => '1',
    ];
    $url = 'user/' . $subscriber_user->id() . '/simplenews';
    $this->drupalGet($url);
    $this->submitForm($edit, 'Save');
    $this->assertRaw(t('Your newsletter subscriptions have been updated.'));
    $count = 1;
    $this->assertEqual($this->countSubscribers(), $count);

    // Try to submit multi-signup form without selecting a newsletter.
    $subscriber_user2 = $this->drupalCreateUser(['subscribe to newsletters']);
    $this->drupalLogin($subscriber_user2);

    // Check that the user has only access to their own subscriptions page.
    $this->drupalGet('user/' . $subscriber_user->id() . '/simplenews');
    $this->assertResponse(403);

    $this->drupalGet('user/' . $subscriber_user2->id() . '/simplenews');
    $this->assertResponse(200);

    $this->assertNoField('mail[0][value]');
    $this->submitForm([], 'Save');
    $this->assertText(t('Your newsletter subscriptions have been updated.'));

    // Nothing should have happened to subscriptions but this does create a
    // subscriber.
    $this->drupalGet('user/' . $subscriber_user2->id() . '/simplenews');
    $this->assertNoFieldChecked('edit-subscriptions-' . $newsletter_id);
    $count++;
    $this->assertEqual($this->countSubscribers(), $count);

    // Now fill out the form and try again.
    $edit = [
      'subscriptions[' . $newsletter_id . ']' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertText(t('Your newsletter subscriptions have been updated.'));
    $this->assertEqual($this->countSubscribers(), $count);

    $this->drupalGet('user/' . $subscriber_user2->id() . '/simplenews');
    $this->assertFieldChecked('edit-subscriptions-' . $newsletter_id);

    // Unsubscribe.
    $edit = [
      'subscriptions[' . $newsletter_id . ']' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertText(t('Your newsletter subscriptions have been updated.'));

    $this->drupalGet('user/' . $subscriber_user2->id() . '/simplenews');
    $this->assertNoFieldChecked('edit-subscriptions-' . $newsletter_id);
  }

  /**
   * Tests Creation of Simplenews Subscription block.
   */
  public function testSimplenewsSubscriptionBlock() {
    $admin_user = $this->drupalCreateUser([
      'administer blocks',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet('/admin/structure/block/add/simplenews_subscription_block/classy');
    // Check for Unique ID field.
    $this->assertText('Unique ID');
    $edit = [
      'settings[unique_id]' => 'test_simplenews_123',
      'settings[newsletters][default]' => TRUE,
      'region' => 'header',
    ];
    $this->submitForm($edit, 'Save block');
    $this->drupalGet('');
    // Provided Unique ID is used as form_id.
    $this->assertFieldByXPath("//*[@id=\"simplenews-subscriptions-block-test-simplenews-123\"]", NULL, 'Form ID found and contains expected value.');
  }

  /**
   * Tests admin creating a single subscriber.
   */
  public function testAdminCreate() {
    $admin_user = $this->drupalCreateUser(['administer simplenews subscriptions']);
    $this->drupalLogin($admin_user);

    $newsletter_id = $this->getRandomNewsletter();
    $mail = $this->randomEmail();
    $this->drupalGet('admin/people/simplenews/create');
    $this->assertText('Add subscriber');
    $edit = [
      "subscriptions[$newsletter_id]" => TRUE,
      'mail[0][value]' => $mail,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertText(t('Subscriber @mail has been added.', ['@mail' => $mail]));

    $subscriber = Subscriber::loadByMail($mail);
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEquals(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);

    // Check that an unsubscribe link works without any permissions.
    $this->drupalLogout();
    user_role_revoke_permissions(AccountInterface::ANONYMOUS_ROLE, ['subscribe to newsletters']);

    $node = $this->drupalCreateNode([
      'type' => 'simplenews_issue',
      'simplenews_issue[target_id]' => ['target_id' => $newsletter_id],
    ]);
    \Drupal::service('simplenews.spool_storage')->addIssue($node);
    \Drupal::service('simplenews.mailer')->sendSpool();

    $unsubscribe_url = $this->extractConfirmationLink($this->getMail(0));
    $this->drupalGet($unsubscribe_url);
    $this->assertText('Confirm remove subscription');
    $this->submitForm([], 'Unsubscribe');
    $this->assertText('was unsubscribed from the Default newsletter mailing list.');
  }

  /**
   * Tests access for a registered user to manage subscriptions with a hash.
   */
  public function testHashAuth() {
    $user = $this->drupalCreateUser(['subscribe to newsletters']);
    $mail = $user->getEmail();
    $subscriber = Subscriber::loadByMail($mail, 'create');
    $subscriber->save();

    $this->drupalGet('newsletter/subscriptions');
    $this->assertSession()->addressEquals('/newsletter/validate');

    $this->submitForm(['mail' => $mail], 'Submit');
    $this->assertSession()->pageTextContains("If $mail is subscribed, an email will be sent with a link to manage your subscriptions.");
    $this->assertSession()->addressEquals('');
    $validate_url = $this->extractValidationLink($this->getMail(0));

    $this->drupalGet($validate_url);
    $this->assertSession()->pageTextContains("Subscriptions for $mail");
    $this->submitForm([], 'Update');
    $this->assertText(t('Your newsletter subscriptions have been updated.'));
  }

  /**
   * Tests formatting and escaping of subscription mails.
   */
  public function testFormatting() {
    $this->config('simplenews.settings')
      ->set('subscription.confirm_combined_subject', 'Please <join> us & enjoy')
      ->set('subscription.confirm_combined_body', "Hello & welcome,\n\nclick to join us <[simplenews-subscriber:combined-url]>")
      ->save();

    $newsletter_id = $this->getRandomNewsletter();
    $newsletter = Newsletter::load($newsletter_id);
    $newsletter->name = 'Rise & <shine>';
    $newsletter->save();

    $mail = $this->randomEmail(8);
    $this->subscribe([], $mail);

    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $email = end($captured_emails);
    $this->assertEquals('Please <join> us & enjoy', $email['subject']);
    $this->assertStringContainsString("Hello & welcome,\n\nclick to join us\n<http", $email['body']);
  }

  /**
   * Gets the number of subscribers entities.
   */
  protected function countSubscribers() {
    return \Drupal::entityQuery('simplenews_subscriber')->count()->execute();
  }

  /**
   * Delete all subscriber entities ready for the next test.
   */
  protected function resetSubscribers() {
    $storage = \Drupal::entityTypeManager()->getStorage('simplenews_subscriber');
    $storage->delete($storage->loadMultiple());
  }

}
