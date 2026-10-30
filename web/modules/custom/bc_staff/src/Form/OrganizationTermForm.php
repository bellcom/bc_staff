<?php

namespace Drupal\bc_staff\Form;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Form\FormBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds BC Staff organization terms in a controlled vocabulary.
 */
final class OrganizationTermForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bc_staff_organization_term';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $vocabulary = NULL, ?int $term = NULL): array {
    $viewer = $this->currentUser();
    $is_manager = $viewer->id() !== '1'
      && !$viewer->hasPermission('administer bc staff employees');
    $leader = $is_manager ? \Drupal::entityTypeManager()->getStorage('user')->load($viewer->id()) : NULL;
    $department = $is_manager ? EmployeeAccess::departmentId($leader) : NULL;
    if ($is_manager && (!$department || $vocabulary !== 'bc_staff_location')) {
      throw new AccessDeniedHttpException();
    }
    $form_state->set('bc_staff_scope_department', $department);
    $form['#attributes']['class'][] = 'bc-org-form';
    $form['#attached']['library'][] = 'bc_staff/organization';
    $allowed = [
      'bc_staff_department',
      'bc_staff_house',
      'bc_staff_location',
      'bc_staff_workgroup',
    ];
    if (!in_array($vocabulary, $allowed, TRUE)) {
      throw new NotFoundHttpException();
    }
    $editing = $term ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term) : NULL;
    if ($term !== NULL && (!$editing || $editing->bundle() !== $vocabulary)) {
      throw new NotFoundHttpException();
    }
    if ($is_manager && $editing
      && (string) ($editing->get('field_bc_staff_department')->target_id ?? '') !== $department) {
      throw new AccessDeniedHttpException();
    }
    $form['vocabulary'] = ['#type' => 'value', '#value' => $vocabulary];
    $form['term_id'] = ['#type' => 'value', '#value' => $term];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => $editing ? $editing->label() : '',
    ];
    $options = [0 => $this->t('Top level')];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary) as $item) {
      if ($editing && (int) $item->tid === (int) $editing->id()) {
        continue;
      }
      if ($is_manager) {
        $candidate = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($item->tid);
        if (!$candidate || (string) ($candidate->get('field_bc_staff_department')->target_id ?? '') !== $department) {
          continue;
        }
      }
      $options[$item->tid] = str_repeat('— ', (int) $item->depth) . $item->name;
    }
    if ($vocabulary === 'bc_staff_location') {
      $department_options = ['' => $this->t('Unassigned')];
      foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('bc_staff_department') as $item) {
        $department_options[$item->tid] = $item->name;
      }
      $form['department'] = [
        '#type' => 'select',
        '#title' => $this->t('Responsible department'),
        '#options' => $is_manager
          ? [$department => ($department_options[$department] ?? $this->t('Own department'))]
          : $department_options,
        '#default_value' => $is_manager
          ? $department : ($editing?->get('field_bc_staff_department')->target_id ?? ''),
        '#disabled' => $is_manager,
      ];
    }
    $form['parent'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent'),
      '#options' => $options,
      '#default_value' => $editing ? (int) ($editing->get('parent')->target_id ?? 0) : 0,
      '#description' => $this->t('Use a parent to represent a location within a building or room hierarchy.'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $editing ? $this->t('Save changes') : $this->t('Add entry'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $vocabulary = (string) $form_state->getValue('vocabulary');
    $id = (int) $form_state->getValue('term_id');
    $term = $id ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($id) : NULL;
    if ($id && (!$term || $term->bundle() !== $vocabulary)) {
      throw new NotFoundHttpException();
    }
    $term = $term ?: Term::create(['vid' => $vocabulary]);
    $scope = $form_state->get('bc_staff_scope_department');
    if ($scope) {
      $leader = \Drupal::entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
      if ($vocabulary !== 'bc_staff_location' || EmployeeAccess::departmentId($leader) !== $scope
        || ($id && (string) ($term->get('field_bc_staff_department')->target_id ?? '') !== $scope)) {
        throw new AccessDeniedHttpException();
      }
    }
    $parent = (int) $form_state->getValue('parent');
    // A term cannot become its own parent or be moved underneath a child.
    // Validate the complete ancestry server-side, not just the select options.
    if ($parent && $id) {
      $visited = [];
      $ancestor_id = $parent;
      while ($ancestor_id) {
        if ($ancestor_id === $id || isset($visited[$ancestor_id])) {
          $this->messenger()->addError($this->t('Invalid parent: circular organization hierarchy.'));
          return;
        }
        $visited[$ancestor_id] = TRUE;
        $ancestor = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($ancestor_id);
        if (!$ancestor || $ancestor->bundle() !== $vocabulary) {
          throw new AccessDeniedHttpException();
        }
        $ancestor_id = (int) ($ancestor->get('parent')->target_id ?? 0);
      }
    }
    if ($parent) {
      $parent_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($parent);
      if (!$parent_term || $parent_term->bundle() !== $vocabulary
        || ($scope && (string) ($parent_term->get('field_bc_staff_department')->target_id ?? '') !== $scope)) {
        throw new AccessDeniedHttpException();
      }
    }
    $term->setName(trim((string) $form_state->getValue('name')));
    if ($vocabulary === 'bc_staff_location' && $term->hasField('field_bc_staff_department')) {
      $department = $scope ?: $form_state->getValue('department');
      $selected = $department ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($department) : NULL;
      if ($department && (!$selected || $selected->bundle() !== 'bc_staff_department')) {
        throw new AccessDeniedHttpException();
      }
      $term->set('field_bc_staff_department', $department ?: NULL);
    }
    $term->set('parent', [(int) $form_state->getValue('parent')]);
    $term->save();
    $this->messenger()->addStatus($this->t('Organization entry saved.'));
    $form_state->setRedirect('bc_staff.organization');
  }

}
