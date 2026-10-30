<?php

namespace Drupal\bc_staff\Controller;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
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
    $rows = [];
    foreach ($today_events as $event) {
      $rows[] = [
        $names[$event->uid] ?? $this->t('Unknown'),
        $event->action === 'in' ? $this->t('Check in') : $this->t('Check out'),
        \Drupal::service('date.formatter')->format((int) $event->created, 'custom', 'd-m-Y H:i'),
        $actors[$event->actor_uid]?->getDisplayName() ?? $this->t('Unknown'),
        $event->source === 'team_touch' ? $this->t('Team terminal') : $this->t('Personal terminal'),
      ];
    }

    $today_rows = [];
    foreach ($names as $uid => $name) {
      $event = $latest[$uid] ?? NULL;
      $today_rows[] = [
        $name,
        $event ? ($event->action === 'in' ? $this->t('Checked in') : $this->t('Checked out')) : $this->t('No registration today'),
        $event ? \Drupal::service('date.formatter')->format((int) $event->created, 'custom', 'H:i') : '—',
      ];
    }
    usort($today_rows, static fn ($a, $b) => strnatcasecmp((string) $a[0], (string) $b[0]));
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bc-attendance-history']],
      'heading' => ['#type' => 'html_tag', '#tag' => 'h1', '#value' => $this->t('Attendance and history')],
      'today_heading' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Today')],
      'today' => [
        '#type' => 'table',
        '#header' => [$this->t('Employee'), $this->t('Last action today'), $this->t('Time')],
        '#rows' => $today_rows,
        '#empty' => $this->t('No employees.'),
      ],
      'events_heading' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Recent registrations')],
      'events' => [
        '#type' => 'table',
        '#header' => [$this->t('Employee'), $this->t('Action'), $this->t('Time'), $this->t('Registered by'), $this->t('Source')],
        '#rows' => $rows,
        '#empty' => $this->t('No attendance registrations yet.'),
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

}
