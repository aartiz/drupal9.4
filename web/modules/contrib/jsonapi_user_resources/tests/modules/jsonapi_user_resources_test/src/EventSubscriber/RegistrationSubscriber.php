<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources_test\EventSubscriber;

use Drupal\jsonapi_user_resources\Events\RegistrationEvent;
use Drupal\jsonapi_user_resources\Events\UserResourcesEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Test event subscriber to the user registration event.
 */
final class RegistrationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UserResourcesEvents::REGISTRATION_COMPLETE => ['onUserRegistration'],
    ];
  }

  /**
   * React on user registration.
   *
   * @param \Drupal\jsonapi_user_resources\Events\RegistrationEvent $event
   *   The event.
   */
  public function onUserRegistration(RegistrationEvent $event) {
    // @codingStandardsIgnoreStart
    /* Blocked on https://www.drupal.org/project/jsonapi_resources/issues/3105792
    $document = $event->getDocument();
    $resource_object = $document->getData()->getIterator()->offsetGet(0);
    assert($resource_object instanceof NewResourceObject);
    $meta = $resource_object->getMeta();
    if (isset($meta['validation']) && $meta['validation'] !== 'PASS') {
      throw new BadRequestHttpException('Unable to validate the request.');
    }
    */
    // @codingStandardsIgnoreEnd
    $user = $event->getUser();
    if ($user->getEmail() === 'blockedEmail@example.com') {
      throw new BadRequestHttpException('Unable to validate the request.');
    }
  }

}
