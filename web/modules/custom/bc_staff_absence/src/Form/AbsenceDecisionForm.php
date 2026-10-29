<?php

namespace Drupal\bc_staff_absence\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\user\Entity\User;

/**
 * Restricts each vacation decision to the correct manager.
 */
final class AbsenceDecisionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bc_staff_absence_decision_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $absence_id = NULL): array {
    $form['absence_id'] = ['#type' => 'value', '#value' => $absence_id];
    $form['approve'] = ['#type' => 'submit', '#value' => $this->t('Approve'), '#name' => 'approve'];
    $form['reject'] = ['#type' => 'submit', '#value' => $this->t('Reject'), '#name' => 'reject'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (int) $form_state->getValue('absence_id');
    $record = \Drupal::database()->select('bc_staff_absence', 'a')
      ->fields('a', ['uid', 'state', 'type'])
      ->condition('id', $id)
      ->execute()->fetchObject();
    $account = $this->currentUser();
    $is_admin = $account->id() === '1' || $account->hasPermission('manage all bc staff absences');
    $manager = User::load($account->id());
    $employee = $record ? User::load($record->uid) : NULL;
    if (!$record || !$employee || $record->type !== 'vacation' || $record->state !== 'pending'
      || (!$is_admin && (!$account->hasPermission('manage own bc staff department') || !EmployeeAccess::canManage($manager, $employee)))) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    $decision = $form_state->getTriggeringElement()['#name'] === 'approve' ? 'approved' : 'rejected';
    \Drupal::database()->update('bc_staff_absence')
      ->fields([
        'state' => $decision,
        'reviewed_by' => (int) $account->id(),
        'changed' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('id', $id)
      ->condition('state', 'pending')
      ->execute();
    $this->messenger()->addStatus($this->t('Request updated.'));
    $form_state->setRedirect('bc_staff_absence.review');
  }

}
