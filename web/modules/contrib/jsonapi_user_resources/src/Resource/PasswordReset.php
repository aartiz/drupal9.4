<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\Resource;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\OmittedData;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\jsonapi_user_resources\Events\PasswordResetEvent;
use Drupal\jsonapi_user_resources\Events\UserResourcesEvents;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Route;

/**
 * User password reset resource.
 */
final class PasswordReset extends EntityResourceBase implements ContainerInjectionInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * Constructs a new PasswordReset object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(LoggerInterface $logger, EventDispatcherInterface $event_dispatcher) {
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('logger.factory')->get('user'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Handles the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The document.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity could not be saved.
   */
  public function process(Request $request, JsonApiDocumentTopLevel $document): ResourceResponse {
    $data = $document->getData();
    if ($data->getCardinality() !== 1) {
      throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
    }
    $resource_object = $data->getIterator()->current();
    assert($resource_object instanceof ResourceObject);

    // Load by name if provided.
    $user_storage = $this->entityTypeManager->getStorage('user');
    assert($user_storage instanceof UserStorageInterface);
    if ($resource_object->getField('name')) {
      $users = $user_storage->loadByProperties(['name' => $resource_object->getField('name')]);
    }
    elseif ($resource_object->getField('mail')) {
      $users = $user_storage->loadByProperties(['mail' => $resource_object->getField('mail')]);
    }
    else {
      throw new UnprocessableEntityHttpException('Missing name or mail');
    }

    $account = reset($users);
    if (!$account instanceof UserInterface) {
      // Error if no users found with provided name or mail.
      throw new UnprocessableEntityHttpException('Unrecognized username or email address.');
    }
    if ($this->userIsBlocked($account->getAccountName())) {
      throw new UnprocessableEntityHttpException('The user has not been activated or is blocked.');
    }
    $this->logger->notice('A password reset has been requested for %name (%email).', ['%name' => $account->getAccountName(), '%email' => $account->getEmail()]);

    $event = new PasswordResetEvent($account, $document);
    $this->eventDispatcher->dispatch(UserResourcesEvents::PASSWORD_RESET, $event);
    return new ResourceResponse(new JsonApiDocumentTopLevel(
      new OmittedData([]),
      new NullIncludedData(),
      new LinkCollection([]),
      [
        'message' => new TranslatableMarkup(
          'Password reset requested for :name (:email).',
          [':name' => $account->getAccountName(), ':email' => $account->getEmail()]
        ),
      ]
    ), 202);
  }

  /**
   * Verifies if the user is blocked.
   *
   * @param string $name
   *   The username.
   *
   * @return bool
   *   TRUE if the user is blocked, otherwise FALSE.
   */
  protected function userIsBlocked($name): bool {
    return user_is_blocked($name);
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    $fields = [
      'name' => new ResourceTypeAttribute('name'),
      'mail' => new ResourceTypeAttribute('mail'),
    ];
    $resource_type = new ResourceType(
      'user',
      'password-reset',
      NULL,
      FALSE,
      FALSE,
      TRUE,
      FALSE,
      $fields
    );
    return [$resource_type];
  }

}
