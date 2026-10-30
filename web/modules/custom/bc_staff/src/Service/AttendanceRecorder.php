<?php

namespace Drupal\bc_staff\Service;

use Drupal\user\UserInterface;

/**
 * Persists attendance changes together with an audit event.
 */
final class AttendanceRecorder {

  public static function record(UserInterface $employee, string $action, int $actor, string $source): void {
    if (!in_array($action, ['in', 'out'], TRUE)) {
      throw new \InvalidArgumentException('Invalid attendance action.');
    }
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    try {
      $previous = (string) ($employee->get('field_bc_staff_status')->value ?? '');
      $employee->set('field_bc_staff_status', $action === 'in' ? 'present' : 'absent');
      $employee->save();
      $db->insert('bc_staff_attendance')->fields([
        'uid' => (int) $employee->id(),
        'actor_uid' => $actor,
        'action' => $action,
        'created' => \Drupal::time()->getCurrentTime(),
        'previous_status' => $previous,
        'source' => $source,
      ])->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

}
