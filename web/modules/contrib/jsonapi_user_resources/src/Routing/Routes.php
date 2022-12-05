<?php

namespace Drupal\jsonapi_user_resources\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_user_resources\Resource\PasswordReset;
use Drupal\jsonapi_user_resources\Resource\PasswordUpdate;
use Drupal\jsonapi_user_resources\Resource\Registration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for our JSON:API user resources.
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The user resource type names.
   *
   * @var array
   */
  protected $resourceTypeNames;

  /**
   * Constructs a new Routes object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository')
    );
  }

  /**
   * Builds the JSON API user resources routes.
   */
  public function routes() {
    $routes = new RouteCollection();

    $routes->add('jsonapi_user_resources.registration', $this->getUserRegistrationRoute());
    $routes->add('jsonapi_user_resources.password_reset', $this->getPasswordResetRoute());
    $routes->add('jsonapi_user_resources.password_update', $this->getPasswordUpdateRoute());

    // Prefix all routes with the JSON:API route prefix.
    $routes->addPrefix('/%jsonapi%');
    $routes->addRequirements([
      '_user_is_logged_in' => 'FALSE',
    ]);

    return $routes;
  }

  /**
   * Gets the user registration route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  protected function getUserRegistrationRoute() {
    $route = new Route('/user/register');
    $route->setMethods(['POST']);
    $route->addDefaults([
      '_jsonapi_resource' => Registration::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames(),
    ]);

    return $route;
  }

  /**
   * Gets the password reset route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  public function getPasswordResetRoute() {
    $route = new Route('/user/password/reset');
    $route->setMethods(['POST']);
    $route->addDefaults([
      '_jsonapi_resource' => PasswordReset::class,
      '_jsonapi_resource_types' => $this->getResourceTypeNames(),
    ]);

    return $route;
  }

  /**
   * Gets the password update route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The route.
   */
  public function getPasswordUpdateRoute() {
    $route = new Route('/user/{user}/password/update');
    $resource_type_names = $this->getResourceTypeNames();
    $route->setMethods(['PATCH']);
    $route
      ->addDefaults([
        '_jsonapi_resource' => PasswordUpdate::class,
        '_jsonapi_resource_types' => $resource_type_names,
        'resource_type' => reset($resource_type_names),
      ])
      ->setOption('parameters', [
        'user' => [
          'type' => 'entity:user',
        ],
      ]);

    return $route;
  }

  /**
   * Get the resource type names for the user entity type.
   *
   * @return string[]
   *   The resource type names.
   */
  protected function getResourceTypeNames(): array {
    if (empty($this->resourceTypeNames)) {
      $resource_type = $this->resourceTypeRepository->get('user', 'user');
      $this->resourceTypeNames = [$resource_type->getTypeName()];
    }
    return $this->resourceTypeNames;
  }

}
