<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\Resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides a resource for updating the password and validating the account.
 */
final class PasswordUpdate extends EntityResourceBase implements ContainerInjectionInterface {

  /**
   * User settings config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $userSettings;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new PasswordUpdate object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TimeInterface $time) {
    $this->userSettings = $config_factory->get('user.settings');
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('config.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * Handles the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The document.
   * @param \Drupal\user\UserInterface $user
   *   The account.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if the entity could not be saved.
   */
  public function process(Request $request, JsonApiDocumentTopLevel $document, UserInterface $user): ResourceResponse {
    $data = $document->getData();
    if ($data->getCardinality() !== 1) {
      throw new UnprocessableEntityHttpException("The request document's primary data must not be an array.");
    }

    $resource_object = $data->getIterator()->current();
    assert($resource_object instanceof ResourceObject);
    foreach (['timestamp', 'hash', 'pass'] as $required_property) {
      if (!$resource_object->hasField($required_property)) {
        throw new UnprocessableEntityHttpException(sprintf('Missing required property "%s".', $required_property));
      }
    }
    $timestamp = $resource_object->getField('timestamp');
    // Time out, in seconds, until reset URL expires.
    $timeout = $this->userSettings->get('password_reset_timeout');
    $current = $this->time->getRequestTime();
    $hash = $resource_object->getField('hash');

    // Copied from Drupal\user\Controller\UserController::confirmCancel.
    if ($timestamp <= $current && $current - $timestamp < $timeout && $timestamp >= $user->getLastLoginTime() && hash_equals($hash, user_pass_rehash($user, $timestamp))) {
      $user->setPassword($resource_object->getField('pass'));
      // Ensure we "activate" new users when the email verification is enabled.
      if ($user->isBlocked() && !$user->getLastAccessedTime() && $this->userSettings->get('verify_mail')) {
        $user->activate();
      }
      $user->save();
    }
    else {
      throw new UnprocessableEntityHttpException('The password reset information is no longer valid.');
    }

    $top_level_data = $this->createIndividualDataFromEntity($user);
    return $this->createJsonapiResponse($top_level_data, $request);
  }

}
