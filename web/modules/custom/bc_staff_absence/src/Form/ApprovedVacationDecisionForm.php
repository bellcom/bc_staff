<?php

namespace Drupal\bc_staff_absence\Form;

use Drupal\bc_staff_absence\Access\AbsenceApprovalAccess;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Approve or reject a requested adjustment to approved vacation.
 */
final class ApprovedVacationDecisionForm extends FormBase {

  public function getFormId(): string { return 'bc_staff_approved_vacation_decision'; }

  public function buildForm(array $form, FormStateInterface $form_state, $change_id = NULL): array {
    $form['change_id'] = ['#type' => 'value', '#value' => $change_id];
    $form['approve'] = ['#type' => 'submit', '#value' => $this->t('Approve'), '#name' => 'approve'];
    $form['reject'] = ['#type' => 'submit', '#value' => $this->t('Reject'), '#name' => 'reject'];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (int) $form_state->getValue('change_id');
    $db = \Drupal::database();
    $q = $db->select('bc_staff_absence_change', 'c');
    $change = $q->fields('c')->condition('id', $id)->condition('state', 'pending')
      ->execute()->fetchObject();
    $absence = $change ? $db->select('bc_staff_absence', 'a')->fields('a')
      ->condition('id', $change->absence_id)->condition('state', 'approved')
      ->condition('type', 'vacation')->execute()->fetchObject() : FALSE;
    $users = \Drupal::entityTypeManager()->getStorage('user');
    $manager = $users->load($this->currentUser()->id());
    $employee = $absence ? $users->load($absence->uid) : NULL;
    if (!$change || !$absence || (int) $change->uid !== (int) $absence->uid
      || !AbsenceApprovalAccess::canReview($this->currentUser(), $manager, $employee)) {
      throw new AccessDeniedHttpException();
    }
    $action = $form_state->getTriggeringElement()['#name'] === 'approve' ? 'approved' : 'rejected';
    $transaction = $db->startTransaction();
    if ($action === 'approved') {
      if ($change->kind === 'dates') {
        $q = $db->select('bc_staff_absence', 'a');
        $conflicts = $q->condition('uid', (int) $absence->uid)
          ->condition('id', (int) $absence->id, '<>')
          ->condition('state', ['pending', 'approved', 'registered'], 'IN')
          ->condition('start_date', $change->end_date, '<=')
          ->condition('end_date', $change->start_date, '>=')
          ->countQuery()->execute()->fetchField();
        if ($conflicts) {
          $this->messenger()->addError($this->t('The requested dates overlap another absence.'));
          return;
        }
        $db->update('bc_staff_absence')->fields([
          'start_date' => $change->start_date, 'end_date' => $change->end_date,
          'changed' => \Drupal::time()->getRequestTime(), 'reviewed_by' => (int) $this->currentUser()->id(),
        ])->condition('id', $absence->id)->condition('state', 'approved')->execute();
      }
      elseif ($change->kind === 'cancel') {
        $db->update('bc_staff_absence')->fields([
          'state' => 'cancelled', 'changed' => \Drupal::time()->getRequestTime(),
          'reviewed_by' => (int) $this->currentUser()->id(),
        ])->condition('id', $absence->id)->condition('state', 'approved')->execute();
      }
      else { throw new AccessDeniedHttpException(); }
    }
    $now = \Drupal::time()->getRequestTime();
    $updated = $db->update('bc_staff_absence_change')->fields([
      'state' => $action, 'reviewed_by' => (int) $this->currentUser()->id(), 'changed' => $now,
    ])->condition('id', $id)->condition('state', 'pending')->execute();
    if ($updated) {
      $db->insert('bc_staff_absence_audit')->fields([
        'absence_id' => (int) $absence->id, 'actor_uid' => (int) $this->currentUser()->id(),
        'action' => $change->kind . '_' . $action,
        'details' => json_encode(['before' => [$absence->start_date, $absence->end_date],
          'requested' => [$change->start_date, $change->end_date]]),
        'created' => $now,
      ])->execute();
    }
    unset($transaction);
    if ($updated) {
      \Drupal\bc_staff_absence\Service\AbsenceNotifier::employee((int) $absence->uid, $change->kind . '_' . $action);
    }
    $this->messenger()->addStatus($this->t('Request updated.'));
    $form_state->setRedirect('bc_staff_absence.review');
  }
}
