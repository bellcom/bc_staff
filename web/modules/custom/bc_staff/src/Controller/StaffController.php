<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Displays staff presence cards.
 */
final class StaffController extends ControllerBase {

  /**
   * Handles overview.
   */
  public function overview(): array {
    $demo_enabled = \Drupal::moduleHandler()->moduleExists('bc_staff_demo');
    $module_path = \Drupal::service('extension.list.module')->getPath('bc_staff');
    $avatar = base_path() . $module_path . '/assets/avatar.svg';
    $ids = $this->entityTypeManager()->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->sort('name')
      ->range(0, 500)
      ->execute();

    $staff = [];
    $departments = [];
    foreach (User::loadMultiple($ids) as $account) {
      $is_demo = $demo_enabled && $account->hasField('field_bc_staff_demo') && (bool) $account->get('field_bc_staff_demo')->value;
      if (!$account->isActive() && !$is_demo) {
        continue;
      }
      if (!$account->hasField('field_bc_staff_status') || $account->get('field_bc_staff_status')->isEmpty()) {
        continue;
      }
      $status_field = $account->get('field_bc_staff_status');
      $status_key = $status_field->value;
      $choices = $status_field->getFieldDefinition()->getSetting('allowed_values');
      $name = $account->hasField('field_bc_staff_display_name')
        ? ($account->get('field_bc_staff_display_name')->value ?: $account->getDisplayName())
        : $account->getDisplayName();
      $department = '';
      if ($account->hasField('field_bc_staff_department') && !$account->get('field_bc_staff_department')->isEmpty()) {
        $term = $account->get('field_bc_staff_department')->entity;
        $department = $term ? $term->label() : '';
      }
      if ($department !== '') {
        $departments[$department] = $department;
      }
      $staff[] = [
        'name' => $name,
        'is_me' => (int) $account->id() === (int) $this->currentUser()->id(),
        'profile_url' => ((int) $account->id() === (int) $this->currentUser()->id() || $this->currentUser()->hasPermission('administer users')) ? Url::fromRoute('entity.user.canonical', ['user' => $account->id()])->toString() : '',
        'department' => $department,
        'status_key' => $status_key,
        'status' => $choices[$status_key] ?? 'Ikke angivet',
        'location' => $account->get('field_bc_staff_location')->value ?: '',
      ];
    }
    natcasesort($departments);
    return [
      '#theme' => 'bc_staff_dashboard',
      '#staff' => $staff,
      '#departments' => array_values($departments),
      '#avatar' => $avatar,
      '#my_name' => $this->currentUser()->isAuthenticated() ? (function () { $u = User::load($this->currentUser()->id()); return $u && $u->hasField('field_bc_staff_display_name') && !$u->get('field_bc_staff_display_name')->isEmpty() ? $u->get('field_bc_staff_display_name')->value : $this->currentUser()->getDisplayName(); })() : '',
      '#my_profile_url' => $this->currentUser()->isAuthenticated() ? Url::fromRoute('entity.user.canonical', ['user' => $this->currentUser()->id()])->toString() : '',
      '#status_url' => $this->currentUser()->hasPermission('update own bc staff status')
        ? Url::fromRoute('bc_staff.my_status')->toString() : '',
      '#employees_url' => ($this->currentUser()->hasPermission('administer bc staff employees') || $this->currentUser()->hasPermission('manage own bc staff department')) ? Url::fromRoute('bc_staff.employee_list')->toString() : '',
      '#demo_url' => $demo_enabled && $this->currentUser()->hasPermission('administer users')
        ? Url::fromRoute('bc_staff_demo.admin')->toString() : '',
      '#attached' => ['library' => ['bc_staff/dashboard']],
      '#cache' => ['max-age' => 0],
    ];
  }

}
