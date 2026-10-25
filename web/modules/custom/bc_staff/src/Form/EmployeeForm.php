<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Manage a Drupal account and its BC Staff attributes together.
 */
final class EmployeeForm extends FormBase {

  /**
   * Constructs the employee form.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $staffEntityTypeManager,
    private readonly CurrentRouteMatch $staffRouteMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bc_staff_employee_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $id = $this->staffRouteMatch->getParameter('user');
    $account = $id ? $this->staffEntityTypeManager->getStorage('user')->load(is_object($id) ? $id->id() : $id) : NULL;
    if ($id && (!$account || (int) $account->id() === 1 || $account->hasRole('administrator'))) {
      throw new AccessDeniedHttpException();
    }
    $manager = $this->currentUser()->hasPermission('manage own bc staff department')
      && !$this->currentUser()->hasPermission('administer bc staff employees');
    $leader = $manager ? $this->staffEntityTypeManager->getStorage('user')->load($this->currentUser()->id()) : NULL;
    $leader_department = EmployeeAccess::departmentId($leader);
    if ($manager && (!$leader_department || ($account && !EmployeeAccess::canManage($leader, $account)))) {
      throw new AccessDeniedHttpException();
    }
    $form_state->set('bc_staff_manager_department', $manager ? $leader_department : NULL);
    $form_state->set('bc_staff_uid', $account?->id());
    $value = static fn (string $field) => $account && $account->hasField($field)
      ? ($account->get($field)->value ?? '')
      : '';
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login username'),
      '#required' => TRUE,
      '#default_value' => $account?->getAccountName() ?? '',
      '#maxlength' => 60,
    ];
    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $account?->getEmail() ?? '',
    ];
    $form['display_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Employee name'),
      '#required' => TRUE,
      '#default_value' => $value('field_bc_staff_display_name'),
      '#maxlength' => 128,
    ];
    $form['department'] = [
      '#type' => 'select',
      '#title' => $this->t('Department'),
      '#options' => ['' => $this->t('No department')],
      '#default_value' => $account && $account->hasField('field_bc_staff_department')
        ? ($account->get('field_bc_staff_department')->target_id ?? '')
        : '',
    ];
    if ($this->staffEntityTypeManager->getStorage('taxonomy_vocabulary')->load('bc_staff_department')) {
      foreach ($this->staffEntityTypeManager->getStorage('taxonomy_term')->loadTree('bc_staff_department') as $term) {
        $form['department']['#options'][$term->tid] = $term->name;
      }
    }
    if ($manager) {
      $form['department']['#options'] = [
        $leader_department => $form['department']['#options'][$leader_department]
        ?? $this->t('Own department'),
      ];
      $form['department']['#default_value'] = $leader_department;
      $form['department']['#disabled'] = TRUE;
    }
    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Current location'),
      '#default_value' => $value('field_bc_staff_location'),
      '#maxlength' => 128,
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active account (can sign in)'),
      '#default_value' => $account ? (int) $account->isActive() : 1,
    ];
    $form['employee_role'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('BC Staff Employee'),
      '#default_value' => $account ? (int) $account->hasRole('bc_staff_employee') : 1,
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $account ? $this->t('New password (optional)') : $this->t('Password'),
      '#required' => !$account,
      '#description' => $this->t('When editing, leave the field blank to keep the existing password.'),
    ];
    if ($account) {
      $form['photo'] = [
        '#type' => 'item',
        '#title' => $this->t('Profile photo'),
        '#markup' => $this->t('Edit the profile photo through the Drupal user form.'),
        '#description' => $this->t('BC Staff uses the existing account profile photo.'),
      ];
      $form['photo_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Manage photo and account settings'),
        '#url' => Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]),
      ];
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save employee')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->staffEntityTypeManager->getStorage('user');
    $uid = $form_state->get('bc_staff_uid');
    foreach (['name' => 'name', 'mail' => 'mail'] as $key => $property) {
      $matching = $storage->loadByProperties([$property => $form_state->getValue($key)]);
      foreach ($matching as $match) {
        if ((string) $match->id() !== (string) $uid) {
          $form_state->setErrorByName($key, $this->t('This value is already used by another account.'));
          break;
        }
      }
    }
    $department = $form_state->get('bc_staff_manager_department') ?: $form_state->getValue('department');
    $term = $department ? $this->staffEntityTypeManager->getStorage('taxonomy_term')->load($department) : NULL;
    if ($department && (!$term || $term->bundle() !== 'bc_staff_department')) {
      $form_state->setErrorByName('department', $this->t('Invalid department.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $form_state->get('bc_staff_uid');
    $user = $uid ? $this->staffEntityTypeManager->getStorage('user')->load($uid) : $this->staffEntityTypeManager->getStorage('user')->create();
    $manager_department = $form_state->get('bc_staff_manager_department');
    if ($manager_department) {
      $leader = $this->staffEntityTypeManager->getStorage('user')->load($this->currentUser()->id());
      if (!EmployeeAccess::departmentId($leader)
        || ($uid && !EmployeeAccess::canManage($leader, $user))) {
        throw new AccessDeniedHttpException();
      }
    }
    if (!$user || ($uid && (
      (int) $uid === 1
      || $user->hasRole('administrator')
      || $user->hasRole('bc_staff_admin')
      || $user->hasRole('bc_staff_manager')
    ))) {
      $this->messenger()->addError($this->t('The employee could not be saved.'));
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
      $user->set('field_bc_staff_department', ($manager_department ?: $form_state->getValue('department')) ?: NULL);
    }
    if (!$uid && $user->hasField('field_bc_staff_status')) {
      $user->set('field_bc_staff_status', 'absent');
    }
    $user->save();
    $this->messenger()->addStatus($this->t('The employee was saved.'));
    $form_state->setRedirect('bc_staff.employee_list');
  }

}
