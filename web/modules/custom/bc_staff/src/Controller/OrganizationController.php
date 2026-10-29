<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Provides a central BC Staff organization administration overview.
 */
final class OrganizationController extends ControllerBase {

  /**
   * Lists organizational taxonomies for administrators.
   */
  public function overview(): array {
    $items = [];
    foreach ([
      'bc_staff_department' => $this->t('Departments'),
      'bc_staff_house' => $this->t('Houses'),
      'bc_staff_location' => $this->t('Locations and rooms'),
      'bc_staff_workgroup' => $this->t('Workgroups'),
    ] as $vid => $label) {
      $vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vid);
      if (!$vocabulary) {
        continue;
      }
      $terms = $this->entityTypeManager()->getStorage('taxonomy_term')
        ->loadTree($vid);
      $details = [];
      foreach ($terms as $term) {
        $details[] = str_repeat('— ', (int) $term->depth) . $term->name;
      }
      $items[] = [
        'data' => [
          $label,
          $details ? implode(', ', $details) : $this->t('No entries yet'),
          [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Add entry'),
              '#url' => Url::fromRoute('bc_staff.organization_add', ['vocabulary' => $vid]),
            ],
          ],
        ],
      ];
    }
    return [
      'intro' => [
        '#markup' => '<p>' . $this->t('Manage departments, houses, locations and workgroups in one place.') . '</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Area'), $this->t('Current entries'), $this->t('Action')],
        '#rows' => $items,
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
