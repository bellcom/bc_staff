<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;

/**
 * Displays the staff presence overview.
 */
final class StaffController extends ControllerBase {

  public function overview(): array {
    $rows = [];
    $ids = $this->entityTypeManager()->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->sort('name')
      ->range(0, 200)
      ->execute();

    foreach (User::loadMultiple($ids) as $account) {
      if (!$account->hasField('field_bc_staff_status') || !$account->hasField('field_bc_staff_location')) {
        continue;
      }
      $status = $account->get('field_bc_staff_status')->first();
      $status_label = $status ? (string) ($status->getFieldDefinition()->getSetting('allowed_values')[$status->value] ?? 'Ikke angivet') : 'Ikke angivet';
      $rows[] = [
        'data' => [
          $account->getDisplayName(),
          $status_label,
          $account->get('field_bc_staff_location')->value ?: 'Ikke angivet',
        ],
      ];
    }
    return [
      'intro' => ['#markup' => '<p>Medarbejderoverblik – første version af BC Staff.</p>'],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Medarbejder'), $this->t('Status'), $this->t('Lokation')],
        '#rows' => $rows,
        '#empty' => $this->t('Ingen medarbejdere fundet.'),
      ],
      'my_status' => [
        '#type' => 'link',
        '#title' => $this->t('Rediger min status'),
        '#url' => \Drupal\Core\Url::fromRoute('bc_staff.my_status'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
