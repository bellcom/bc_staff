<?php

namespace Drupal\bc_staff_demo\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Creates repeatable, fictional, active demo users and absence scenarios.
 *
 * These users have random, unknown passwords. No credentials are published.
 */
final class DemoAbsenceSeeder {

  /**
   * Constructs the demo absence seeder.
   */
  public function __construct(
    private readonly Connection $staffDb,
    private readonly EntityTypeManagerInterface $staffEntities,
  ) {}

  /**
   * Builds realistic examples across two distinct staff departments.
   *
   * @param array $departments
   *   Department name to taxonomy term ID mapping.
   *
   * @return array
   *   Counts of created accounts, created absences and skipped accounts.
   */
  public function seed(array $departments): array {
    $users = $this->staffEntities->getStorage('user');
    $people = [
      [
        'bc_demo_manager_naestved',
        'Katrine Brandt',
        'Næstved – Administration',
        'bc_staff_manager',
        'present',
        'Office 2',
      ],
      [
        'bc_demo_manager_roskilde',
        'Thomas Vestergaard',
        'Roskilde – Administration',
        'bc_staff_manager',
        'present',
        'Office 3',
      ],
      [
        'bc_demo_employee_naestved_1',
        'Laura Birk',
        'Næstved – Administration',
        'bc_staff_employee',
        'present',
        'Reception',
      ],
      [
        'bc_demo_employee_naestved_2',
        'Emil Dahl',
        'Næstved – Administration',
        'bc_staff_employee',
        'remote',
        'Home',
      ],
      [
        'bc_demo_employee_naestved_3',
        'Ida Nørgaard',
        'Næstved – Administration',
        'bc_staff_employee',
        'meeting',
        'Meeting room 1',
      ],
      [
        'bc_demo_employee_roskilde_1',
        'Malthe Bæk',
        'Roskilde – Administration',
        'bc_staff_employee',
        'present',
        'Reception',
      ],
      [
        'bc_demo_employee_roskilde_2',
        'Alma Friis',
        'Roskilde – Administration',
        'bc_staff_employee',
        'away',
        'Out of office',
      ],
      [
        'bc_demo_employee_roskilde_3',
        'Felix Sønder',
        'Roskilde – Administration',
        'bc_staff_employee',
        'break',
        'Staff room',
      ],
    ];
    $accounts = [];
    $created_users = 0;
    $skipped = 0;

    foreach ($people as [$username, $display_name, $department, $role, $status, $location]) {
      $matches = $users->loadByProperties(['name' => $username]);
      $account = reset($matches);
      if ($account) {
        // Never take over or activate an account not marked as demo.
        if (!$account->hasField('field_bc_staff_demo')
          || !(bool) $account->get('field_bc_staff_demo')->value) {
          $skipped++;
          continue;
        }
      }
      else {
        // Active demo accounts are deliberately created with unguessable passwords.
        // A local administrator must explicitly reset a password to test logins.
        $account = $users->create([
          'name' => $username,
          'mail' => $username . '@example.invalid',
          'pass' => bin2hex(random_bytes(32)),
          'status' => 1,
        ]);
        $created_users++;
      }
      $account->addRole($role);
      $account->set('field_bc_staff_demo', 1);
      $account->set('field_bc_staff_display_name', $display_name);
      $account->set('field_bc_staff_status', $status);
      $account->set('field_bc_staff_location', $location);
      $account->set('field_bc_staff_department', [
        'target_id' => $departments[$department],
      ]);
      $account->save();
      $accounts[$username] = (int) $account->id();
    }

    // Fix the date range to the calendar month. Re-running in the same month
    // must not duplicate or reset review decisions already made by a tester.
    $month = date('Y-m-01');
    $day = static fn (int $offset): string => date('Y-m-d', strtotime($month . sprintf(' %+d days', $offset)));
    $scenarios = [
      ['bc_demo_employee_naestved_1', 'vacation', 4, 8, 'pending'],
      ['bc_demo_employee_naestved_2', 'vacation', 11, 15, 'approved'],
      ['bc_demo_employee_naestved_3', 'vacation', 18, 22, 'rejected'],
      ['bc_demo_employee_roskilde_1', 'vacation', 6, 10, 'pending'],
      ['bc_demo_employee_roskilde_2', 'vacation', 13, 16, 'approved'],
      ['bc_demo_employee_roskilde_3', 'vacation', 20, 23, 'rejected'],
      ['bc_demo_employee_naestved_1', 'sick', 0, 2, 'registered'],
      ['bc_demo_employee_naestved_2', 'child_sick', 3, 3, 'registered'],
      ['bc_demo_employee_naestved_3', 'course', 7, 8, 'registered'],
      ['bc_demo_employee_roskilde_1', 'day_off', 9, 9, 'registered'],
      ['bc_demo_employee_roskilde_2', 'leave', 12, 14, 'registered'],
      ['bc_demo_employee_roskilde_3', 'sick', 16, 18, 'registered'],
    ];

    $created_absences = 0;
    $now = time();
    foreach ($scenarios as [$username, $type, $start, $end, $state]) {
      if (!isset($accounts[$username])) {
        continue;
      }
      $uid = $accounts[$username];
      $start_date = $day($start);
      $end_date = $day($end);
      $existing = $this->staffDb->select('bc_staff_absence', 'a')
        ->fields('a', ['id'])
        ->condition('uid', $uid)
        ->condition('type', $type)
        ->condition('start_date', $start_date)
        ->condition('end_date', $end_date)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($existing) {
        continue;
      }
      $this->staffDb->insert('bc_staff_absence')
        ->fields([
          'uid' => $uid,
          'type' => $type,
          'start_date' => $start_date,
          'end_date' => $end_date,
          'state' => $state,
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
      $created_absences++;
    }
    return [
      'users' => $created_users,
      'absences' => $created_absences,
      'skipped' => $skipped,
    ];
  }

}
