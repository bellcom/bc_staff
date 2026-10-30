<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Extension\ModuleExtensionList;
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
    private readonly FileSystemInterface $staffFileSystem,
    private readonly FileUrlGeneratorInterface $staffFileUrlGenerator,
    private readonly ModuleExtensionList $staffModuleList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('file_system'),
      $container->get('file_url_generator'),
      $container->get('extension.list.module'),
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
    foreach ([
      'house' => ['bc_staff_house', 'field_bc_staff_house', $this->t('House')],
      'workgroups' => ['bc_staff_workgroup', 'field_bc_staff_workgroups', $this->t('Workgroups')],
    ] as $key => [$vid, $field, $label]) {
      $options = [];
      foreach ($this->staffEntityTypeManager->getStorage('taxonomy_term')->loadTree($vid) as $term) {
        $options[$term->tid] = $term->name;
      }
      $existing = $account && $account->hasField($field)
        ? array_column($account->get($field)->getValue(), 'target_id') : [];
      $form[$key] = [
        '#type' => $key === 'house' ? 'select' : 'checkboxes',
        '#title' => $label,
        '#options' => $key === 'house' ? ['' => $this->t('No house')] + $options : $options,
        '#default_value' => $key === 'house' ? ($existing[0] ?? '') : $existing,
      ];
    }
    $form['location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Current location'),
      '#default_value' => $value('field_bc_staff_location'),
      '#maxlength' => 128,
    ];
    $form['#attributes']['class'][] = 'bc-org-form';
    $form['#attached']['library'][] = 'bc_staff/organization';
    $form['#attached']['library'][] = 'bc_staff/employee_editor';
    $form['#attributes']['data-bc-employee-editor'] = '1';
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
    if ($account && $account->hasField('user_picture')) {
      $photo_dir = 'public://bc_staff_photos';
      if ($this->staffFileSystem->prepareDirectory($photo_dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $existing_fid = $account->get('user_picture')->target_id ?? NULL;
        if ($existing_fid && ($existing_file = $this->staffEntityTypeManager->getStorage('file')->load($existing_fid))) {
          $form['photo_preview'] = [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'class' => ['bc-editor__photo-preview'],
              'src' => $this->staffFileUrlGenerator->generateString($existing_file->getFileUri()),
              'alt' => $this->t('Current profile photo'),
            ],
          ];
        }
        if (!$existing_fid) {
          $fallback = base_path() . $this->staffModuleList->getPath('bc_staff') . '/assets/avatar.svg';
          $form['photo_preview'] = [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'class' => ['bc-editor__photo-preview'],
              'src' => $fallback,
              'alt' => $this->t('Default profile picture'),
            ],
          ];
        }
        $form['photo'] = [
          '#type' => 'managed_file',
          '#title' => $this->t('Profile photo'),
          '#description' => $this->t('Upload JPG, PNG or WebP, maximum 2 MB. Remove the uploaded photo to use the default avatar.'),
          '#upload_location' => $photo_dir,
          '#upload_validators' => [
            'FileExtension' => ['extensions' => 'jpg jpeg png webp'],
            'FileSizeLimit' => ['fileLimit' => 2097152],
          ],
          '#default_value' => $existing_fid ? [(int) $existing_fid] : [],
        ];
      }
    }
    foreach ([
      'general' => [$this->t('Basic information'), ['photo_preview', 'photo', 'display_name', 'name', 'mail']],
      'employment' => [$this->t('Employment'), ['department', 'house', 'workgroups', 'employee_role', 'enabled']],
      'locations' => [$this->t('Locations'), ['location', 'password']],
    ] as $key => [$label, $fields]) {
      $form[$key] = [
        '#type' => 'details',
        '#title' => $label,
        '#open' => TRUE,
        '#attributes' => [
          'class' => ['bc-editor__section'],
          'data-bc-editor-section' => $key,
        ],
      ];
      foreach ($fields as $field) {
        if (isset($form[$field])) {
          $form[$key][$field] = $form[$field];
          unset($form[$field]);
        }
      }
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save employee')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['house' => 'bc_staff_house', 'workgroups' => 'bc_staff_workgroup'] as $key => $vid) {
      $values = $key === 'house'
        ? [$form_state->getValue($key)] : array_filter((array) $form_state->getValue($key));
      foreach (array_filter($values) as $id) {
        $term = $this->staffEntityTypeManager->getStorage('taxonomy_term')->load($id);
        if (!$term || $term->bundle() !== $vid) {
          $form_state->setErrorByName($key, $this->t('Invalid organization selection.'));
        }
      }
    }
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
    if ($user->hasField('field_bc_staff_house')) {
      $user->set('field_bc_staff_house', $form_state->getValue('house') ?: NULL);
    }
    if ($user->hasField('field_bc_staff_workgroups')) {
      $workgroups = array_keys(array_filter((array) $form_state->getValue('workgroups')));
      $user->set('field_bc_staff_workgroups', array_map(
        static fn ($id) => ['target_id' => $id], $workgroups,
      ));
    }
    if (!$uid && $user->hasField('field_bc_staff_status')) {
      $user->set('field_bc_staff_status', 'absent');
    }
    if ($uid && $user->hasField('user_picture') && $form_state->hasValue('photo')) {
      $fids = array_filter((array) $form_state->getValue('photo'));
      $fid = $fids ? (int) reset($fids) : NULL;
      if ($fid) {
        $file = $this->staffEntityTypeManager->getStorage('file')->load($fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
        }
      }
      $user->set('user_picture', $fid ? ['target_id' => $fid] : NULL);
    }
    $user->save();
    $this->messenger()->addStatus($this->t('The employee was saved.'));
    $form_state->setRedirect('bc_staff.employee_list');
  }

}
