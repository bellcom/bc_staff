<?php

namespace Drupal\bc_staff_demo\Service;

/**
 * Rebuilds fictional in-app notifications for marked demo accounts only.
 */
final class DemoNotificationSeeder {

  /**
   * @return int
   *   Number of notifications created.
   */
  public static function refresh(): int {
    $db = \Drupal::database();
    if (!\Drupal::moduleHandler()->moduleExists('bc_staff_absence')
      || !$db->schema()->tableExists('bc_staff_notification')) {
      return 0;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('user');
    // Mix of unread and read notifications, with both manager and employee
    // scenarios. The status of a demo inbox is deliberately reset on refresh.
    $scenarios = [
      'bc_demo_manager_naestved' => [
        ['Ny ferieansøgning', 'bc_staff_absence.review', -10, FALSE],
        ['Ferieændring afventer godkendelse', 'bc_staff_absence.review', -55, FALSE],
        ['Ferieansøgning ændret', 'bc_staff_absence.review', -190, FALSE],
        ['Ny ferieansøgning', 'bc_staff_absence.review', -1620, TRUE],
        ['Ferieændring afventer godkendelse', 'bc_staff_absence.review', -4000, TRUE],
      ],
      'bc_demo_manager_roskilde' => [
        ['Ferieændring afventer godkendelse', 'bc_staff_absence.review', -25, FALSE],
        ['Ny ferieansøgning', 'bc_staff_absence.review', -130, FALSE],
        ['Ferieansøgning ændret', 'bc_staff_absence.review', -1800, TRUE],
      ],
      'bc_demo_employee_naestved_1' => [
        ['Din ferieansøgning er godkendt', 'bc_staff_absence.list', -45, FALSE],
        ['Din ferieændring er godkendt', 'bc_staff_absence.list', -2800, TRUE],
      ],
      'bc_demo_employee_naestved_2' => [
        ['Din ferieansøgning er afvist', 'bc_staff_absence.list', -85, FALSE],
      ],
      'bc_demo_employee_roskilde_1' => [
        ['Din ferieannullering er godkendt', 'bc_staff_absence.list', -115, FALSE],
      ],
      'bc_demo_employee_roskilde_2' => [
        ['Din ferieændring er afvist', 'bc_staff_absence.list', -70, FALSE],
        ['Din ferieansøgning er godkendt', 'bc_staff_absence.list', -2200, TRUE],
      ],
    ];

    $now = \Drupal::time()->getCurrentTime();
    $created = 0;
    $transaction = $db->startTransaction();
    try {
      foreach ($scenarios as $username => $notices) {
        $matches = $storage->loadByProperties(['name' => $username]);
        $account = reset($matches);
        if (!$account || !$account->hasField('field_bc_staff_demo')
          || !(bool) $account->get('field_bc_staff_demo')->value
          || !str_starts_with($account->getAccountName(), 'bc_demo_')) {
          continue;
        }
        $uid = (int) $account->id();
        $db->delete('bc_staff_notification')->condition('uid', $uid)->execute();
        foreach ($notices as [$message, $target, $minutes, $read]) {
          $created_at = $now + $minutes * 60;
          $db->insert('bc_staff_notification')->fields([
            'uid' => $uid,
            'message' => $message,
            'target' => $target,
            'created' => $created_at,
            'read_at' => $read ? $created_at + 600 : NULL,
          ])->execute();
          $created++;
        }
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    unset($transaction);
    return $created;
  }
}
