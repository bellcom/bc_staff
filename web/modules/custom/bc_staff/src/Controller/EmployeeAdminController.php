<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff\Utility\StaffPhoto;

/**
 * BC Staff employee administration.
 */
final class EmployeeAdminController extends ControllerBase {

  /**
   * Handles listing.
   */
  public function listing(): array {
    $employees = [];
    $fallback = base_path() . $this->moduleHandler()->getModule('bc_staff')->getPath() . '/assets/avatar.svg';
    $departments = [];
    $storage = $this->entityTypeManager()->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)->condition('uid', 0, '>')->sort('name')->execute();
    $manager = $this->currentUser()->hasPermission('manage own bc staff department')
      && !$this->currentUser()->hasPermission('administer bc staff employees');
    $leader = $manager ? $storage->load($this->currentUser()->id()) : NULL;
    foreach ($storage->loadMultiple($ids) as $user) {
      if ($manager && !EmployeeAccess::canManage($leader, $user)) {
        continue;
      }
      if (!$manager && ((int) $user->id() === 1 || $user->hasRole('administrator'))) {
        continue;
      }
      if (!$user->hasField('field_bc_staff_status')) {
        continue;
      }
      $name = $user->hasField('field_bc_staff_display_name') && !$user->get('field_bc_staff_display_name')->isEmpty()
        ? $user->get('field_bc_staff_display_name')->value : $user->getAccountName();
      $department = '—';
      if ($user->hasField('field_bc_staff_department') && !$user->get('field_bc_staff_department')->isEmpty()) {
        $term = $user->get('field_bc_staff_department')->entity;
        $department = $term ? $term->label() : '—';
      }
      $house = '';
      if ($user->hasField('field_bc_staff_house') && !$user->get('field_bc_staff_house')->isEmpty()) {
        $house = $user->get('field_bc_staff_house')->entity?->label() ?? '';
      }
      $groups = [];
      if ($user->hasField('field_bc_staff_workgroups')) {
        foreach ($user->get('field_bc_staff_workgroups')->referencedEntities() as $group) {
          $groups[] = $group->label();
        }
      }
      $departments[$department] = $department;
      $employees[] = [
        'photo_url' => StaffPhoto::url($user, \Drupal::service('file_url_generator'), $fallback),
        'name' => $name,
        'login' => $user->getAccountName(),
        'department' => $department,
        'house' => $house,
        'groups' => $groups,
        'active' => $user->isActive(),
        'edit_url' => Url::fromRoute('bc_staff.employee_edit', [
          'user' => $user->id(),
        ])->toString(),
      ];
    }
    natcasesort($departments);
    return [
      '#theme' => 'bc_staff_employee_admin',
      '#employees' => $employees,
      '#departments' => array_values($departments),
      '#add_url' => Url::fromRoute('bc_staff.employee_add')->toString(),
      '#attached' => ['library' => ['bc_staff/employee_admin']],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

}
