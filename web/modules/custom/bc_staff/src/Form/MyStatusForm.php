<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Change the current account's own presence and location.
 */
final class MyStatusForm extends FormBase {

  public function getFormId(): string {
    return 'bc_staff_my_status_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $user = User::load($this->currentUser()->id());
    if (!$user || !$user->hasField('field_bc_staff_status')) {
      $form['error'] = ['#markup' => $this->t('BC Staff-felterne er ikke installeret.')];
      return $form;
    }
    $options = $user->get('field_bc_staff_status')->getFieldDefinition()->getSetting('allowed_values');
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Min status'),
      '#options' => $options,
      '#default_value' => $user->get('field_bc_staff_status')->value ?: 'absent',
      '#required' => TRUE,
    ];
    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min lokation'),
      '#default_value' => $user->get('field_bc_staff_location')->value ?: '',
      '#maxlength' => 128,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Gem min status'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $user = User::load($this->currentUser()->id());
    if (!$user || !$user->isActive()) {
      $this->messenger()->addError($this->t('Brugerkontoen kunne ikke opdateres.'));
      return;
    }
    $user->set('field_bc_staff_status', $form_state->getValue('status'));
    $user->set('field_bc_staff_location', $form_state->getValue('location'));
    $user->save();
    $this->messenger()->addStatus($this->t('Din status blev opdateret.'));
    $form_state->setRedirect('bc_staff.overview');
  }

}
