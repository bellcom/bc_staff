<?php

namespace Drupal\bc_staff_absence\Controller;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Renders the visual department planning calendar.
 */
final class AbsenceCalendarController extends ControllerBase {

  /**
   * Constructs the calendar controller.
   */
  public function __construct(
    private readonly Connection $staffDb,
    private readonly EntityTypeManagerInterface $staffEntities,
    private readonly RequestStack $staffRequestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * Builds a week or a 28-day planning grid.
   */
  public function calendar(): array {
    $viewer = $this->currentUser();
    $global = $viewer->id() === '1'
      || $viewer->hasPermission('manage all bc staff absences');
    $manager = $this->staffEntities->getStorage('user')->load($viewer->id());
    $department = EmployeeAccess::departmentId($manager);
    if (!$global && (!$viewer->hasPermission('manage own bc staff department') || !$department)) {
      throw new AccessDeniedHttpException();
    }

    $query = $this->staffRequestStack->getCurrentRequest()->query;
    $mode = $query->get('view') === 'month' ? 'month' : 'week';
    $date = $query->get('date');
    $anchor = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
      ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : FALSE;
    $anchor = $anchor ?: new \DateTimeImmutable('today');
    $start = $anchor->modify('monday this week');
    if ($mode === 'month') {
      $start = $anchor->modify('first day of this month')->modify('monday this week');
    }
    $days_count = $mode === 'month' ? 42 : 7;
    $end = $start->modify('+' . ($days_count - 1) . ' days');
    $dates = [];
    for ($i = 0; $i < $days_count; $i++) {
      $day = $start->modify("+$i days");
      $dates[] = [
        'key' => $day->format('Y-m-d'),
        'number' => $day->format('j'),
        'label' => $day->format('D'),
        'weekend' => (int) $day->format('N') >= 6,
        'outside' => $mode === 'month' && $day->format('m') !== $anchor->format('m'),
        'today' => $day->format('Y-m-d') === date('Y-m-d'),
      ];
    }

    $storage = $this->staffEntities->getStorage('user');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('uid', 0, '>')->condition('status', 1)->sort('name')->execute();
    $employees = [];
    foreach ($storage->loadMultiple($ids) as $employee) {
      if (!$employee->hasField('field_bc_staff_department')) {
        continue;
      }
      if (!$global && EmployeeAccess::departmentId($employee) !== $department) {
        continue;
      }
      $employees[$employee->id()] = $employee;
    }

    $absences = [];
    if ($employees) {
      $records = $this->staffDb->select('bc_staff_absence', 'a')
        ->fields('a', ['uid', 'type', 'state', 'start_date', 'end_date'])
        ->condition('uid', array_keys($employees), 'IN')
        ->condition('start_date', $end->format('Y-m-d'), '<=')
        ->condition('end_date', $start->format('Y-m-d'), '>=')
        ->condition('state', ['approved', 'registered', 'pending'], 'IN')
        ->execute();
      foreach ($records as $record) {
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
          $key = $day->format('Y-m-d');
          if ($key >= $record->start_date && $key <= $record->end_date) {
            // Never reveal health-related reasons in the planning grid.
            $category = in_array($record->type, ['sick', 'child_sick'], TRUE)
              ? 'unavailable' : $record->type;
            $label = in_array($record->type, ['sick', 'child_sick'], TRUE)
              ? (string) $this->t('Unavailable')
              : (string) match ($record->type) {
              'vacation' => $this->t('Vacation'),
              'course' => $this->t('Course'),
              'day_off' => $this->t('Day off'),
              'leave' => $this->t('Leave'),
              default => $this->t('Unavailable'),
              };
            $absences[$record->uid][$key] = [
              'category' => $category,
              'label' => $label,
              'pending' => $record->state === 'pending',
            ];
          }
        }
      }
    }

    $rows = [];
    foreach ($employees as $employee) {
      $cells = [];
      $index = 0;
      while ($index < count($dates)) {
        $item = $absences[$employee->id()][$dates[$index]['key']] ?? NULL;
        if (!$item) {
          $cells[] = ['span' => 1, 'event' => NULL];
          $index++;
          continue;
        }
        $first = $index;
        $index++;
        while ($index < count($dates)
          && ($absences[$employee->id()][$dates[$index]['key']] ?? NULL) === $item) {
          $index++;
        }
        $cells[] = [
          'span' => $index - $first,
          'event' => $item,
          'from' => $dates[$first]['key'],
          'to' => $dates[$index - 1]['key'],
        ];
      }
      $rows[] = [
        'name' => $employee->getDisplayName(),
        'cells' => $cells,
      ];
    }
    $shift = $mode === 'month' ? 'month' : 'week';
    $url = static fn (string $date_value, string $view): string =>
      Url::fromRoute('bc_staff_absence.calendar', [], [
        'query' => ['date' => $date_value, 'view' => $view],
      ])->toString();
    return [
      '#theme' => 'bc_staff_absence_calendar',
      '#dates' => $dates,
      '#rows' => $rows,
      '#period' => $mode === 'month' ? $anchor->format('F Y') : $start->format('j M') . ' – ' . $end->format('j M Y'),
      '#mode' => $mode,
      '#today_url' => $url((new \\DateTimeImmutable('today'))->format('Y-m-d'), $mode),
      '#previous' => $url($anchor->modify("-1 $shift")->format('Y-m-d'), $mode),
      '#next' => $url($anchor->modify("+1 $shift")->format('Y-m-d'), $mode),
      '#week_url' => $url($anchor->format('Y-m-d'), 'week'),
      '#month_url' => $url($anchor->format('Y-m-d'), 'month'),
      '#cache' => ['contexts' => ['user', 'user.permissions', 'url.query_args'], 'max-age' => 0],
    ];
  }

}
