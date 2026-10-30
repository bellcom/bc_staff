<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff\Utility\StaffPhoto;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Department-scoped staff cards with one-tap attendance controls.
 */
final class ManagedTouchForm extends FormBase {

  public function getFormId(): string {
    return 'bc_staff_managed_touch';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $current = $this->currentUser();
    $global = $current->id() === '1' || $current->hasPermission('administer bc staff employees');
    $users = \Drupal::entityTypeManager()->getStorage('user');
    $manager = $users->load($current->id());
    $department = EmployeeAccess::departmentId($manager);
    $ids = $users->getQuery()->accessCheck(FALSE)->condition('uid', 0, '>')
      ->condition('status', 1)->sort('name')->execute();
    $form['#attributes']['class'][] = 'bc-touch-directory';
    $form['#attached']['library'][] = 'bc_staff/employee_admin';
    $form['#attached']['library'][] = 'bc_staff/touch_directory';
    $form['heading'] = [
      '#markup' => '<header class="bc-employees__header"><div><p class="bc-employees__eyebrow">BC STAFF</p><h2>' .
        $this->t('Team check-in terminal') . '</h2><p>' .
        $this->t('Select In or Out on the employee card.') . '</p></div></header>',
    ];
    $form['search'] = [
      '#type' => 'search',
      '#title' => $this->t('Search employees'),
      '#attributes' => ['placeholder' => $this->t('Search employees'), 'data-bc-touch-search' => ''],
    ];
    $form['cards'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bc-employees__grid'], 'data-bc-touch-grid' => ''],
    ];
    $fallback = base_path() . \Drupal::service('extension.list.module')->getPath('bc_staff') . '/assets/avatar.svg';
    $photo_urls = \Drupal::service('file_url_generator');
    $count = 0;
    foreach ($users->loadMultiple($ids) as $employee) {
      if (!$this->canOperate($employee, $global, $department)) {
        continue;
      }
      $uid = (int) $employee->id();
      $name = $employee->hasField('field_bc_staff_display_name')
        && !$employee->get('field_bc_staff_display_name')->isEmpty()
        ? (string) $employee->get('field_bc_staff_display_name')->value
        : $employee->getDisplayName();
      $status = (string) ($employee->get('field_bc_staff_status')->value ?? '');
      $department_name = $employee->hasField('field_bc_staff_department')
        && !$employee->get('field_bc_staff_department')->isEmpty()
        ? ($employee->get('field_bc_staff_department')->entity?->label() ?? '')
        : '';
      $card = &$form['cards']['person_' . $uid];
      $card = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['bc-employees__card', 'bc-touch-directory__card'],
          'data-bc-touch-card' => '',
          'data-bc-touch-name' => mb_strtolower($name . ' ' . $department_name),
        ],
      ];
      $card['top'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bc-person__top']],
      ];
      $card['top']['photo'] = [
        '#theme' => 'image',
        '#uri' => StaffPhoto::url($employee, $photo_urls, $fallback),
        '#alt' => '',
        '#attributes' => ['class' => ['bc-employees__avatar']],
      ];
      $card['top']['name'] = ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $name];
      $card['top']['department'] = ['#type' => 'html_tag', '#tag' => 'p', '#value' => $department_name];
      $card['top']['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $status === 'present' ? $this->t('Present') : $this->t('Not checked in'),
        '#attributes' => ['class' => ['bc-touch-directory__status']],
      ];
      $card['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['bc-touch-directory__actions']],
      ];
      foreach (['in' => $this->t('Ind'), 'out' => $this->t('Ud')] as $action => $label) {
        $card['actions'][$action] = [
          '#type' => 'submit',
          '#value' => $label,
          '#name' => $action . '_' . $uid,
          '#attributes' => ['class' => ['bc-touch-directory__button', 'bc-touch-directory__button--' . $action]],
          '#disabled' => $action === 'in' ? $status === 'present' : $status === 'absent',
        ];
      }
      unset($card);
      $count++;
    }
    if (!$count) {
      $form['empty'] = ['#markup' => '<p>' . $this->t('No employees.') . '</p>'];
    }
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $name = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if (!preg_match('/^(in|out)_([1-9][0-9]*)$/D', $name, $matches)) {
      $form_state->setErrorByName('', $this->t('Invalid action.'));
      return;
    }
    $current = $this->currentUser();
    $global = $current->id() === '1' || $current->hasPermission('administer bc staff employees');
    $users = \Drupal::entityTypeManager()->getStorage('user');
    $manager = $users->load($current->id());
    $employee = $users->load((int) $matches[2]);
    if (!$this->canOperate($employee, $global, EmployeeAccess::departmentId($manager))) {
      $form_state->setErrorByName('', $this->t('This employee is not available for check-in.'));
      return;
    }
    $form_state->set('attendance_uid', (int) $matches[2]);
    $form_state->set('attendance_action', $matches[1]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $form_state->get('attendance_uid');
    $action = $form_state->get('attendance_action');
    if (!$uid || !in_array($action, ['in', 'out'], TRUE)) {
      return;
    }
    $employee = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $employee->set('field_bc_staff_status', $action === 'in' ? 'present' : 'absent');
    $employee->save();
    $this->messenger()->addStatus($this->t('Attendance updated for @employee.', [
      '@employee' => $employee->getDisplayName(),
    ]));
    $form_state->setRedirect('bc_staff.managed_touch');
  }

  private function canOperate(?UserInterface $employee, bool $global, ?string $department): bool {
    if (!$employee || !$employee->isActive() || !$employee->hasField('field_bc_staff_status')
      || (int) $employee->id() === 1 || $employee->hasRole('administrator')
      || $employee->hasRole('bc_staff_admin') || $employee->hasRole('bc_staff_manager')) {
      return FALSE;
    }
    return $global || ($department !== NULL
      && EmployeeAccess::departmentId($employee) === $department);
  }

}
