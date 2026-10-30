<?php

namespace Drupal\bc_staff\Controller;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff\Utility\StaffPhoto;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Shows department-scoped attendance history and today's overview.
 */
final class AttendanceHistoryController extends ControllerBase {

  public function listing(): array {
    $viewer = $this->currentUser();
    $global = (int) $viewer->id() === 1 || $viewer->hasPermission('administer bc staff employees');
    $users = $this->entityTypeManager()->getStorage('user');
    $manager = $users->load($viewer->id());
    $department = EmployeeAccess::departmentId($manager);
    if (!$global && (!$viewer->hasPermission('manage own bc staff department') || !$department)) {
      throw new AccessDeniedHttpException();
    }

    $employee_ids = [];
    $names = [];
    $current_status = [];
    $photos = [];
    $departments = [];
    $locations = [];
    $ids = $users->getQuery()->accessCheck(FALSE)->condition('uid', 0, '>')
      ->condition('status', 1)->execute();
    foreach ($users->loadMultiple($ids) as $employee) {
      if (!$employee->hasField('field_bc_staff_status') || (int) $employee->id() === 1
        || $employee->hasRole('administrator')) {
        continue;
      }
      if (!$global && EmployeeAccess::departmentId($employee) !== $department) {
        continue;
      }
      $uid = (int) $employee->id();
      $employee_ids[] = $uid;
      $current_status[$uid] = (string) ($employee->get('field_bc_staff_status')->value ?? '');
      $photos[$uid] = StaffPhoto::url($employee, \Drupal::service('file_url_generator'), base_path() . \Drupal::service('extension.list.module')->getPath('bc_staff') . '/assets/avatar.svg');
      $departments[$uid] = $employee->hasField('field_bc_staff_department') && !$employee->get('field_bc_staff_department')->isEmpty() ? ($employee->get('field_bc_staff_department')->entity?->label() ?? '') : '';
      $locations[$uid] = $employee->hasField('field_bc_staff_location') ? (string) ($employee->get('field_bc_staff_location')->value ?? '') : '';
      $names[$uid] = $employee->hasField('field_bc_staff_display_name')
        && !$employee->get('field_bc_staff_display_name')->isEmpty()
        ? (string) $employee->get('field_bc_staff_display_name')->value
        : $employee->getDisplayName();
    }

    $timezone = new \DateTimeZone(date_default_timezone_get());
    $start = (new \DateTimeImmutable('today', $timezone))->getTimestamp();
    $end = (new \DateTimeImmutable('tomorrow', $timezone))->getTimestamp();
    $db = \Drupal::database();
    $latest = [];
    $today_events = [];
    if ($employee_ids && $db->schema()->tableExists('bc_staff_attendance')) {
      $query = $db->select('bc_staff_attendance', 'a');
      $query->fields('a', ['id', 'uid', 'actor_uid', 'action', 'created', 'source'])
        ->condition('uid', $employee_ids, 'IN')
        ->orderBy('created', 'DESC')
        ->orderBy('id', 'DESC')
        ->range(0, 200);
      foreach ($query->execute() as $event) {
        $today_events[] = $event;
        if ($event->created >= $start && $event->created < $end
          && !isset($latest[$event->uid])) {
          $latest[$event->uid] = $event;
        }
      }
    }

    $actor_ids = array_unique(array_map(static fn ($event) => (int) $event->actor_uid, $today_events));
    $actors = $actor_ids ? $users->loadMultiple($actor_ids) : [];
    $events = [];
    foreach ($today_events as $event) {
      $events[] = [
        'name' => $names[$event->uid] ?? (string) $this->t('Unknown'),
        'action' => $event->action,
        'action_label' => (string) ($event->action === 'in' ? $this->t('Check in') : $this->t('Check out')),
        'time' => \Drupal::service('date.formatter')->format((int) $event->created, 'custom', 'd-m-Y H:i'),
        'actor' => ($actors[$event->actor_uid] ?? NULL)?->getDisplayName() ?? (string) $this->t('Unknown'),
        'source' => (string) ($event->source === 'team_touch' ? $this->t('Team terminal') : $this->t('Personal terminal')),
      ];
    }
    $employees = [];
    foreach ($names as $uid => $name) {
      $event = $latest[$uid] ?? NULL;
      $employees[] = [
        'name' => $name,
        'photo' => $photos[$uid],
        'department' => $departments[$uid],
        'location' => $locations[$uid],
        'present' => $current_status[$uid] === 'present',
        'status' => (string) ($current_status[$uid] === 'present' ? $this->t('Present') : $this->t('Not present')),
        'action' => $event?->action ?? '',
        'last_action' => $event ? (string) ($event->action === 'in' ? $this->t('Checked in') : $this->t('Checked out')) : (string) $this->t('No registration today'),
        'last_time' => $event ? \Drupal::service('date.formatter')->format((int) $event->created, 'custom', 'H:i') : '',
      ];
    }
    usort($employees, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return [
      '#theme' => 'bc_staff_attendance_history',
      '#employees' => $employees,
      '#events' => $events,
      '#attached' => ['library' => ['bc_staff/attendance_history']],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

}
