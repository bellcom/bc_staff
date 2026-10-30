<?php

namespace Drupal\bc_staff\Utility;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\user\UserInterface;

/**
 * Resolves a staff member's existing Drupal user picture.
 */
final class StaffPhoto {

  /**
   * Returns the uploaded user photo URL, or a generic avatar.
   */
  public static function url(UserInterface $account, FileUrlGeneratorInterface $generator, string $fallback): string {
    if ($account->hasField('user_picture') && !$account->get('user_picture')->isEmpty()) {
      $file = $account->get('user_picture')->entity;
      if ($file && $file->isPermanent()) {
        return $generator->generateString($file->getFileUri());
      }
    }
    return $fallback;
  }

}
