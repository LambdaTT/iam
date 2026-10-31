<?php

namespace Iam\Events;

use SplitPHP\Event;

/**
 * Class UserCreated
 *
 * Triggered right after a new user has been inserted into IAM_USER (see
 * Iam\Services\User::create). Carries the created user record AND the tags of the access
 * profiles the user is being created WITH — the profiles are attached by the caller RIGHT
 * AFTER create() returns (panel: updUserProfiles; signup: addProfileByTag), so they are not
 * in the database yet at dispatch time. Listeners therefore decide from the payload tags, not
 * from the database.
 *
 * @package Iam\Events
 */
class UserCreated extends Event
{
  /**
   * The name of the event.
   */
  public const EVENT_NAME = 'iam.user.created';

  /**
   * @var object $user The IAM_USER record that was created.
   */
  private object $user;

  /**
   * @var array $profileTags The access-profile tags the user is being created with.
   */
  private array $profileTags;

  /**
   * UserCreated constructor.
   *
   * @param object $user        The IAM_USER record that was created.
   * @param array  $profileTags The access-profile tags (ds_tag) the user will be attached to.
   */
  public function __construct(object $user, array $profileTags = [])
  {
    $this->user = $user;
    $this->profileTags = $profileTags;
  }

  /**
   * Returns a string representation of the event.
   *
   * @return string
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME
      . ' (user: ' . ($this->user->id_iam_user ?? '?')
      . ', profileTags: ' . json_encode($this->profileTags) . ')';
  }

  /**
   * Returns the created user and the access-profile tags it is being created with.
   *
   * @return object { user: object, profileTags: array }
   */
  public function info(): object
  {
    return (object) [
      'user'        => $this->user,
      'profileTags' => $this->profileTags,
    ];
  }
}
