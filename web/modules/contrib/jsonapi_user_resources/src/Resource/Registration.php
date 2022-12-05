<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\Resource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\jsonapi_resources\Unstable\Entity\EntityCreationTrait;
use Drupal\jsonapi_user_resources\Events\RegistrationEvent;
use Drupal\jsonapi_user_resources\Events\UserResourcesEvents;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * User registration resource.
 */
final class Registration extends EntityResourceBase implements ContainerInjectionInterface {

  use EntityCreationTrait;

  /**
   * User settings config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $userSettings;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  private $entityRepository;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * Constructs a new Registration object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $account, EntityRepositoryInterface $entity_repository, EventDispatcherInterface $event_dispatcher) {
    $this->userSettings = $config_factory->get('user.settings');
    $this->currentUser = $account;
    $this->entityRepository = $entity_repository;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function modifyCreatedEntity(EntityInterface $created_entity, Request $request) {
    assert($created_entity instanceof UserInterface);
    $this->ensureAccountCanRegister($created_entity);

    // Only activate new users if visitors are allowed to register and no email
    // verification required.
    if ($this->userSettings->get('register') === UserInterface::REGISTER_VISITORS && !$this->userSettings->get('verify_mail')) {
      $created_entity->activate();
    }
    else {
      $created_entity->block();
    }

    $document = $this->getDocumentFromRequest($request);
    $event = new RegistrationEvent($created_entity, $document);
    $this->eventDispatcher->dispatch(UserResourcesEvents::REGISTRATION_VALIDATE, $event);
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if the entity could not be saved.
   */
  public function process(Request $request, JsonApiDocumentTopLevel $document): ResourceResponse {
    $response = $this->processEntityCreation($request, $document);
    $response_document = $response->getResponseData();
    assert($response_document instanceof JsonApiDocumentTopLevel);
    $resource_object = $response_document->getData()->getIterator()->offsetGet(0);
    assert($resource_object instanceof ResourceObject);
    $account = $this->entityRepository->loadEntityByUuid(
      $resource_object->getResourceType()->getEntityTypeId(),
      $resource_object->getId()
    );
    assert($account instanceof UserInterface);
    $event = new RegistrationEvent($account, $document);
    $this->eventDispatcher->dispatch(UserResourcesEvents::REGISTRATION_COMPLETE, $event);
    return $response;
  }

  /**
   * Ensure the account can be registered in this request.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account to register.
   */
  protected function ensureAccountCanRegister(UserInterface $account = NULL) {
    if ($account === NULL) {
      throw new BadRequestHttpException('No user account data for registration received.');
    }

    // POSTed user accounts must not have an ID set, because we always want to
    // create new entities here.
    if (!$account->isNew()) {
      throw new BadRequestHttpException('An ID has been set and only new user accounts can be registered.');
    }

    // Only allow anonymous users to register, authenticated users with the
    // necessary permissions can POST a new user to the "user" REST resource.
    // @see \Drupal\rest\Plugin\rest\resource\EntityResource
    if (!$this->currentUser->isAnonymous()) {
      throw new AccessDeniedHttpException('Only anonymous users can register a user.');
    }

    // Verify that the current user can register a user account.
    if ($this->userSettings->get('register') === UserInterface::REGISTER_ADMINISTRATORS_ONLY) {
      throw new AccessDeniedHttpException('You cannot register a new user account.');
    }

    if (!$this->userSettings->get('verify_mail')) {
      if (empty($account->getPassword())) {
        // If no e-mail verification then the user must provide a password.
        throw new UnprocessableEntityHttpException('No password provided.');
      }
    }
    else {
      if (!empty($account->getPassword())) {
        // If e-mail verification required then a password cannot provided.
        // The password will be set when the user logs in.
        throw new UnprocessableEntityHttpException('A Password cannot be specified. It will be generated on login.');
      }
    }
  }

}
