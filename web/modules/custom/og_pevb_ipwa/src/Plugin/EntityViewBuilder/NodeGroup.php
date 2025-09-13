<?php

namespace Drupal\og_pevb_ipwa\Plugin\EntityViewBuilder;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgAccessInterface;
use Drupal\server_general\EntityViewBuilder\NodeViewBuilderAbstract;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\Core\Cache\Cache;

/**
 * Provides a view builder plugin for the "Group" content type.
 *
 * @EntityViewBuilder(
 *   id = "node.group",
 *   label = @Translation("Node - Group"),
 *   description = @Translation("Node view builder for Group bundle.")
 * )
 */
class NodeGroup extends NodeViewBuilderAbstract {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * The OG membership manager service.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected $membershipManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('renderer'),
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('og.access'),
      $container->get('og.membership_manager')
    );
  }

  /**
   * Constructs a NodeGroup plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $entity_type_manager,
    AccountProxyInterface $current_user,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer,
    $messenger,
    $module_handler,
    OgAccessInterface $og_access,
    MembershipManagerInterface $membership_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $current_user,
      $entity_repository,
      $language_manager,
      $renderer,
      $messenger,
      $module_handler
    );

    $this->currentUser = $current_user;
    $this->ogAccess = $og_access;
    $this->membershipManager = $membership_manager;
  }

  /**
   * Builds the "full" view mode for group nodes.
   */
  public function buildFull(array $build, NodeInterface $entity): array {
    $build[] = $this->buildSubscriptionSuggestion($entity);
    $build[] = $this->buildProcessedText($entity);

    return $build;
  }

  /**
   * Builds the subscription suggestion block.
   */
  protected function buildSubscriptionSuggestion(NodeInterface $entity): array {
    $variables = [
      'headline' => '',
      'message' => '',
      'button' => NULL,
    ];

    $name = $this->currentUser->isAuthenticated()
      ? $this->currentUser->getDisplayName()
      : $this->t('friend');

    // Always start with a headline.
    $variables['headline'] = $this->t('Hi @name,', ['@name' => $name]);

    // Case 1: Anonymous user.
    if ($this->currentUser->isAnonymous()) {
      $variables['button'] = [
        'login' => [
          'title' => $this->t('Login'),
          'url' => Url::fromRoute('user.login')->toString(),
        ],
        'register' => [
          'title' => $this->t('Register'),
          'url' => Url::fromRoute('user.register')->toString(),
        ],
      ];
      $variables['message'] = $this->t('to join this group called "@label"', [
        '@label' => $entity->label(),
      ]);
    }
    // Case 2: Group owner.
    elseif ($entity->getOwnerId() === $this->currentUser->id()) {
      $variables['message'] = $this->t('You are the owner of this group called "@label"', [
        '@label' => $entity->label(),
      ]);
    }
    else {
      // Look up membership in any state.
      $membership = $this->membershipManager->getMembership(
        $entity,
        $this->currentUser->id(),
        [] // empty array = all states.
      );

      if ($membership) {
        $state = $membership->getState();

        if ($state === \Drupal\og\OgMembershipInterface::STATE_ACTIVE) {
          $variables['message'] = $this->t('You are already subscribed to "@label"', [
            '@label' => $entity->label(),
          ]);
        }
        elseif ($state === \Drupal\og\OgMembershipInterface::STATE_BLOCKED) {
          $variables['message'] = $this->t('You are blocked from subscribing to "@label"', [
            '@label' => $entity->label(),
          ]);
        }
        elseif ($state === \Drupal\og\OgMembershipInterface::STATE_PENDING) {
          $variables['message'] = $this->t('Your subscription to "@label" is pending approval', [
            '@label' => $entity->label(),
          ]);
        }
      }
      elseif ($this->canUserSubscribe($this->currentUser, $entity)) {
        // Eligible user.
        $variables['button'] = [
          'subscribe' => [
            'title' => $this->t('Subscribe'),
            'url' => Url::fromUri('internal:/group/' . $entity->getEntityTypeId() . '/' . $entity->id() . '/subscribe')->toString(),
          ],
        ];
        $variables['message'] = $this->t('to this group called "@label"', [
          '@label' => $entity->label(),
        ]);
      }
      else {
        // No suggestion at all.
        return [];
      }
    }

    return [
      '#theme' => 'og_group_subscription_suggestion',
      '#headline' => $variables['headline'],
      '#message' => $variables['message'],
      '#button' => $variables['button'],
      '#cache' => [
        'contexts' => \Drupal\Core\Cache\Cache::mergeContexts(
          $entity->getCacheContexts(),
          ['user']
        ),
        'tags' => $entity->getCacheTags(),
      ],
    ];
  }

  /**
   * Determines whether a user can subscribe to a group.
   */
  protected function canUserSubscribe(AccountProxyInterface $account, NodeInterface $group): bool {
    // Use the user ID.
    $user_id = $account->id();

    // Ask for membership across ALL states by passing an empty array.
    // (MembershipManager defaults to STATE_ACTIVE if you omit the third argument.)
    $membership = $this->membershipManager->getMembership($group, $user_id, []);

    if ($membership) {
      $state = $membership->getState();

      // Deny subscribe if already active, blocked or pending.
      if (in_array($state, [
        OgMembershipInterface::STATE_ACTIVE,
        OgMembershipInterface::STATE_BLOCKED,
        OgMembershipInterface::STATE_PENDING,
      ], TRUE)) {
        return FALSE;
      }
    }

    // Fall back to OG access check for the 'subscribe' operation.
    return $this->ogAccess->userAccess($group, 'subscribe', $account)->isAllowed();
  }

}
