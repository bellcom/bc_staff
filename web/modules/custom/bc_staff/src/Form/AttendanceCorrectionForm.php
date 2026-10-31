<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Records an auditable correction without modifying original check-in events.
 */
final class AttendanceCorrectionForm extends FormBase {

  public function getFormId(): string { return 'bc_staff_attendance_correction'; }

  private function event($id): object {
    if (!ctype_digit((string) $id)) { throw new AccessDeniedHttpException(); }
    $db = \Drupal::database();
    $event = $db->select('bc_staff_attendance', 'a')->fields('a')
      ->condition('id', (int) $id)->execute()->fetchObject();
    $accounts = \Drupal::entityTypeManager()->getStorage('user');
    $viewer = $this->currentUser();
    $manager = $accounts->load($viewer->id());
    $employee = $event ? $accounts->load($event->uid) : NULL;
    $global = (int) $viewer->id() === 1 || $viewer->hasPermission('administer bc staff employees');
    if (!$employee || (!$global && (!$viewer->hasPermission('manage own bc staff department')
      || !EmployeeAccess::departmentId($manager)
      || EmployeeAccess::departmentId($employee) !== EmployeeAccess::departmentId($manager)))) {
      throw new AccessDeniedHttpException();
    }
    return $event;
  }

  public function buildForm(array $form, FormStateInterface $form_state, $attendance = NULL): array {
    $event = $this->event($attendance);
    $db = \Drupal::database();
    $latest = $db->select('bc_staff_attendance_correction', 'c')->fields('c', ['corrected_at'])
      ->condition('event_id', (int) $event->id)->orderBy('id', 'DESC')
      ->range(0, 1)->execute()->fetchField();
    $timestamp = $latest === FALSE ? (int) $event->created : (int) $latest;
    $form_state->set('attendance_id', (int) $event->id);
    $form['#attributes']['class'][] = 'bc-staff-absence-form';
    $form['original'] = ['#type' => 'item', '#title' => $this->t('Original registration'),
      '#markup' => \Drupal::service('date.formatter')->format((int) $event->created, 'custom', 'd-m-Y H:i')];
    $form['corrected_at'] = ['#type' => 'datetime', '#title' => $this->t('Corrected date and time'),
      '#required' => TRUE, '#default_value' => \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp($timestamp)];
    $form['reason'] = ['#type' => 'textarea', '#title' => $this->t('Reason for correction'),
      '#required' => TRUE, '#maxlength' => 500];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save correction')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $date = $form_state->getValue('corrected_at');
    if (!$date instanceof \Drupal\Core\Datetime\DrupalDateTime || $date->getTimestamp() > \Drupal::time()->getCurrentTime() + 300) {
      $form_state->setErrorByName('corrected_at', $this->t('Enter a valid time that is not in the future.'));
    }
    if (trim((string) $form_state->getValue('reason')) === '') {
      $form_state->setErrorByName('reason', $this->t('A reason is required.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event = $this->event($form_state->get('attendance_id'));
    \Drupal::database()->insert('bc_staff_attendance_correction')->fields([
      'event_id' => (int) $event->id,
      'uid' => (int) $event->uid,
      'actor_uid' => (int) $this->currentUser()->id(),
      'corrected_at' => $form_state->getValue('corrected_at')->getTimestamp(),
      'reason' => trim((string) $form_state->getValue('reason')),
      'created' => \Drupal::time()->getCurrentTime(),
    ])->execute();
    $this->messenger()->addStatus($this->t('Attendance correction saved.'));
    $form_state->setRedirect('bc_staff.attendance_report');
  }
}
