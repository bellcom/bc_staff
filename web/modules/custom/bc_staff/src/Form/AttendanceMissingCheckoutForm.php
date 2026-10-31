<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Records a missing checkout without overwriting original events.
 */
final class AttendanceMissingCheckoutForm extends FormBase {

  public function getFormId(): string { return 'bc_staff_missing_checkout'; }

  private function missing($uid): array {
    if (!ctype_digit((string) $uid)) { throw new AccessDeniedHttpException(); }
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $viewer = $this->currentUser();
    $manager = $storage->load($viewer->id());
    $employee = $storage->load((int) $uid);
    $global = (int) $viewer->id() === 1 || $viewer->hasPermission('administer bc staff employees');
    if (!$employee || !$employee->hasField('field_bc_staff_status')
      || (!$global && (!$viewer->hasPermission('manage own bc staff department')
      || !EmployeeAccess::departmentId($manager)
      || EmployeeAccess::departmentId($employee) !== EmployeeAccess::departmentId($manager)))) {
      throw new AccessDeniedHttpException();
    }
    $db = \Drupal::database();
    $event = $db->select('bc_staff_attendance', 'a')->fields('a')
      ->condition('uid', (int) $employee->id())->orderBy('id', 'DESC')
      ->range(0, 1)->execute()->fetchObject();
    if (!$event || $event->action !== 'in' || (string) $employee->get('field_bc_staff_status')->value !== 'present') {
      throw new AccessDeniedHttpException();
    }
    $latest_correction = $db->select('bc_staff_attendance_correction', 'c')
      ->fields('c', ['corrected_at'])->condition('event_id', (int) $event->id)
      ->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchField();
    $start = $latest_correction === FALSE ? (int) $event->created : (int) $latest_correction;
    return [$employee, $start];
  }

  public function buildForm(array $form, FormStateInterface $form_state, $user = NULL): array {
    [$employee, $start] = $this->missing($user);
    $form_state->set('employee_id', (int) $employee->id());
    $form_state->set('checkin_time', $start);
    $form['#attributes']['class'][] = 'bc-staff-absence-form';
    $form['#attached']['library'][] = 'bc_staff/attendance_history';
    $form['employee'] = ['#type' => 'item', '#title' => $this->t('Employee'), '#plain_text' => $employee->getDisplayName()];
    $form['checkin'] = ['#type' => 'item', '#title' => $this->t('Last check-in'),
      '#markup' => \Drupal::service('date.formatter')->format($start, 'custom', 'd-m-Y H:i')];
    $end = (new \Drupal\Core\Datetime\DrupalDateTime('now'))->setTimestamp($start + 8 * 3600);
    if ($end->getTimestamp() > \Drupal::time()->getCurrentTime()) {
      $end->setTimestamp(\Drupal::time()->getCurrentTime());
    }
    $form['checkout'] = ['#type' => 'datetime', '#title' => $this->t('Missing check-out time'),
      '#required' => TRUE, '#default_value' => $end];
    $form['reason'] = ['#type' => 'textarea', '#title' => $this->t('Reason for correction'),
      '#required' => TRUE, '#maxlength' => 500];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Register missing check-out')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $date = $form_state->getValue('checkout');
    $start = (int) $form_state->get('checkin_time');
    if (!$date instanceof \Drupal\Core\Datetime\DrupalDateTime
      || $date->getTimestamp() <= $start
      || $date->getTimestamp() > \Drupal::time()->getCurrentTime()) {
      $form_state->setErrorByName('checkout', $this->t('Check-out must be after check-in and not in the future.'));
    }
    if (trim((string) $form_state->getValue('reason')) === '') {
      $form_state->setErrorByName('reason', $this->t('A reason is required.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$employee, $start] = $this->missing($form_state->get('employee_id'));
    $when = $form_state->getValue('checkout')->getTimestamp();
    if ($when <= $start) { throw new AccessDeniedHttpException(); }
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    $id = $db->insert('bc_staff_attendance')->fields([
      'uid' => (int) $employee->id(), 'actor_uid' => (int) $this->currentUser()->id(),
      'action' => 'out', 'created' => $when,
      'previous_status' => 'present', 'source' => 'manual_correction',
    ])->execute();
    $db->insert('bc_staff_attendance_correction')->fields([
      'event_id' => (int) $id, 'uid' => (int) $employee->id(),
      'actor_uid' => (int) $this->currentUser()->id(), 'corrected_at' => $when,
      'reason' => trim((string) $form_state->getValue('reason')),
      'created' => \Drupal::time()->getCurrentTime(),
    ])->execute();
    $employee->set('field_bc_staff_status', 'absent');
    $employee->save();
    unset($transaction);
    $this->messenger()->addStatus($this->t('Missing check-out registered.'));
    $form_state->setRedirect('bc_staff.attendance_history');
  }
}
