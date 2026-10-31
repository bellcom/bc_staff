<?php

namespace Drupal\bc_staff_demo\Service;

/**
 * Rebuilds fictional attendance and absence workflows for demonstration.
 */
final class DemoScenarioRefresh {

  /**
   * Resets scenarios belonging exclusively to marked demo accounts.
   *
   * @return array<string, int>
   *   Counts of generated fixtures.
   */
  public static function refresh(): array {
    $db = \Drupal::database();
    $users = \Drupal::entityTypeManager()->getStorage('user');
    $accounts = [];
    $ids = $users->getQuery()->accessCheck(FALSE)->condition('uid', 0, '>')->execute();
    foreach ($users->loadMultiple($ids) as $user) {
      if ($user->hasField('field_bc_staff_demo')
        && (bool) $user->get('field_bc_staff_demo')->value
        && str_starts_with($user->getAccountName(), 'bc_demo_')) {
        $accounts[$user->getAccountName()] = $user;
      }
    }
    if (!$accounts || !\Drupal::moduleHandler()->moduleExists('bc_staff_absence')
      || !$db->schema()->tableExists('bc_staff_absence')
      || !$db->schema()->tableExists('bc_staff_attendance')
      || !$db->schema()->tableExists('bc_staff_absence_change')
      || !$db->schema()->tableExists('bc_staff_absence_audit')) {
      return ['users' => count($accounts), 'absences' => 0, 'changes' => 0, 'attendance' => 0];
    }

    $uids = array_map(static fn ($user) => (int) $user->id(), $accounts);
    $transaction = $db->startTransaction();
    $absence_ids = $db->select('bc_staff_absence', 'a')->fields('a', ['id'])
      ->condition('uid', $uids, 'IN')->execute()->fetchCol();
    if ($absence_ids) {
      foreach (['bc_staff_absence_change', 'bc_staff_absence_audit'] as $table) {
        $db->delete($table)->condition('absence_id', $absence_ids, 'IN')->execute();
      }
    }
    // Remove orphan change requests for marked demo owners as well.
    $db->delete('bc_staff_absence_change')->condition('uid', $uids, 'IN')->execute();
    $db->delete('bc_staff_absence')->condition('uid', $uids, 'IN')->execute();
    if ($db->schema()->tableExists('bc_staff_attendance_correction')) {
      $db->delete('bc_staff_attendance_correction')->condition('uid', $uids, 'IN')->execute();
    }
    $db->delete('bc_staff_attendance')->condition('uid', $uids, 'IN')->execute();

    $today = new \DateTimeImmutable('today');
    $date = static fn (int $offset): string => $today->modify(sprintf('%+d days', $offset))->format('Y-m-d');
    $now = \Drupal::time()->getCurrentTime();
    $fixtures = [
      ['manager_naestved', 'course', -2, 0, 'registered'],
      ['manager_roskilde', 'day_off', 4, 4, 'registered'],
      ['employee_naestved_1', 'vacation', -1, 3, 'approved'],
      ['employee_naestved_2', 'vacation', 5, 9, 'pending'],
      ['employee_naestved_3', 'vacation', -14, -10, 'rejected'],
      ['employee_naestved_1', 'sick', -17, -15, 'registered'],
      ['employee_naestved_2', 'child_sick', -9, -9, 'registered'],
      ['employee_naestved_3', 'course', 6, 7, 'registered'],
      ['employee_roskilde_1', 'vacation', 6, 10, 'approved'],
      ['employee_roskilde_2', 'vacation', -2, 1, 'approved'],
      ['employee_roskilde_3', 'vacation', -12, -8, 'rejected'],
      ['employee_roskilde_1', 'day_off', 15, 15, 'registered'],
      ['employee_roskilde_2', 'leave', 12, 14, 'registered'],
      ['employee_roskilde_3', 'sick', -5, -3, 'registered'],
    ];
    $absences = [];
    $count_absences = 0;
    foreach ($fixtures as [$suffix, $type, $from, $to, $state]) {
      $name = 'bc_demo_' . $suffix;
      if (!isset($accounts[$name])) { continue; }
      $id = (int) $db->insert('bc_staff_absence')->fields([
        'uid' => (int) $accounts[$name]->id(), 'type' => $type,
        'start_date' => $date($from), 'end_date' => $date($to),
        'state' => $state, 'created' => $now - 86400 * 5, 'changed' => $now - 86400,
      ])->execute();
      $absences[$suffix . ':' . $type] = $id;
      $count_absences++;
    }

    $change_fixtures = [
      ['employee_naestved_1', 'dates', 'pending', -1, 4, 'manager_naestved'],
      ['employee_roskilde_1', 'cancel', 'pending', NULL, NULL, 'manager_roskilde'],
      ['employee_roskilde_2', 'dates', 'rejected', 1, 3, 'manager_roskilde'],
    ];
    $count_changes = 0;
    foreach ($change_fixtures as [$suffix, $kind, $state, $from, $to, $manager_suffix]) {
      $name = 'bc_demo_' . $suffix;
      $manager_name = 'bc_demo_' . $manager_suffix;
      $absence_id = $absences[$suffix . ':vacation'] ?? NULL;
      if (!$absence_id || !isset($accounts[$manager_name])) { continue; }
      $manager_uid = (int) $accounts[$manager_name]->id();
      $requester_uid = (int) $accounts[$name]->id();
      $db->insert('bc_staff_absence_change')->fields([
        'absence_id' => $absence_id, 'uid' => $requester_uid,
        'kind' => $kind, 'start_date' => $from === NULL ? NULL : $date($from),
        'end_date' => $to === NULL ? NULL : $date($to),
        'state' => $state, 'created' => $now - 7200,
        'changed' => $now - ($state === 'pending' ? 7200 : 3600),
        'reviewed_by' => $state === 'pending' ? NULL : $manager_uid,
      ])->execute();
      foreach ([
        ['change_requested', $requester_uid, $now - 7200],
        ...($state === 'pending' ? [] : [[$kind . '_' . $state, $manager_uid, $now - 3600]]),
      ] as [$action, $actor, $time]) {
        $db->insert('bc_staff_absence_audit')->fields([
          'absence_id' => $absence_id, 'actor_uid' => $actor,
          'action' => $action, 'details' => '{}', 'created' => $time,
        ])->execute();
      }
      $count_changes++;
    }

    $attendance_fixtures = [
      ['employee_naestved_1', -3, 8, 0, 'in'],
      ['employee_naestved_1', -3, 16, 0, 'out'],
      ['employee_naestved_1', -2, 8, 5, 'in'],
      ['employee_naestved_1', -2, 16, 10, 'out'],
      ['employee_naestved_2', -1, 8, 10, 'in'],
      ['employee_naestved_2', -1, 15, 35, 'out'],
      ['employee_naestved_3', -1, 8, 15, 'in'],
      ['employee_roskilde_1', -1, 8, 0, 'in'],
      ['employee_roskilde_1', -1, 16, 0, 'out'],
      ['employee_roskilde_2', -3, 8, 30, 'in'],
      ['employee_roskilde_2', -3, 16, 0, 'out'],
      ['employee_roskilde_2', -1, 8, 30, 'in'],
      ['employee_roskilde_3', 0, 9, 10, 'in'],
      ['employee_roskilde_3', 0, 11, 0, 'out'],
    ];
    $count_attendance = 0;
    $latest_action = [];
    foreach ($attendance_fixtures as [$suffix, $offset, $hour, $minute, $action]) {
      $name = 'bc_demo_' . $suffix;
      if (!isset($accounts[$name])) { continue; }
      $uid = (int) $accounts[$name]->id();
      $timestamp = $today->modify(sprintf('%+d days', $offset))
        ->setTime($hour, $minute)->getTimestamp();
      $db->insert('bc_staff_attendance')->fields([
        'uid' => $uid, 'actor_uid' => $uid, 'action' => $action,
        'created' => $timestamp, 'previous_status' => $action === 'in' ? 'absent' : 'present',
        'source' => 'self_touch',
      ])->execute();
      $latest_action[$uid] = $action;
      $count_attendance++;
    }
    foreach ($latest_action as $uid => $action) {
      $user = $users->load($uid);
      $user->set('field_bc_staff_status', $action === 'in' ? 'present' : 'absent');
      $user->save();
    }
    unset($transaction);
    return ['users' => count($accounts), 'absences' => $count_absences,
      'changes' => $count_changes, 'attendance' => $count_attendance];
  }

}
