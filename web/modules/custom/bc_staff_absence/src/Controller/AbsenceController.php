<?php

namespace Drupal\bc_staff_absence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff_absence\Access\AbsenceApprovalAccess;
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
      ->fields('a', ['type', 'start_date', 'end_date', 'state'])
      ->condition('uid', (int) $this->currentUser()->id())
      ->orderBy('start_date', 'DESC')
      ->execute()->fetchAll();
    $rows = [];
    foreach ($records as $record) {
      $rows[] = [$record->type, $record->start_date, $record->end_date, $record->state];
    }
    return [
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Register absence'),
        '#url' => Url::fromRoute('bc_staff_absence.add'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Type'), $this->t('From'), $this->t('To'), $this->t('Decision')],
        '#rows' => $rows,
        '#empty' => $this->t('No absence records.'),
      ],
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
    $rows = [];
    foreach ($records as $record) {
      $employee = $this->staffEntities->getStorage('user')->load($record->uid);
      if (!$employee || !AbsenceApprovalAccess::canReview($account, $manager, $employee)) {
        continue;
      }
      $rows[] = [
        $employee->getDisplayName(),
        $record->type,
        $record->start_date,
        $record->end_date,
        $this->staffFormBuilder->getForm('Drupal\\bc_staff_absence\\Form\\AbsenceDecisionForm', $record->id),
      ];
    }
    return [
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Employee'), $this->t('Type'), $this->t('From'), $this->t('To'), $this->t('Decision')],
        '#rows' => $rows,
        '#empty' => $this->t('No pending requests.'),
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

}
