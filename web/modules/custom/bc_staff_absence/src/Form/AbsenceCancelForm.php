<?php

namespace Drupal\bc_staff_absence\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Allows an employee to withdraw a pending vacation request.
 */
final class AbsenceCancelForm extends ConfirmFormBase {

  public function getFormId(): string {
    return 'bc_staff_absence_cancel';
  }

  public function getQuestion() {
    return $this->t('Withdraw this pending absence request?');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('bc_staff_absence.list');
  }

  public function buildForm(array $form, FormStateInterface $form_state, $absence = NULL): array {
    $record = ctype_digit((string) $absence)
      ? \Drupal::database()->select('bc_staff_absence', 'a')
        ->fields('a', ['id', 'uid', 'state'])
        ->condition('id', (int) $absence)->execute()->fetchObject() : FALSE;
    if (!$record || (int) $record->uid !== (int) $this->currentUser()->id()
      || $record->state !== 'pending') {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    $form_state->set('absence_id', (int) $record->id);
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Guard again against an approval performed while the form was open.
    $updated = \Drupal::database()->update('bc_staff_absence')
      ->fields([
        'state' => 'cancelled',
        'changed' => \Drupal::time()->getCurrentTime(),
      ])
      ->condition('id', $form_state->get('absence_id'))
      ->condition('uid', (int) $this->currentUser()->id())
      ->condition('state', 'pending')
      ->execute();
    $this->messenger()->addStatus($updated
      ? $this->t('The request was withdrawn.')
      : $this->t('This request can no longer be withdrawn.'));
    $form_state->setRedirect('bc_staff_absence.list');
  }

}
