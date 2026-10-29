<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Form\FormBase;
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
    $editing = $term ? $this->entityTypeManager()->getStorage('taxonomy_term')->load($term) : NULL;
    if ($term !== NULL && (!$editing || $editing->bundle() !== $vocabulary)) {
      throw new NotFoundHttpException();
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
    foreach ($this->entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary) as $item) {
      if ($editing && (int) $item->tid === (int) $editing->id()) {
        continue;
      }
      $options[$item->tid] = str_repeat('— ', (int) $item->depth) . $item->name;
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
    $term = $id ? $this->entityTypeManager()->getStorage('taxonomy_term')->load($id) : NULL;
    if ($id && (!$term || $term->bundle() !== $vocabulary)) {
      throw new NotFoundHttpException();
    }
    $term = $term ?: Term::create(['vid' => $vocabulary]);
    $term->setName(trim((string) $form_state->getValue('name')));
    $term->set('parent', [(int) $form_state->getValue('parent')]);
    $term->save();
    $this->messenger()->addStatus($this->t('Organization entry saved.'));
    $form_state->setRedirect('bc_staff.organization');
  }

}
