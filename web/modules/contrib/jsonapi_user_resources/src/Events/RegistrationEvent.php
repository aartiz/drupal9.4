<?php declare(strict_types = 1);

namespace Drupal\jsonapi_user_resources\Events;

use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the event for reacting to user account registrations.
 */
final class RegistrationEvent extends Event {

  /**
   * The user account being registered.
   *
   * @var \Drupal\user\UserInterface
   */
  private $user;

  /**
   * The JSON:API request document.
   *
   * @var \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel
   */
  private $document;

  /**
   * Constructs a new RegistrationEvent object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The JSON:API request document.
   */
  public function __construct(UserInterface $user, JsonApiDocumentTopLevel $document) {
    $this->user = $user;
    $this->document = $document;
  }

  /**
   * Get the user account.
   *
   * @return \Drupal\user\UserInterface
   *   The user account.
   */
  public function getUser() {
    return $this->user;
  }

  /**
   * Get the JSON:API request document.
   *
   * @return \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel
   *   The document.
   */
  public function getDocument() {
    return $this->document;
  }

}
