<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * BC Staff employee administration.
 */
final class EmployeeAdminController extends ControllerBase {

  public function listing(): array {
    $rows = [];
    $ids = $this->entityTypeManager()->getStorage('user')->getQuery()
      ->accessCheck(FALSE)->condition('uid', 0, '>')->sort('name')->execute();
    foreach (User::loadMultiple($ids) as $user) {
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
      $rows[] = [
        'data' => [
          $name, $user->getAccountName(), $department,
          $user->isActive() ? $this->t('Aktiv') : $this->t('Blokeret'),
          ['data' => ['#type' => 'link', '#title' => $this->t('Rediger'), '#url' => Url::fromRoute('bc_staff.employee_edit', ['user' => $user->id()])]],
        ],
      ];
    }
    return [
      'description' => ['#markup' => '<p>Medarbejdere anvender eksisterende Drupal-brugerkonti. Login og medarbejderoplysninger redigeres samlet.</p>'],
      'add' => ['#type' => 'link', '#title' => $this->t('Opret medarbejder'), '#url' => Url::fromRoute('bc_staff.employee_add'), '#attributes' => ['class' => ['button', 'button--primary']]],
      'table' => ['#type' => 'table', '#header' => [$this->t('Medarbejder'), $this->t('Login'), $this->t('Afdeling'), $this->t('Konto'), $this->t('Handling')], '#rows' => $rows, '#empty' => $this->t('Ingen medarbejdere.')],
      '#cache' => ['max-age' => 0],
    ];
  }

}
