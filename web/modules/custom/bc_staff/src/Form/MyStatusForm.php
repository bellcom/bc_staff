<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Mobile-friendly self service, always limited to the logged-in user.
 */
final class MyStatusForm extends FormBase {

  public function getFormId(): string {
    return 'bc_staff_my_status_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = User::load($this->currentUser()->id());
    if (!$account || !$account->isActive() || !$account->hasField('field_bc_staff_status')) {
      return ['error' => ['#markup' => $this->t('Log ind med en aktiv medarbejderkonto.')]];
    }

    $options = $account->get('field_bc_staff_status')->getFieldDefinition()->getSetting('allowed_values');
    $form['#prefix'] = '<div class="bc-staff-mobile">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'bc_staff/mobile';
    $form['intro'] = [
      '#markup' => '<p class="bc-staff-mobile-note">Vælg din status og placering. Ændringer gemmes først, når du trykker Gem.</p>',
    ];
    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Min status'),
      '#options' => $options,
      '#default_value' => $account->get('field_bc_staff_status')->value ?: 'absent',
      '#required' => TRUE,
      '#attributes' => ['class' => ['bc-staff-status-buttons']],
    ];

    $locations = [];
    if (\Drupal::moduleHandler()->moduleExists('taxonomy')) {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('bc_staff_location');
      foreach ($terms as $term) {
        $locations[$term->tid] = $term->name;
      }
    }

    if ($locations) {
      $current = (string) ($account->get('field_bc_staff_location')->value ?? '');
      $selected = '';
      foreach ($locations as $tid => $label) {
        if ($label === $current) {
          $selected = (string) $tid;
          break;
        }
      }
      $form['location'] = [
        '#type' => 'select',
        '#title' => $this->t('Min lokation'),
        '#options' => ['' => $this->t('Vælg lokation')] + $locations,
        '#default_value' => $selected,
      ];
    }
    else {
      $form['location'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Min lokation'),
        '#default_value' => $account->get('field_bc_staff_location')->value ?: '',
        '#maxlength' => 128,
      ];
      $form['location']['#description'] = $this->t('Administrator kan oprette valgbare lokationer under Struktur → Taksonomi.');
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Gem min status'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = User::load($this->currentUser()->id());
    if (!$account || !$account->isActive()) {
      $this->messenger()->addError($this->t('Brugerkontoen kunne ikke opdateres.'));
      return;
    }
    $status = $form_state->getValue('status');
    $options = $account->get('field_bc_staff_status')->getFieldDefinition()->getSetting('allowed_values');
    if (!array_key_exists($status, $options)) {
      $this->messenger()->addError($this->t('Ugyldig status.'));
      return;
    }
    $location = $form_state->getValue('location');
    if (\Drupal::moduleHandler()->moduleExists('taxonomy')) {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('bc_staff_location');
      if ($terms) {
        $allowed = [];
        foreach ($terms as $term) {
          $allowed[$term->tid] = $term->name;
        }
        if ($location !== '' && !isset($allowed[$location])) {
          $this->messenger()->addError($this->t('Ugyldig lokation.'));
          return;
        }
        $location = $location === '' ? '' : $allowed[$location];
      }
    }
    $account->set('field_bc_staff_status', $status);
    $account->set('field_bc_staff_location', $location);
    $account->save();
    $this->messenger()->addStatus($this->t('Din status blev opdateret.'));
    $form_state->setRedirect('bc_staff.mobile');
  }

}
