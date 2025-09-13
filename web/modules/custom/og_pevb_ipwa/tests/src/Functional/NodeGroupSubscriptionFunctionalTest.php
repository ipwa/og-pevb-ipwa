<?php

namespace Drupal\Tests\og_pevb_ipwa\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Functional test for Group node subscription suggestions.
 *
 * @group og_pevb_ipwa
 */
class NodeGroupSubscriptionFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'block',
    'og',
    'og_pevb_ipwa',
  ];

  /**
   * The test group node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $groupNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a Group content type.
    $this->createContentType(['type' => 'group', 'name' => 'Group']);

    // Create a node of type Group.
    $owner = $this->drupalCreateUser();
    $this->groupNode = Node::create([
      'type' => 'group',
      'title' => 'My Test Group',
      'uid' => $owner->id(),
    ]);
    $this->groupNode->save();
  }

  /**
   * Test anonymous user sees login/register links.
   */
  public function testAnonymousUser(): void {
    $this->drupalGet($this->groupNode->toUrl('canonical'));

    // Assert the wrapper exists.
    $this->assertSession()->elementExists('css', '.group-subscription-suggestion');

    // Assert the buttons exist with correct labels.
    $this->assertSession()->elementExists('css', 'a.btn-login');
    $this->assertSession()->elementExists('css', 'a.btn-register');
  }

  /**
   * Test owner sees owner message (no buttons).
   */
  public function testOwnerUser(): void {
    $this->drupalLogin(User::load($this->groupNode->getOwnerId()));
    $this->drupalGet($this->groupNode->toUrl('canonical'));

    $this->assertSession()->elementExists('css', '.message');
    $this->assertSession()->pageTextContains('You are the owner of this group');

    // Assert no subscribe button.
    $this->assertSession()->elementNotExists('css', 'a.btn-subscribe');
  }

  /**
   * Test eligible user sees subscribe link.
   */
  public function testEligibleUser(): void {
    $eligibleUser = $this->drupalCreateUser();
    $this->drupalLogin($eligibleUser);
    $this->drupalGet($this->groupNode->toUrl('canonical'));

    $this->assertSession()->elementExists('css', 'a.btn-subscribe');
    $this->assertSession()->pageTextContains('to this group called "My Test Group"');
  }

}
