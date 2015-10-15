<?php

/**
 * @file
 * Contains \Drupal\simplenews\Tests\SimplenewsUninstallTest.
 */

namespace Drupal\simplenews\Tests;

/**
 * Tests that Simplenews module can be uninstalled.
 *
 * @group simplenews
 */
class SimplenewsUninstallTest extends SimplenewsTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser(array(
      'administer nodes',
      'administer simplenews settings',
      'administer simplenews subscriptions',
      'create simplenews_issue content',
      'administer modules',
    ));
    $this->drupalLogin($admin_user);

    // Subscribe a user.
    $this->setUpSubscribers(1);
  }

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('simplenews');

  /**
   * Tests that Simplenews module can be uninstalled.
   */
  public function testUninstall() {
    // Add a newsletter issue.
    $this->drupalCreateNode(['type' => 'simplenews_issue', 'label' => $this->randomMachineName()])->save();

    // Delete Simplenews data.
    $this->drupalPostForm('admin/config/services/simplenews/settings/uninstall', [], t('Delete Simplenews data'));
    $this->assertText(t('Simplenews data has been deleted.'));

    // Uninstall the module.
    $this->drupalPostForm('admin/modules/uninstall', ['uninstall[simplenews]' => TRUE], t('Uninstall'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));
    $this->assertText(t('The selected modules have been uninstalled.'));
    $this->assertNoText(t('Simplenews'));
  }

}
