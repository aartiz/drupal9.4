<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\Plugin\jsonapi_hypermedia\LinkProvider;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_hypermedia\AccessRestrictedLink;
use Drupal\jsonapi_hypermedia\Plugin\LinkProviderBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds an `authenticated-as` link for unauthenticated requests.
 *
 * @JsonapiHypermediaLinkProvider(
 *   id = "jsonapi.top_level.authenticated_as",
 *   link_relation_type = "authenticated-as",
 *   link_context = {
 *     "top_level_object" = true,
 *   }
 * )
 */
final class AuthenticatedAsLinkProvider extends LinkProviderBase implements ContainerFactoryPluginInterface {

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $userStorage;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  private $resourceTypeRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $provider = new static($configuration, $plugin_id, $plugin_definition);
    $provider->setCurrentUser($container->get('current_user'));
    $provider->setUserStorage(
      $container->get('entity_type.manager')->getStorage('user')
    );
    $provider->setResourceTypeRepository($container->get('jsonapi.resource_type.repository'));
    return $provider;
  }

  /**
   * Sets the current account.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current account.
   */
  public function setCurrentUser(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Sets the user storage.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The user storage.
   */
  public function setUserStorage(EntityStorageInterface $storage) {
    $this->userStorage = $storage;
  }

  /**
   * Set the resource type repository.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   */
  public function setResourceTypeRepository(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getLink($context) {
    assert($context instanceof JsonApiDocumentTopLevel);
    $link_cacheability = new CacheableMetadata();
    $link_cacheability->addCacheContexts(['user']);
    if ($this->currentUser->isAnonymous()) {
      return AccessRestrictedLink::createInaccessibleLink($link_cacheability);
    }

    $user = $this->userStorage->load($this->currentUser->id());
    if (!$user instanceof UserInterface) {
      return AccessRestrictedLink::createInaccessibleLink($link_cacheability);
    }

    $resource_type = $this->resourceTypeRepository->get($user->getEntityTypeId(), $user->bundle());
    $resource_type_name = $resource_type->getTypeName();
    return AccessRestrictedLink::createLink(
      AccessResult::allowedIf($user->isAuthenticated())->cachePerUser(),
      $link_cacheability,
      Url::fromRoute("jsonapi.$resource_type_name.individual", ['entity' => $user->uuid()]),
      $this->getLinkRelationType()
    );
  }

}
