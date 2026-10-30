<?php

namespace Drupal\bc_staff\Controller;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Provides the BC Staff organization administration overview.
 */
final class OrganizationController extends ControllerBase {

  /**
   * Displays manageable organization entities as cards.
   */
  public function overview(): array {
    $groups = [];
    $vocabularies = [
      'bc_staff_department' => $this->t('Departments'),
      'bc_staff_house' => $this->t('Houses'),
      'bc_staff_location' => $this->t('Locations and rooms'),
      'bc_staff_workgroup' => $this->t('Workgroups'),
    ];
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $viewer = $this->currentUser();
    $is_manager = $viewer->id() !== '1'
      && !$viewer->hasPermission('administer bc staff employees');
    $manager = $is_manager ? $this->entityTypeManager()->getStorage('user')->load($viewer->id()) : NULL;
    $manager_department = $is_manager ? EmployeeAccess::departmentId($manager) : NULL;
    // Count employees by department using the same eligible accounts as the
    // employee directory, without exposing privileged Drupal accounts.
    $department_counts = [];
    $user_storage = $this->entityTypeManager()->getStorage('user');
    $user_ids = $user_storage->getQuery()
      ->accessCheck(FALSE)->condition('uid', 0, '>')
      ->exists('field_bc_staff_department')
      ->exists('field_bc_staff_status')
      ->execute();
    foreach ($user_storage->loadMultiple($user_ids) as $user) {
      if ((int) $user->id() === 1 || $user->hasRole('administrator')) {
        continue;
      }
      $department_id = (int) ($user->get('field_bc_staff_department')->target_id ?? 0);
      if ($department_id) {
        $department_counts[$department_id] = ($department_counts[$department_id] ?? 0) + 1;
      }
    }
    foreach ($vocabularies as $vid => $label) {
      if ($is_manager && $vid !== 'bc_staff_location') {
        continue;
      }
      if (!$this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid)) {
        continue;
      }
      $entries = [];
      foreach ($storage->loadTree($vid) as $term) {
        if ($is_manager) {
          $entity = $storage->load($term->tid);
          if (!$entity || !$entity->hasField('field_bc_staff_department')
            || (string) ($entity->get('field_bc_staff_department')->target_id ?? '') !== $manager_department) {
            continue;
          }
        }
        $entries[] = [
          'name' => $term->name,
          'depth' => (int) $term->depth,
          'employee_count' => $vid === 'bc_staff_department' ? ($department_counts[(int) $term->tid] ?? 0) : NULL,
          'employee_url' => $vid === 'bc_staff_department' ? Url::fromRoute('bc_staff.employee_list', [], ['query' => ['department' => $term->name]])->toString() : NULL,
          'edit_url' => Url::fromRoute('bc_staff.organization_edit', [
            'vocabulary' => $vid,
            'term' => $term->tid,
          ])->toString(),
        ];
      }
      $groups[] = [
        'label' => $label,
        'count' => count($entries),
        'entries' => $entries,
        'add_url' => Url::fromRoute('bc_staff.organization_add', [
          'vocabulary' => $vid,
        ])->toString(),
      ];
    }
    return [
      '#theme' => 'bc_staff_organization',
      '#groups' => $groups,
      '#attached' => ['library' => ['bc_staff/organization']],
      '#cache' => ['max-age' => 0],
    ];
  }

}
