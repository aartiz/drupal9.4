<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\EventSubscriber;

use Drupal\jsonapi_user_resources\Events\PasswordResetEvent;
use Drupal\jsonapi_user_resources\Events\UserResourcesEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Sends the default password_reset email when a password has been reset.
 */
final class PasswordResetSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      UserResourcesEvents::PASSWORD_RESET => 'onPasswordReset',
    ];
  }

  /**
   * Handles the dispatched password reset event.
   *
   * @param \Drupal\jsonapi_user_resources\Events\PasswordResetEvent $event
   *   The event.
   */
  public function onPasswordReset(PasswordResetEvent $event) {
    $account = $event->getUser();
    // Send the password reset email.
    $mail = _user_mail_notify('password_reset', $account, $account->getPreferredLangcode());
    if (empty($mail)) {
      throw new UnprocessableEntityHttpException('Unable to send email. Contact the site administrator if the problem persists.');
    }
  }

}
