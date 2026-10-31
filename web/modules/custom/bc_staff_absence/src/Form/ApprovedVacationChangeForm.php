<?php

namespace Drupal\bc_staff_absence\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Requests date changes or withdrawal of an already approved vacation.
 */
final class ApprovedVacationChangeForm extends FormBase {

  public function getFormId(): string { return 'bc_staff_approved_vacation_change'; }

  private function record($id): ?object {
    if (!ctype_digit((string) $id)) { return NULL; }
    $db = \Drupal::database();
    $q = $db->select('bc_staff_absence', 'a');
    $record = $q->fields('a')->condition('id', (int) $id)
      ->condition('uid', (int) $this->currentUser()->id())
      ->condition('type', 'vacation')->condition('state', 'approved')
      ->execute()->fetchObject();
    return $record ?: NULL;
  }

  public function buildForm(array $form, FormStateInterface $form_state, $absence = NULL): array {
    $record = $this->record($absence);
    if (!$record) { throw new AccessDeniedHttpException(); }
    $db = \Drupal::database();
    $pending = $db->select('bc_staff_absence_change', 'c')
      ->condition('absence_id', (int) $record->id)->condition('state', 'pending')
      ->countQuery()->execute()->fetchField();
    if ($pending) {
      return ['notice' => ['#markup' => $this->t('A change request is already awaiting approval.')]];
    }
    $form_state->set('absence_id', (int) $record->id);
    $form['#attributes']['class'][] = 'bc-staff-absence-form';
    $form['kind'] = ['#type' => 'radios', '#title' => $this->t('Request type'),
      '#options' => ['dates' => $this->t('Change vacation dates'), 'cancel' => $this->t('Cancel approved vacation')],
      '#default_value' => 'dates', '#required' => TRUE];
    $form['start_date'] = ['#type' => 'date', '#title' => $this->t('Start date'), '#default_value' => $record->start_date];
    $form['end_date'] = ['#type' => 'date', '#title' => $this->t('End date'), '#default_value' => $record->end_date];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Submit change request')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('kind') !== 'dates') { return; }
    $start = (string) $form_state->getValue('start_date');
    $end = (string) $form_state->getValue('end_date');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
      || !\DateTimeImmutable::createFromFormat('!Y-m-d', $start)
      || !\DateTimeImmutable::createFromFormat('!Y-m-d', $end) || $start > $end) {
      $form_state->setErrorByName('end_date', $this->t('Enter a valid date range.'));
      return;
    }
    $q = \Drupal::database()->select('bc_staff_absence', 'a');
    $overlap = $q->condition('uid', (int) $this->currentUser()->id())
      ->condition('id', (int) $form_state->get('absence_id'), '<>')
      ->condition('state', ['pending', 'approved', 'registered'], 'IN')
      ->condition('start_date', $end, '<=')->condition('end_date', $start, '>=')
      ->countQuery()->execute()->fetchField();
    if ($overlap) {
      $form_state->setErrorByName('start_date', $this->t('You already have an absence covering one or more of these dates.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (int) $form_state->get('absence_id');
    $record = $this->record($id);
    if (!$record) { throw new AccessDeniedHttpException(); }
    $db = \Drupal::database();
    $transaction = $db->startTransaction();
    $already = $db->select('bc_staff_absence_change', 'c')->condition('absence_id', $id)
      ->condition('state', 'pending')->countQuery()->execute()->fetchField();
    if ($already) {
      $this->messenger()->addWarning($this->t('A change request is already awaiting approval.'));
      return;
    }
    $kind = $form_state->getValue('kind') === 'cancel' ? 'cancel' : 'dates';
    $now = \Drupal::time()->getRequestTime();
    $db->insert('bc_staff_absence_change')->fields([
      'absence_id' => $id, 'uid' => (int) $this->currentUser()->id(), 'kind' => $kind,
      'start_date' => $kind === 'dates' ? $form_state->getValue('start_date') : NULL,
      'end_date' => $kind === 'dates' ? $form_state->getValue('end_date') : NULL,
      'state' => 'pending', 'created' => $now, 'changed' => $now,
    ])->execute();
    $db->insert('bc_staff_absence_audit')->fields([
      'absence_id' => $id, 'actor_uid' => (int) $this->currentUser()->id(),
      'action' => 'change_requested', 'details' => json_encode(['kind' => $kind,
        'start' => $kind === 'dates' ? $form_state->getValue('start_date') : NULL,
        'end' => $kind === 'dates' ? $form_state->getValue('end_date') : NULL]),
      'created' => $now,
    ])->execute();
    unset($transaction);
    \Drupal\bc_staff_absence\Service\AbsenceNotifier::reviewers((int) $this->currentUser()->id(), 'change');
    $this->messenger()->addStatus($this->t('Change request submitted for approval.'));
    $form_state->setRedirect('bc_staff_absence.list');
  }
}
