<?php

namespace Drupal\bc_staff_absence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff_absence\Access\AbsenceApprovalAccess;
use Drupal\bc_staff_absence\Form\AbsenceDecisionForm;
use Drupal\bc_staff_absence\Form\ApprovedVacationDecisionForm;
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
    $pending_changes = [];
    if ($this->staffDb->schema()->tableExists('bc_staff_absence_change')) {
      $changes = $this->staffDb->select('bc_staff_absence_change', 'c')->fields('c', ['absence_id'])
        ->condition('uid', (int) $this->currentUser()->id())->condition('state', 'pending')->execute();
      foreach ($changes as $change) { $pending_changes[(int) $change->absence_id] = TRUE; }
    }
    $audit = [];
    if ($this->staffDb->schema()->tableExists('bc_staff_absence_audit')) {
      $entries = $this->staffDb->select('bc_staff_absence_audit', 'h')->fields('h')
        ->orderBy('created', 'DESC')->orderBy('id', 'DESC')->execute();
      foreach ($entries as $entry) {
        $audit[(int) $entry->absence_id][] = $entry;
      }
    }
    $items = [];
    foreach ($records as $record) {
      $items[] = [
        'type' => $this->absenceLabel($record->type),
        'from' => $record->start_date,
        'to' => $record->end_date,
        'status' => $this->statusLabel($record->state),
        'status_key' => $record->state,
        'history' => $this->auditRows($audit[(int) $record->id] ?? []),
        'change_url' => $record->type === 'vacation' && $record->state === 'approved'
          && !isset($pending_changes[(int) $record->id])
          ? Url::fromRoute('bc_staff_absence.approved_change', ['absence' => $record->id])->toString() : '',
        'change_pending' => isset($pending_changes[(int) $record->id]),
        'edit_url' => $record->state === 'pending' && $record->type === 'vacation' ? Url::fromRoute('bc_staff_absence.edit', ['absence' => $record->id])->toString() : '',
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
    if ($this->staffDb->schema()->tableExists('bc_staff_absence_change')) {
      $changes = $this->staffDb->select('bc_staff_absence_change', 'c')->fields('c')
        ->condition('state', 'pending')->orderBy('created', 'ASC')->execute();
      foreach ($changes as $change) {
        $absence = $this->staffDb->select('bc_staff_absence', 'a')->fields('a')
          ->condition('id', $change->absence_id)->condition('state', 'approved')
          ->condition('type', 'vacation')->execute()->fetchObject();
        $employee = $absence ? $this->staffEntities->getStorage('user')->load($absence->uid) : NULL;
        if (!$employee || (int) $change->uid !== (int) $absence->uid
          || !AbsenceApprovalAccess::canReview($account, $manager, $employee)) {
          continue;
        }
        $items[] = [
          'employee' => $employee->getDisplayName(),
          'type' => $change->kind === 'cancel' ? $this->t('Cancel approved vacation') : $this->t('Change vacation dates'),
          'from' => $change->kind === 'dates' ? $change->start_date : $absence->start_date,
          'to' => $change->kind === 'dates' ? $change->end_date : $absence->end_date,
          'status' => $this->t('Pending'),
          'status_key' => 'pending',
          'form' => $this->staffFormBuilder->getForm(ApprovedVacationDecisionForm::class, (int) $change->id),
        ];
      }
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
   * Formats a concise audit trail for the owner of an absence.
   */
  private function auditRows(array $records): array {
    $rows = [];
    foreach ($records as $record) {
      $actor = $this->staffEntities->getStorage('user')->load($record->actor_uid);
      $label = match ($record->action) {
        'vacation_approved' => $this->t('Approved'),
        'vacation_rejected' => $this->t('Rejected'),
        'change_requested' => $this->t('Change requested'),
        'dates_approved' => $this->t('Date change approved'),
        'dates_rejected' => $this->t('Date change rejected'),
        'cancel_approved' => $this->t('Cancellation approved'),
        'cancel_rejected' => $this->t('Cancellation rejected'),
        default => $this->t('Updated'),
      };
      $rows[] = [
        'label' => (string) $label,
        'actor' => $actor?->getDisplayName() ?? (string) $this->t('Unknown'),
        'time' => \Drupal::service('date.formatter')->format((int) $record->created, 'custom', 'd-m-Y H:i'),
      ];
    }
    return $rows;
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
