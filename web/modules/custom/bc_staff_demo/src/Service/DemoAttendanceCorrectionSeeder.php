<?php

namespace Drupal\bc_staff_demo\Service;

/**
 * Adds a traceable correction to a fictional attendance registration.
 */
final class DemoAttendanceCorrectionSeeder {

  public static function seed(): int {
    $db = \Drupal::database();
    if (!$db->schema()->tableExists('bc_staff_attendance_correction')
      || !$db->schema()->tableExists('bc_staff_attendance')) {
      return 0;
    }
    $users = \Drupal::entityTypeManager()->getStorage('user');
    $accounts = $users->loadByProperties(['name' => 'bc_demo_employee_naestved_1']);
    $account = reset($accounts);
    if (!$account || !$account->hasField('field_bc_staff_demo')
      || !(bool) $account->get('field_bc_staff_demo')->value) {
      return 0;
    }
    $event = $db->select('bc_staff_attendance', 'a')
      ->fields('a', ['id', 'uid', 'created'])
      ->condition('uid', (int) $account->id())
      ->condition('action', 'out')
      ->orderBy('created', 'DESC')->range(0, 1)->execute()->fetchObject();
    if (!$event) { return 0; }
    $existing = $db->select('bc_staff_attendance_correction', 'c')
      ->condition('event_id', (int) $event->id)->countQuery()->execute()->fetchField();
    if ($existing) { return 0; }
    $db->insert('bc_staff_attendance_correction')->fields([
      'event_id' => (int) $event->id,
      'uid' => (int) $account->id(),
      'actor_uid' => (int) $account->id(),
      'corrected_at' => (int) $event->created - 900,
      'reason' => 'Demo: checkout time corrected by 15 minutes.',
      'created' => \Drupal::time()->getCurrentTime(),
    ])->execute();
    return 1;
  }
}
