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
    $corrections = [];
    $total_minutes = 0;
    if ($names && $db->schema()->tableExists('bc_staff_attendance')) {
      if ($db->schema()->tableExists('bc_staff_attendance_correction')) {
        $q = $db->select('bc_staff_attendance_correction', 'c');
        foreach ($q->fields('c', ['event_id', 'corrected_at'])->condition('uid', array_keys($names), 'IN')->orderBy('id')->execute() as $c) {
          $corrections[(int) $c->event_id] = (int) $c->corrected_at;
        }
      }
      $query = $db->select('bc_staff_attendance', 'a');
      $query->fields('a', ['id', 'uid', 'action', 'created', 'source', 'actor_uid'])
        ->condition('uid', array_keys($names), 'IN')
        ->orderBy('created', 'ASC')->orderBy('id', 'ASC');
      foreach ($query->execute() as $event) {
        if (!in_array($event->action, ['in', 'out'], TRUE)) { continue; }
        $event->effective = $corrections[(int) $event->id] ?? (int) $event->created;
        $uid = (int) $event->uid;
        if ($event->effective < $from || $event->effective >= $to) { continue; }
        $date = (new \DateTimeImmutable('@' . $event->effective))->setTimezone($tz)->format('Y-m-d');
        if (!isset($counts[$uid][$date])) { $counts[$uid][$date] = ['in' => 0, 'out' => 0, 'first' => '', 'last' => '', 'records' => []]; }
        $counts[$uid][$date][$event->action]++;
        $counts[$uid][$date]['records'][] = $event;
        $time = (new \DateTimeImmutable('@' . $event->effective))->setTimezone($tz)->format('H:i');
        if ($event->action === 'in' && $counts[$uid][$date]['first'] === '') { $counts[$uid][$date]['first'] = $time; }
        if ($event->action === 'out') { $counts[$uid][$date]['last'] = $time; }
      }
    }
    foreach ($counts as $uid => $days) {
      foreach ($days as $date => $summary) {

        usort($summary['records'], static fn ($a, $b) => $a->effective <=> $b->effective ?: $a->id <=> $b->id);
        $open = NULL;
        $minutes = 0;
        $incomplete = FALSE;
        $links = [];
        foreach ($summary['records'] as $event) {
          $links[] = [
            'url' => \Drupal\Core\Url::fromRoute('bc_staff.attendance_correction', ['attendance' => $event->id])->toString(),
            'label' => $event->action === 'in' ? (string) $this->t('Correct check-in') : (string) $this->t('Correct check-out'),
          ];
          if ($event->action === 'in') {
            if ($open !== NULL) { $incomplete = TRUE; }
            $open = $event->effective;
          }
          elseif ($open !== NULL && $event->effective > $open) {
            $minutes += intdiv($event->effective - $open, 60);
            $open = NULL;
          }
          else { $incomplete = TRUE; }
        }
        if ($open !== NULL) { $incomplete = TRUE; }
        $total_minutes += $minutes;
        $first_in = NULL;
        $last_out = NULL;
        foreach ($summary['records'] as $event) {
          $time = (new \DateTimeImmutable('@' . $event->effective))->setTimezone($tz)->format('H:i');
          if ($event->action === 'in' && $first_in === NULL) { $first_in = $time; }
          if ($event->action === 'out') { $last_out = $time; }
        }
        $summary['first'] = $first_in ?? '';
        $summary['last'] = $last_out ?? '';
        unset($summary['records']);
        $summary['duration'] = sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
        $summary['incomplete'] = $incomplete;
        $summary['correction_links'] = $links;
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
      '#hours' => sprintf('%d:%02d', intdiv($total_minutes, 60), $total_minutes % 60),
      '#events' => array_sum(array_map(static fn ($days) => array_sum(array_map(static fn ($v) => $v['in'] + $v['out'], $days)), $counts)),
      '#attached' => ['library' => ['bc_staff/attendance_history']],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'url.query_args'], 'max-age' => 0],
    ];
  }


  /**
   * Downloads the same scoped report in an Excel-compatible CSV format.
   */
  public function export(): \Symfony\Component\HttpFoundation\StreamedResponse {
    $report = $this->listing();
    if (!isset($report['#entries'])) {
      throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid date range.');
    }
    $rows = $report['#entries'];
    $response = new \Symfony\Component\HttpFoundation\StreamedResponse(static function () use ($rows): void {
      $out = fopen('php://output', 'wb');
      fwrite($out, "\xEF\xBB\xBF");
      fputcsv($out, ['Medarbejder', 'Dato', 'Første indtjekning', 'Sidste udtjekning', 'Ind', 'Ud', 'Registrerede timer', 'Ufuldstændig'], ';');
      foreach ($rows as $row) {
        $name = (string) $row['name'];
        if (preg_match('/^[=+@\-\t\r]/u', $name)) { $name = "'" . $name; }
        fputcsv($out, [$name, $row['date'], $row['first'], $row['last'],
          $row['in'], $row['out'], $row['duration'], $row['incomplete'] ? 'Ja' : 'Nej'], ';');
      }
      fclose($out);
    });
    $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="bc-staff-fremmoede.csv"');
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

  private function parseDate(mixed $value): ?\DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return NULL; }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : NULL;
  }

}
