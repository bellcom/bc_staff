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
    foreach ($vocabularies as $vid => $label) {
      if (!$this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid)) {
        continue;
      }
      $entries = [];
      foreach ($storage->loadTree($vid) as $term) {
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
