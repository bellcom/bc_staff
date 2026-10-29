<?php

namespace Drupal\bc_staff\Controller;

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
    $manager_department = $is_manager ? \Drupal\bc_staff\Access\EmployeeAccess::departmentId($manager) : NULL;
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
