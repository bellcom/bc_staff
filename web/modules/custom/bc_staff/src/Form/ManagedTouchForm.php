<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Attended terminal for a manager to update their department's attendance.
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
    $choices = [];
    $ids = $users->getQuery()->accessCheck(FALSE)->condition('uid', 0, '>')
      ->condition('status', 1)->sort('name')->execute();
    foreach ($users->loadMultiple($ids) as $employee) {
      if (!$this->canOperate($employee, $global, $department)) {
        continue;
      }
      $name = $employee->hasField('field_bc_staff_display_name')
        && !$employee->get('field_bc_staff_display_name')->isEmpty()
        ? (string) $employee->get('field_bc_staff_display_name')->value
        : $employee->getDisplayName();
      $choices[$employee->id()] = $name;
    }
    natcasesort($choices);
    $form['#attributes']['class'][] = 'bc-touch';
    $form['#attached']['library'][] = 'bc_staff/touch';
    $form['intro'] = [
      '#markup' => '<h1>' . $this->t('Team check-in terminal') . '</h1>',
    ];
    $form['employee'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose employee'),
      '#options' => $choices,
      '#empty_option' => $this->t('- Select employee -'),
      '#required' => TRUE,
    ];
    $form['actions'] = ['#type' => 'actions', '#attributes' => ['class' => ['bc-touch__actions']]];
    $form['actions']['check_in'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check in'),
      '#name' => 'check_in',
      '#attributes' => ['class' => ['bc-touch__button', 'bc-touch__button--in']],
    ];
    $form['actions']['check_out'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check out'),
      '#name' => 'check_out',
      '#attributes' => ['class' => ['bc-touch__button', 'bc-touch__button--out']],
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $uid = $form_state->getValue('employee');
    $employee = ctype_digit((string) $uid)
      ? \Drupal::entityTypeManager()->getStorage('user')->load($uid) : NULL;
    $current = $this->currentUser();
    $global = $current->id() === '1' || $current->hasPermission('administer bc staff employees');
    $manager = \Drupal::entityTypeManager()->getStorage('user')->load($current->id());
    if (!$employee || !$this->canOperate($employee, $global, EmployeeAccess::departmentId($manager))) {
      $form_state->setErrorByName('employee', $this->t('This employee is not available for check-in.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement()['#name'] ?? '';
    if (!in_array($trigger, ['check_in', 'check_out'], TRUE)) {
      $this->messenger()->addError($this->t('Invalid action.'));
      return;
    }
    $employee = \Drupal::entityTypeManager()->getStorage('user')->load($form_state->getValue('employee'));
    $employee->set('field_bc_staff_status', $trigger === 'check_in' ? 'present' : 'absent');
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
