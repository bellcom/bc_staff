<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the mobile self-service form for the signed-in staff member.
 */
final class MyStatusForm extends FormBase {

  /**
   * Constructs the staff status form.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bc_staff_my_status_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());
    if (!$account || !$account->isActive() || !$account->hasField('field_bc_staff_status')) {
      return ['error' => ['#markup' => $this->t('Sign in with an active employee account.')]];
    }

    $options = $account->get('field_bc_staff_status')->getFieldDefinition()->getSetting('allowed_values');
    $form['#prefix'] = '<div class="bc-staff-mobile">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'bc_staff/mobile';

    $display_name = $account->getDisplayName();
    if ($account->hasField('field_bc_staff_display_name')
      && !$account->get('field_bc_staff_display_name')->isEmpty()) {
      $display_name = $account->get('field_bc_staff_display_name')->value;
    }
    $current_status = $account->get('field_bc_staff_status')->value ?: 'absent';
    $current_location = $account->get('field_bc_staff_location')->value ?: $this->t('Not specified');
    $form['identity'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bc-staff-mobile-identity']],
      'eyebrow' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['bc-staff-mobile-identity__label']],
        '#value' => $this->t('Signed in as'),
      ],
      'name' => [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $display_name,
      ],
      'status' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Current status: @status', [
          '@status' => $options[$current_status] ?? $this->t('Not specified'),
        ]),
      ],
      'location' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Current location: @location', [
          '@location' => $current_location,
        ]),
      ],
    ];
    $form['intro'] = [
      '#markup' => '<p class="bc-staff-mobile-note">' . $this->t('Select a status to save it immediately. Use the save button below to update your location.') . '</p>',
    ];
    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('My status'),
      '#options' => $options,
      '#default_value' => $current_status,
      '#required' => TRUE,
      '#attributes' => ['class' => ['bc-staff-status-buttons']],
    ];

    $locations = [];
    if ($this->moduleHandler->moduleExists('taxonomy')) {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('bc_staff_location');
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
        '#title' => $this->t('My location'),
        '#options' => ['' => $this->t('Select a location')] + $locations,
        '#default_value' => $selected,
      ];
    }
    else {
      $form['location'] = [
        '#type' => 'textfield',
        '#title' => $this->t('My location'),
        '#default_value' => $account->get('field_bc_staff_location')->value ?: '',
        '#maxlength' => 128,
        '#description' => $this->t('Administrators can manage locations under Structure > Taxonomy.'),
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save my status'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());
    if (!$account || !$account->isActive()) {
      $this->messenger()->addError($this->t('The user account could not be updated.'));
      return;
    }
    $status = $form_state->getValue('status');
    $options = $account->get('field_bc_staff_status')->getFieldDefinition()->getSetting('allowed_values');
    if (!array_key_exists($status, $options)) {
      $this->messenger()->addError($this->t('Invalid status.'));
      return;
    }
    $location = (string) ($form_state->getValue('location') ?? '');
    if ($this->moduleHandler->moduleExists('taxonomy')) {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('bc_staff_location');
      if ($terms) {
        $allowed = [];
        foreach ($terms as $term) {
          $allowed[$term->tid] = $term->name;
        }
        if ($location !== '' && !isset($allowed[$location])) {
          $this->messenger()->addError($this->t('Invalid location.'));
          return;
        }
        $location = $location === '' ? '' : $allowed[$location];
      }
    }
    $account->set('field_bc_staff_status', $status);
    $account->set('field_bc_staff_location', $location);
    $account->save();
    $this->messenger()->addStatus($this->t('Your status has been updated.'));
    $form_state->setRedirect('bc_staff.mobile');
  }

}
