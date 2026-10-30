<?php

namespace Drupal\bc_staff_demo\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\user\UserInterface;

/**
 * Installs explicitly matched fictional portraits for demo users.
 *
 * Portrait filenames are assigned per demo username, never inferred from
 * names or personal attributes. Existing user pictures are preserved.
 */
final class DemoPortraits {

  /**
   * Demo user to portrait assignment.
   */
  private const PORTRAITS = [
    'bc_demo_01' => 'portrait-01.jpg',
    'bc_demo_02' => 'portrait-02.jpg',
    'bc_demo_03' => 'portrait-03.jpg',
    'bc_demo_04' => 'portrait-04.jpg',
    'bc_demo_05' => 'portrait-05.jpg',
    'bc_demo_06' => 'portrait-06.jpg',
    'bc_demo_07' => 'portrait-07.jpg',
    'bc_demo_08' => 'portrait-08.jpg',
    'bc_demo_09' => 'portrait-09.jpg',
    'bc_demo_10' => 'portrait-10.jpg',
    'bc_demo_11' => 'portrait-11.jpg',
    'bc_demo_12' => 'portrait-12.jpg',
    'bc_demo_manager_naestved' => 'portrait-01.jpg',
    'bc_demo_manager_roskilde' => 'portrait-02.jpg',
    'bc_demo_employee_naestved_1' => 'portrait-03.jpg',
    'bc_demo_employee_naestved_2' => 'portrait-04.jpg',
    'bc_demo_employee_naestved_3' => 'portrait-05.jpg',
    'bc_demo_employee_roskilde_1' => 'portrait-06.jpg',
    'bc_demo_employee_roskilde_2' => 'portrait-07.jpg',
    'bc_demo_employee_roskilde_3' => 'portrait-08.jpg',
  ];

  /**
   * Sets the picture only for a demo user with an empty user_picture field.
   */
  public static function assign(UserInterface $account, string $username): bool {
    if (!$account->hasField('field_bc_staff_demo')
      || !(bool) $account->get('field_bc_staff_demo')->value
      || !$account->hasField('user_picture')
      || !$account->get('user_picture')->isEmpty()) {
      return FALSE;
    }
    $filename = self::PORTRAITS[$username] ?? NULL;
    $path = $filename ? dirname(__DIR__, 2) . '/assets/portraits/' . $filename : NULL;
    if (!$path || !is_file($path)) {
      return FALSE;
    }
    $data = file_get_contents($path);
    if ($data === FALSE) {
      return FALSE;
    }
    $filesystem = \Drupal::service('file_system');
    $directory = 'public://bc_staff_demo';
    if (!$filesystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return FALSE;
    }
    $repository = \Drupal::service('file.repository');
    $file = $repository->writeData($data, $directory . '/' . $username . '.jpg', FileExists::Replace);
    $file->setPermanent();
    $file->save();
    $account->set('user_picture', ['target_id' => $file->id()]);
    return TRUE;
  }

  /**
   * Checks whether the optional portrait package is installed.
   */
  public static function available(): bool {
    return is_file(dirname(__DIR__, 2) . '/assets/portraits/portrait-01.jpg');
  }

}
