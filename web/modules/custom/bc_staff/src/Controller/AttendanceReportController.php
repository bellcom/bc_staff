<?php

namespace Drupal\bc_staff\Controller;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Department-scoped daily and period attendance report.
 */
final class AttendanceReportController extends ControllerBase {

  public function listing(): array {
    $viewer = $this->currentUser();
    $global = (int) $viewer->id() === 1 || $viewer->hasPermission('administer bc staff employees');
    $storage = $this->entityTypeManager()->getStorage('user');
    $manager = $storage->load($viewer->id());
    $department = EmployeeAccess::departmentId($manager);
    if (!$global && (!$viewer->hasPermission('manage own bc staff department') || !$department)) {
      throw new AccessDeniedHttpException();
    }
    $request = \Drupal::request();
    $today = new \DateTimeImmutable('today');
    $start = $this->parseDate($request->query->get('from')) ?? $today;
    $end = $this->parseDate($request->query->get('to')) ?? $start;
    if ($end < $start || $start->diff($end)->days > 90) {
      return ['#markup' => $this->t('Select a valid period of up to 90 days.')];
    }
    $names = [];
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('uid', 0, '>')
      ->condition('status', 1)->execute();
    foreach ($storage->loadMultiple($ids) as $employee) {
      if (!$employee->hasField('field_bc_staff_status') || (int) $employee->id() === 1
        || $employee->hasRole('administrator')) { continue; }
      if (!$global && EmployeeAccess::departmentId($employee) !== $department) { continue; }
      $names[(int) $employee->id()] = $employee->hasField('field_bc_staff_display_name')
        && !$employee->get('field_bc_staff_display_name')->isEmpty()
        ? $employee->get('field_bc_staff_display_name')->value : $employee->getDisplayName();
    }
    $tz = new \DateTimeZone(date_default_timezone_get());
    $from = $start->setTimezone($tz)->getTimestamp();
    $to = $end->modify('+1 day')->setTimezone($tz)->getTimestamp();
    $db = \Drupal::database();
    $counts = [];
    $entries = [];
    if ($names && $db->schema()->tableExists('bc_staff_attendance')) {
      $query = $db->select('bc_staff_attendance', 'a');
      $query->fields('a', ['uid', 'action', 'created', 'source', 'actor_uid'])
        ->condition('uid', array_keys($names), 'IN')
        ->condition('created', $from, '>=')
        ->condition('created', $to, '<')
        ->orderBy('created', 'ASC')->orderBy('id', 'ASC');
      foreach ($query->execute() as $event) {
        $uid = (int) $event->uid;
        $date = (new \DateTimeImmutable('@' . $event->created))->setTimezone($tz)->format('Y-m-d');
        if (!isset($counts[$uid][$date])) { $counts[$uid][$date] = ['in' => 0, 'out' => 0, 'first' => '', 'last' => '']; }
        $counts[$uid][$date][$event->action]++;
        $time = (new \DateTimeImmutable('@' . $event->created))->setTimezone($tz)->format('H:i');
        if ($event->action === 'in' && $counts[$uid][$date]['first'] === '') { $counts[$uid][$date]['first'] = $time; }
        if ($event->action === 'out') { $counts[$uid][$date]['last'] = $time; }
      }
    }
    foreach ($counts as $uid => $days) {
      foreach ($days as $date => $summary) {
        $entries[] = ['name' => $names[$uid], 'date' => $date] + $summary;
      }
    }
    usort($entries, static fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strnatcasecmp($a['name'], $b['name']));
    return [
      '#theme' => 'bc_staff_attendance_report',
      '#entries' => $entries,
      '#from' => $start->format('Y-m-d'),
      '#to' => $end->format('Y-m-d'),
      '#employees' => count($names),
      '#events' => array_sum(array_map(static fn ($days) => array_sum(array_map(static fn ($v) => $v['in'] + $v['out'], $days)), $counts)),
      '#attached' => ['library' => ['bc_staff/attendance_history']],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'url.query_args'], 'max-age' => 0],
    ];
  }

  private function parseDate(mixed $value): ?\DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return NULL; }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : NULL;
  }

}
