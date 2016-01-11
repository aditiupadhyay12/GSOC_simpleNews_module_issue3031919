<?php

/**
 * @file
 * Contains \Drupal\simplenews\Tests\SimplenewsFieldUiTest.
 */

namespace Drupal\simplenews\Tests;

/**
 * Tests integration with field_ui.
 *
 * @group simplenews
 */
class SimplenewsFieldUiTest extends SimplenewsTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('field_ui');

  /**
   * Test that a new content type has a simplenews_issue field when is used as a simplenews newsletter.
   */
  function testContentTypeCreation() {
    $admin_user = $this->drupalCreateUser(array(
      'administer blocks',
      'administer content types',
      'administer nodes',
      'administer node fields',
      'access administration pages',
      'administer permissions',
      'administer newsletters',
      'administer simplenews subscriptions',
      'bypass node access',
      'send newsletter',
    ));
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/types');
    $this->clickLink(t('Add content type'));
    $edit = array(
      'name' => $name = $this->randomMachineName(),
      'type' => $type = strtolower($name),
      'simplenews_content_type' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save and manage fields'));
    $this->drupalGet('admin/structure/types/manage/' . $type . '/fields');
    $this->assertText('simplenews_issue');
  }
}
