<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\Events;

/**
 * Constants containing event names for this module.
 */
final class UserResourcesEvents {

  const REGISTRATION_VALIDATE = 'jsonapi_user_resources.registration_validate';

  const REGISTRATION_COMPLETE = 'jsonapi_user_resources.registration_complete';

  const PASSWORD_RESET = 'jsonapi_user_resources.password_reset';

}
