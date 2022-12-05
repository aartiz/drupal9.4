<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\jsonapi_user_resources\Events\RegistrationEvent;
use Drupal\jsonapi_user_resources\Events\UserResourcesEvents;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * User registration event subscriber.
 */
final class UserRegistrationSubscriber implements EventSubscriberInterface {

  /**
   * User settings config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $userSettings;

  /**
   * UserRegistrationSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->userSettings = $config_factory->get('user.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UserResourcesEvents::REGISTRATION_COMPLETE => 'sendEmailNotifications',
    ];
  }

  /**
   * Sends email notifications when a user has registered.
   *
   * @param \Drupal\jsonapi_user_resources\Events\RegistrationEvent $event
   *   The registration event.
   */
  public function sendEmailNotifications(RegistrationEvent $event) {
    $account = $event->getUser();
    $approval_settings = $this->userSettings->get('register');
    // No e-mail verification is required. Activating the user.
    if ($approval_settings === UserInterface::REGISTER_VISITORS) {
      if ($this->userSettings->get('verify_mail')) {
        // No administrator approval required.
        _user_mail_notify('register_no_approval_required', $account);
      }
    }
    // Administrator approval required.
    elseif ($approval_settings === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL) {
      _user_mail_notify('register_pending_approval', $account);
    }
  }

}
