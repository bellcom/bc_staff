<?php

namespace Drupal\bc_staff_absence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff_absence\Access\AbsenceApprovalAccess;
use Drupal\bc_staff_absence\Form\AbsenceDecisionForm;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Displays absence requests with role-aware access restrictions.
 */
final class AbsenceController extends ControllerBase {

  public function __construct(
    private readonly Connection $staffDb,
    private readonly EntityTypeManagerInterface $staffEntities,
    private readonly FormBuilderInterface $staffFormBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('entity_type.manager'), $container->get('form_builder'));
  }

  /**
   * Lists absences belonging to the current employee.
   */
  public function listing(): array {
    $records = $this->staffDb->select('bc_staff_absence', 'a')
      ->fields('a', ['id', 'type', 'start_date', 'end_date', 'state'])
      ->condition('uid', (int) $this->currentUser()->id())
      ->orderBy('start_date', 'DESC')
      ->execute()->fetchAll();
    $items = [];
    foreach ($records as $record) {
      $items[] = [
        'type' => $this->absenceLabel($record->type),
        'from' => $record->start_date,
        'to' => $record->end_date,
        'status' => $this->statusLabel($record->state),
        'status_key' => $record->state,
        'cancel_url' => $record->state === 'pending' ? Url::fromRoute('bc_staff_absence.cancel', ['absence' => $record->id])->toString() : '',
      ];
    }
    return [
      '#theme' => 'bc_staff_absence_dashboard',
      '#heading' => $this->t('My absence'),
      '#description' => $this->t('Review your registered absence and requests.'),
      '#items' => $items,
      '#review' => FALSE,
      '#add_url' => Url::fromRoute('bc_staff_absence.add')->toString(),
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
  }

  /**
   * Lists pending vacation requests in a manager's own department.
   */
  public function review(): array {
    $account = $this->currentUser();
    $is_admin = $account->id() === '1' || $account->hasPermission('manage all bc staff absences');
    $manager = $this->staffEntities->getStorage('user')->load($account->id());
    $department = EmployeeAccess::departmentId($manager);
    if (!$is_admin && !$department) {
      throw new AccessDeniedHttpException();
    }

    $records = $this->staffDb->select('bc_staff_absence', 'a')
      ->fields('a', ['id', 'uid', 'type', 'start_date', 'end_date'])
      ->condition('state', 'pending')
      ->orderBy('created', 'ASC')
      ->execute()->fetchAll();
    $items = [];
    foreach ($records as $record) {
      $employee = $this->staffEntities->getStorage('user')->load($record->uid);
      if (!$employee || !AbsenceApprovalAccess::canReview($account, $manager, $employee)) {
        continue;
      }
      $items[] = [
        'employee' => $employee->getDisplayName(),
        'type' => $this->absenceLabel($record->type),
        'from' => $record->start_date,
        'to' => $record->end_date,
        'status' => $this->t('Pending'),
        'status_key' => 'pending',
        'form' => $this->staffFormBuilder->getForm(AbsenceDecisionForm::class, $record->id),
      ];
    }
    return [
      '#theme' => 'bc_staff_absence_dashboard',
      '#heading' => $this->t('Absence approvals'),
      '#description' => $this->t('Review pending requests from your team.'),
      '#items' => $items,
      '#review' => TRUE,
      '#add_url' => '',
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

  /**
   * Provides translatable absence type labels.
   */
  private function absenceLabel(string $type): string {
    return (string) match ($type) {
      'vacation' => $this->t('Vacation'),
      'course' => $this->t('Course'),
      'child_sick' => $this->t('Child sick day'),
      'sick' => $this->t('Sickness'),
      'day_off' => $this->t('Day off'),
      'leave' => $this->t('Leave'),
      default => $this->t('Other absence'),
    };
  }

  /**
   * Provides translatable workflow state labels.
   */
  private function statusLabel(string $state): string {
    return (string) match ($state) {
      'pending' => $this->t('Pending'),
      'approved' => $this->t('Approved'),
      'rejected' => $this->t('Rejected'),
      'cancelled' => $this->t('Withdrawn'),
      'registered' => $this->t('Registered'),
      default => $this->t('Unknown'),
    };
  }

}
