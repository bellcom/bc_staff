<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Manage a Drupal account and its BC Staff attributes together.
 */
final class EmployeeForm extends FormBase {

  public function getFormId(): string {
    return 'bc_staff_employee_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $id = \Drupal::routeMatch()->getParameter('user');
    $account = $id ? User::load(is_object($id) ? $id->id() : $id) : NULL;
    if ($id && (!$account || (int) $account->id() === 1)) {
      return ['notice' => ['#markup' => $this->t('Denne konto kan ikke redigeres her.')]];
    }
    $form_state->set('bc_staff_uid', $account?->id());
    $value = static fn(string $field) => $account && $account->hasField($field) ? ($account->get($field)->value ?? '') : '';
    $form['name'] = ['#type' => 'textfield', '#title' => $this->t('Loginbrugernavn'), '#required' => TRUE, '#default_value' => $account?->getAccountName() ?? '', '#maxlength' => 60];
    $form['mail'] = ['#type' => 'email', '#title' => $this->t('E-mail'), '#required' => TRUE, '#default_value' => $account?->getEmail() ?? ''];
    $form['display_name'] = ['#type' => 'textfield', '#title' => $this->t('Medarbejdernavn'), '#required' => TRUE, '#default_value' => $value('field_bc_staff_display_name'), '#maxlength' => 128];
    $form['department'] = ['#type' => 'select', '#title' => $this->t('Afdeling'), '#options' => ['' => $this->t('Ingen afdeling')], '#default_value' => $account && $account->hasField('field_bc_staff_department') ? ($account->get('field_bc_staff_department')->target_id ?? '') : ''];
    if (\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('bc_staff_department')) {
      foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('bc_staff_department') as $term) {
        $form['department']['#options'][$term->tid] = $term->name;
      }
    }
    $form['location'] = ['#type' => 'textfield', '#title' => $this->t('Aktuel lokation'), '#default_value' => $value('field_bc_staff_location'), '#maxlength' => 128];
    $form['enabled'] = ['#type' => 'checkbox', '#title' => $this->t('Aktiv konto (kan logge ind)'), '#default_value' => $account ? (int) $account->isActive() : 1];
    $form['employee_role'] = ['#type' => 'checkbox', '#title' => $this->t('BC Staff Medarbejder'), '#default_value' => $account ? (int) $account->hasRole('bc_staff_employee') : 1];
    $form['password'] = ['#type' => 'password', '#title' => $account ? $this->t('Ny adgangskode (valgfrit)') : $this->t('Adgangskode'), '#required' => !$account, '#description' => $this->t('Ved redigering ændres adgangskoden kun, hvis feltet udfyldes.')];
    if ($account) {
      $form['photo'] = [
        '#type' => 'item', '#title' => $this->t('Profilfoto'),
        '#markup' => $this->t('Rediger profilfoto via Drupals brugerformular.'),
        '#description' => $this->t('Brugerløsningen anvender Drupal-kontoens eksisterende profilfoto.'),
      ];
      $form['photo_link'] = ['#type' => 'link', '#title' => $this->t('Administrer foto og øvrige kontoindstillinger'), '#url' => Url::fromRoute('entity.user.edit_form', ['user' => $account->id()])];
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Gem medarbejder')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $uid = $form_state->get('bc_staff_uid');
    foreach (['name' => 'name', 'mail' => 'mail'] as $key => $property) {
      $matching = $storage->loadByProperties([$property => $form_state->getValue($key)]);
      foreach ($matching as $match) {
        if ((string) $match->id() !== (string) $uid) {
          $form_state->setErrorByName($key, $this->t('Værdien bruges allerede af en anden konto.'));
          break;
        }
      }
    }
    $department = $form_state->getValue('department');
    if ($department && !\Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($department)) {
      $form_state->setErrorByName('department', $this->t('Ugyldig afdeling.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $form_state->get('bc_staff_uid');
    $user = $uid ? User::load($uid) : User::create();
    if (!$user || ($uid && (int) $uid === 1)) {
      $this->messenger()->addError($this->t('Medarbejderen kunne ikke gemmes.'));
      return;
    }
    $user->setUsername($form_state->getValue('name'));
    $user->setEmail($form_state->getValue('mail'));
    $user->set('status', (int) $form_state->getValue('enabled'));
    if ($form_state->getValue('password') !== '') {
      $user->setPassword($form_state->getValue('password'));
    }
    if ($form_state->getValue('employee_role')) {
      $user->addRole('bc_staff_employee');
    }
    else {
      $user->removeRole('bc_staff_employee');
    }
    foreach ([
      'field_bc_staff_display_name' => 'display_name',
      'field_bc_staff_location' => 'location',
    ] as $field => $input) {
      if ($user->hasField($field)) {
        $user->set($field, $form_state->getValue($input));
      }
    }
    if ($user->hasField('field_bc_staff_department')) {
      $user->set('field_bc_staff_department', $form_state->getValue('department') ?: NULL);
    }
    if (!$uid && $user->hasField('field_bc_staff_status')) {
      $user->set('field_bc_staff_status', 'absent');
    }
    $user->save();
    $this->messenger()->addStatus($this->t('Medarbejderen blev gemt.'));
    $form_state->setRedirect('bc_staff.employee_list');
  }

}
