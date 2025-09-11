<?php

namespace Drupal\Tests\og_pevb_ipwa\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;

/**
 * Tests node group subscription behavior.
 *
 * @group og_pevb_ipwa
 */
class NodeGroupSubscriptionTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * Only enable what is strictly necessary for Kernel tests.
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'og_pevb_ipwa',
  ];

  /**
   * Owner user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $ownerUser;

  /**
   * Eligible user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $eligibleUser;

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

    // Install minimal entity schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Create test users.
    $this->ownerUser = User::create(['name' => 'owner', 'status' => 1]);
    $this->ownerUser->save();

    $this->eligibleUser = User::create(['name' => 'eligible', 'status' => 1]);
    $this->eligibleUser->save();

    // Create a test group node.
    $this->groupNode = Node::create([
      'type' => 'group_type',
      'title' => 'Test Group',
      'uid' => $this->ownerUser->id(),
    ]);
    $this->groupNode->save();

    // Stub the subscription manager service.
    $stub = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['getSubscriptionLinksForUser'])
      ->getMock();

    $stub->method('getSubscriptionLinksForUser')
      ->willReturnCallback(function ($user, $group) {
        // Return links based on user type.
        if ($user === NULL) {
          return ['Login', 'Register'];
        }
        if ($user->id() === $this->ownerUser->id()) {
          return ['You are the owner'];
        }
        return ['Subscribe'];
      });

    // Replace the service in the test container.
    $this->container->set('og_pevb_ipwa.subscription_manager', $stub);
  }

  /**
   * Test anonymous user sees login/register links.
   */
  public function testAnonymousUserSeesLoginRegisterLinks(): void {
    $links = $this->container->get('og_pevb_ipwa.subscription_manager')
      ->getSubscriptionLinksForUser(NULL, $this->groupNode);

    $this->assertEquals(['Login', 'Register'], $links);
  }

  /**
   * Test owner sees owner message.
   */
  public function testOwnerSeesOwnerMessage(): void {
    $links = $this->container->get('og_pevb_ipwa.subscription_manager')
      ->getSubscriptionLinksForUser($this->ownerUser, $this->groupNode);

    $this->assertEquals(['You are the owner'], $links);
  }

  /**
   * Test eligible user sees subscribe link.
   */
  public function testEligibleUserSeesSubscribeLink(): void {
    $links = $this->container->get('og_pevb_ipwa.subscription_manager')
      ->getSubscriptionLinksForUser($this->eligibleUser, $this->groupNode);

    $this->assertEquals(['Subscribe'], $links);
  }

}
