<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Touch-friendly check-in for the currently authenticated employee.
 *
 * Shared unattended multi-user terminals require a separate authentication
 * design; this form never allows editing another user's presence.
 */
final class TouchCheckInForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bc_staff_touch_check_in';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    if (!$account || !$account->isActive() || !$account->hasField('field_bc_staff_status')) {
      return ['message' => ['#markup' => $this->t('Sign in with an active employee account.')]];
    }
    $name = $account->hasField('field_bc_staff_display_name')
      && !$account->get('field_bc_staff_display_name')->isEmpty()
      ? $account->get('field_bc_staff_display_name')->value
      : $account->getDisplayName();
    $current = (string) ($account->get('field_bc_staff_status')->value ?? 'absent');
    $form['#attributes']['class'][] = 'bc-touch';
    $form['#attached']['library'][] = 'bc_staff/touch';
    $form['intro'] = [
      '#type' => 'container',
      'heading' => ['#markup' => '<h1>' . $this->t('Check in / Check out') . '</h1>'],
      'name' => ['#markup' => '<p class="bc-touch__name">' . $this->t('Signed in as @name', ['@name' => $name]) . '</p>'],
      'state' => ['#markup' => '<p>' . $this->t('Current status: @status', [
        '@status' => $current === 'present' ? $this->t('Present') : $this->t('Not checked in'),
      ]) . '</p>'],
    ];
    $form['actions'] = ['#type' => 'actions', '#attributes' => ['class' => ['bc-touch__actions']]];
    $form['actions']['check_in'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check in'),
      '#name' => 'check_in',
      '#attributes' => ['class' => ['bc-touch__button', 'bc-touch__button--in']],
      '#disabled' => $current === 'present',
    ];
    $form['actions']['check_out'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check out'),
      '#name' => 'check_out',
      '#attributes' => ['class' => ['bc-touch__button', 'bc-touch__button--out']],
      '#disabled' => $current === 'absent',
    ];
    $form['more'] = [
      '#type' => 'link',
      '#title' => $this->t('My status and location'),
      '#url' => Url::fromRoute('bc_staff.mobile'),
      '#attributes' => ['class' => ['bc-touch__more']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    if (!$account || !$account->isActive() || !$account->hasField('field_bc_staff_status')) {
      $this->messenger()->addError($this->t('The user account could not be updated.'));
      return;
    }
    $trigger = $form_state->getTriggeringElement()['#name'] ?? '';
    if (!in_array($trigger, ['check_in', 'check_out'], TRUE)) {
      $this->messenger()->addError($this->t('Invalid action.'));
      return;
    }
    \Drupal\\bc_staff\\Service\\AttendanceRecorder::record($account, $trigger === 'check_in' ? 'in' : 'out', (int) $this->currentUser()->id(), 'self_touch');
    $this->messenger()->addStatus($trigger === 'check_in'
      ? $this->t('You are checked in.') : $this->t('You are checked out.'));
    $form_state->setRedirect('bc_staff.touch');
  }

}
