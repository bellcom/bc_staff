<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff\Service\AttendanceRecorder;
use Drupal\bc_staff\Utility\StaffPhoto;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Full-screen kiosk with permanent department-scoped bearer URL.
 */
final class KioskTouchForm extends FormBase {

  public function getFormId(): string { return 'bc_staff_kiosk_touch'; }

  private function department(string $token): string {
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { throw new NotFoundHttpException(); }
    foreach ((array) $this->configFactory()->get('bc_staff.kiosks')->get('departments') as $id => $secret) {
      if (is_string($secret) && hash_equals($secret, $token)) { return (string) $id; }
    }
    throw new NotFoundHttpException();
  }

  public function buildForm(array $form, FormStateInterface $form_state, $token = NULL): array {
    $department = $this->department((string) $token);
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($department);
    if (!$term || $term->bundle() !== 'bc_staff_department') { throw new NotFoundHttpException(); }
    $form['#attributes']['class'][] = 'bc-touch-directory';
    $form['#attached']['library'][] = 'bc_staff/employee_admin';
    $form['#attached']['library'][] = 'bc_staff/touch_directory';
    $form['#attached']['library'][] = 'bc_staff/kiosk';
    $form['#cache']['max-age'] = 0;
    $form_state->set('kiosk_token', (string) $token);
    $form['heading'] = ['#markup' => '<header class="bc-employees__header"><div><h2>' .
      htmlspecialchars($term->label(), ENT_QUOTES, 'UTF-8') . '</h2><p>' .
      $this->t('Select an employee to check in or out.') . '</p></div></header>'];
    $form['search'] = ['#type' => 'search', '#title' => $this->t('Search employees'),
      '#attributes' => ['placeholder' => $this->t('Search employees'), 'data-bc-touch-search' => '']];
    $form['cards'] = ['#type' => 'container', '#attributes' => ['class' => ['bc-employees__grid'], 'data-bc-touch-grid' => '']];
    $users = \Drupal::entityTypeManager()->getStorage('user');
    $ids = $users->getQuery()->accessCheck(FALSE)->condition('uid', 0, '>')
      ->condition('status', 1)->sort('name')->execute();
    $fallback = base_path() . \Drupal::service('extension.list.module')->getPath('bc_staff') . '/assets/avatar.svg';
    $file_urls = \Drupal::service('file_url_generator');
    foreach ($users->loadMultiple($ids) as $employee) {
      if (!$this->eligible($employee, $department)) { continue; }
      $uid = (int) $employee->id();
      $name = $employee->hasField('field_bc_staff_display_name') && !$employee->get('field_bc_staff_display_name')->isEmpty()
        ? (string) $employee->get('field_bc_staff_display_name')->value : $employee->getDisplayName();
      $status = (string) ($employee->get('field_bc_staff_status')->value ?? '');
      $card = &$form['cards']['person_' . $uid];
      $card = ['#type' => 'container', '#attributes' => [
        'class' => ['bc-employees__card', 'bc-touch-directory__card'],
        'data-bc-touch-card' => '', 'data-bc-touch-name' => mb_strtolower($name)]];
      $card['top']['photo'] = ['#theme' => 'image', '#uri' => StaffPhoto::url($employee, $file_urls, $fallback),
        '#alt' => '', '#attributes' => ['class' => ['bc-employees__avatar']]];
      $card['top']['name'] = ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $name];
      $card['top']['status'] = ['#type' => 'html_tag', '#tag' => 'p',
        '#value' => $status === 'present' ? $this->t('Present') : $this->t('Not checked in'),
        '#attributes' => ['class' => ['bc-touch-directory__status']]];
      $card['actions'] = ['#type' => 'container', '#attributes' => ['class' => ['bc-touch-directory__actions']]];
      foreach (['in' => $this->t('Ind'), 'out' => $this->t('Ud')] as $action => $label) {
        $card['actions'][$action] = ['#type' => 'submit', '#value' => $label,
          '#name' => $action . '_' . $uid,
          '#attributes' => ['class' => ['bc-touch-directory__button', 'bc-touch-directory__button--' . $action]],
          '#disabled' => $action === 'in' ? $status === 'present' : $status === 'absent'];
      }
      unset($card);
    }
    return $form;
  }

  private function eligible($employee, string $department): bool {
    return $employee && $employee->isActive() && (int) $employee->id() !== 1
      && $employee->hasField('field_bc_staff_status')
      && !$employee->hasRole('administrator') && !$employee->hasRole('bc_staff_admin')
      && !$employee->hasRole('bc_staff_manager')
      && EmployeeAccess::departmentId($employee) === $department;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $token = (string) $form_state->get('kiosk_token');
    $department = $this->department($token);
    $name = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if (!preg_match('/^(in|out)_([1-9][0-9]*)$/D', $name, $m)) {
      $form_state->setErrorByName('', $this->t('Invalid action.'));
      return;
    }
    $employee = \Drupal::entityTypeManager()->getStorage('user')->load((int) $m[2]);
    if (!$this->eligible($employee, $department)) {
      $form_state->setErrorByName('', $this->t('This employee is not available for check-in.'));
      return;
    }
    $status = (string) ($employee->get('field_bc_staff_status')->value ?? '');
    if (($m[1] === 'in' && $status === 'present') || ($m[1] === 'out' && $status === 'absent')) {
      $form_state->setErrorByName('', $this->t('Attendance already has this status.'));
      return;
    }
    $form_state->set('action', $m[1]);
    $form_state->set('uid', (int) $m[2]);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $department = $this->department((string) $form_state->get('kiosk_token'));
    $employee = \Drupal::entityTypeManager()->getStorage('user')->load((int) $form_state->get('uid'));
    if (!$this->eligible($employee, $department)) { throw new NotFoundHttpException(); }
    AttendanceRecorder::record($employee, (string) $form_state->get('action'), 0, 'kiosk');
    $this->messenger()->addStatus($this->t('Attendance updated for @employee.', ['@employee' => $employee->getDisplayName()]));
    $form_state->setRedirect('bc_staff.kiosk', ['token' => $form_state->get('kiosk_token')]);
  }
}
