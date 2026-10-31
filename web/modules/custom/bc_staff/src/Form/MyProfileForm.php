<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Utility\StaffPhoto;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Read-only staff profile with self-service picture upload.
 */
final class MyProfileForm extends FormBase {

  public function getFormId(): string {
    return 'bc_staff_my_profile_form';
  }

  private function currentEmployee(): UserInterface {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
    if (!$account || !$account->isActive() || !$account->hasField('field_bc_staff_status')) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }
    return $account;
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = $this->currentEmployee();
    $text = static fn (string $field): string => $account->hasField($field)
      ? (string) ($account->get($field)->value ?? '') : '';
    $term = static fn (string $field): string => $account->hasField($field)
      && !$account->get($field)->isEmpty()
        ? ($account->get($field)->entity?->label() ?? '') : '';
    $roles = [];
    foreach ($account->getRoles(TRUE) as $id) {
      $role = \Drupal::entityTypeManager()->getStorage('user_role')->load($id);
      if ($role) {
        $roles[] = $role->label();
      }
    }
    $groups = [];
    if ($account->hasField('field_bc_staff_workgroups')) {
      foreach ($account->get('field_bc_staff_workgroups')->referencedEntities() as $group) {
        $groups[] = $group->label();
      }
    }
    $fallback = base_path() . \Drupal::service('extension.list.module')->getPath('bc_staff') . '/assets/avatar.svg';
    $form['#attributes']['class'][] = 'bc-org-form';
    $form['#attached']['library'][] = 'bc_staff/employee_editor';
    $form['#attached']['library'][] = 'bc_staff/my_profile';
    $form['intro'] = ['#markup' => '<p>' . $this->t('Your employment details are read-only. You can update your profile photo below.') . '</p>'];
    $form['general'] = [
      '#type' => 'details', '#title' => $this->t('Basic information'), '#open' => TRUE,
      '#attributes' => ['class' => ['bc-editor__section']],
    ];
    $form['general']['photo_preview'] = [
      '#theme' => 'image',
      '#uri' => StaffPhoto::url($account, \Drupal::service('file_url_generator'), $fallback),
      '#alt' => (string) $this->t('Current profile photo'),
      '#attributes' => ['class' => ['bc-editor__photo-preview']],
    ];
    $values = [
      'display_name' => [$this->t('Employee name'), $text('field_bc_staff_display_name') ?: $account->getDisplayName()],
      'login' => [$this->t('Login username'), $account->getAccountName()],
      'email' => [$this->t('Email'), (string) $account->getEmail()],
    ];
    foreach ($values as $key => [$label, $value]) {
      $form['general'][$key] = [
        '#type' => 'item', '#title' => $label, '#markup' => [
          '#plain_text' => $value ?: '—',
        ],
      ];
    }
    $form['employment'] = [
      '#type' => 'details', '#title' => $this->t('Employment'), '#open' => TRUE,
      '#attributes' => ['class' => ['bc-editor__section']],
    ];
    foreach ([
      'department' => [$this->t('Department'), $term('field_bc_staff_department')],
      'house' => [$this->t('House'), $term('field_bc_staff_house')],
      'workgroups' => [$this->t('Workgroups'), implode(', ', $groups)],
      'roles' => [$this->t('Roles'), implode(', ', $roles)],
      'enabled' => [$this->t('Active account'), $account->isActive() ? (string) $this->t('Yes') : (string) $this->t('No')],
    ] as $key => [$label, $value]) {
      $form['employment'][$key] = ['#type' => 'item', '#title' => $label, 'value' => ['#plain_text' => $value ?: '—']];
    }
    $form['locations'] = [
      '#type' => 'details', '#title' => $this->t('Locations'), '#open' => TRUE,
      '#attributes' => ['class' => ['bc-editor__section']],
      'location' => ['#type' => 'item', '#title' => $this->t('Current location'), 'value' => ['#plain_text' => $text('field_bc_staff_location') ?: '—']],
    ];

    if ($account->hasField('user_picture')) {
      $directory = 'public://bc_staff_photos';
      if (\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        $fid = $account->get('user_picture')->target_id ?? NULL;
        $form['general']['photo'] = [
          '#type' => 'managed_file',
          '#title' => $this->t('Profile photo'),
          '#description' => $this->t('Upload JPG, PNG or WebP, maximum 2 MB.'),
          '#upload_location' => $directory,
          '#upload_validators' => [
            'FileExtension' => ['extensions' => 'jpg jpeg png webp'],
            'FileSizeLimit' => ['fileLimit' => 2097152],
          ],
          '#default_value' => $fid ? [(int) $fid] : [],
        ];
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save profile photo'),
        ];
      }
    }
    $form['#cache']['max-age'] = 0;
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = $this->currentEmployee();
    if (!$account->hasField('user_picture') || !$form_state->hasValue('photo')) {
      return;
    }
    $fids = array_filter((array) $form_state->getValue('photo'));
    $fid = $fids ? (int) reset($fids) : NULL;
    if ($fid) {
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
      if (!$file || !str_starts_with($file->getFileUri(), 'public://bc_staff_photos/')) {
        $this->messenger()->addError($this->t('Invalid profile photo.'));
        return;
      }
      $file->setPermanent();
      $file->save();
    }
    $account->set('user_picture', $fid ? ['target_id' => $fid] : NULL);
    $account->save();
    $this->messenger()->addStatus($this->t('Profile photo updated.'));
    $form_state->setRedirect('bc_staff.my_profile');
  }

}
