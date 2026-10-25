<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Displays the BC Staff overview.
 */
final class StaffController extends ControllerBase {

  public function overview(): array {
    $rows = [];
    $module_path = \Drupal::service('extension.list.module')->getPath('bc_staff_demo');
    $demo_enabled = \Drupal::moduleHandler()->moduleExists('bc_staff_demo');
    $avatar = $demo_enabled ? base_path() . $module_path . '/assets/avatar.svg' : '';
    $ids = $this->entityTypeManager()->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->sort('name')
      ->range(0, 500)
      ->execute();

    foreach (User::loadMultiple($ids) as $account) {
      $is_demo = $demo_enabled && $account->hasField('field_bc_staff_demo')
        && (bool) $account->get('field_bc_staff_demo')->value;
      if (!$account->isActive() && !$is_demo) {
        continue;
      }
      if (!$account->hasField('field_bc_staff_status') || !$account->hasField('field_bc_staff_location')) {
        continue;
      }
      $status_field = $account->get('field_bc_staff_status');
      $value = $status_field->value;
      $choices = $status_field->getFieldDefinition()->getSetting('allowed_values');
      $status = $choices[$value] ?? 'Ikke angivet';
      $name = $is_demo && $account->hasField('field_bc_staff_display_name')
        ? ($account->get('field_bc_staff_display_name')->value ?: $account->getDisplayName())
        : $account->getDisplayName();
      $department = '—';
      if ($account->hasField('field_bc_staff_department') && !$account->get('field_bc_staff_department')->isEmpty()) {
        $term = $account->get('field_bc_staff_department')->entity;
        $department = $term ? $term->label() : '—';
      }
      $photo = $is_demo ? [
        'data' => [
          '#theme' => 'image',
          '#uri' => $avatar,
          '#alt' => $this->t('Standardavatar'),
          '#width' => 48,
          '#height' => 48,
        ],
      ] : '—';
      $rows[] = [
        'data' => [
          $photo,
          $name,
          $department,
          $status,
          $account->get('field_bc_staff_location')->value ?: '—',
        ],
      ];
    }

    $build = [
      'intro' => ['#markup' => '<p>BC Staff – medarbejderoverblik. Demo-profiler er udelukkende testdata.</p>'],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Foto'), $this->t('Medarbejder'), $this->t('Afdeling'),
          $this->t('Status'), $this->t('Lokation'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Ingen medarbejdere fundet.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
    if ($this->currentUser()->hasPermission('update own bc staff status')) {
      $build['my_status'] = [
        '#type' => 'link',
        '#title' => $this->t('Rediger min status'),
        '#url' => Url::fromRoute('bc_staff.my_status'),
      ];
    }
    if ($demo_enabled && $this->currentUser()->hasPermission('administer users')) {
      $build['demo'] = [
        '#type' => 'link',
        '#title' => $this->t('Vedligehold testdata'),
        '#url' => Url::fromRoute('bc_staff_demo.admin'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }
    return $build;
  }

}
