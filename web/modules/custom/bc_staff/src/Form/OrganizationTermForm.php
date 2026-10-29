<?php

namespace Drupal\bc_staff\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

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
  public function buildForm(array $form, FormStateInterface $form_state, ?string $vocabulary = NULL): array {
    $allowed = [
      'bc_staff_department',
      'bc_staff_house',
      'bc_staff_location',
      'bc_staff_workgroup',
    ];
    if (!in_array($vocabulary, $allowed, TRUE)) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $form['vocabulary'] = ['#type' => 'value', '#value' => $vocabulary];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];
    $options = [0 => $this->t('Top level')];
    foreach ($this->entityTypeManager()->getStorage('taxonomy_term')->loadTree($vocabulary) as $term) {
      $options[$term->tid] = str_repeat('— ', (int) $term->depth) . $term->name;
    }
    $form['parent'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent'),
      '#options' => $options,
      '#default_value' => 0,
      '#description' => $this->t('Use a parent to represent a location within a building or room hierarchy.'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add entry'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $vocabulary = (string) $form_state->getValue('vocabulary');
    $term = Term::create([
      'vid' => $vocabulary,
      'name' => trim((string) $form_state->getValue('name')),
      'parent' => [(int) $form_state->getValue('parent')],
    ]);
    $term->save();
    $this->messenger()->addStatus($this->t('Organization entry created.'));
    $form_state->setRedirect('bc_staff.organization');
  }

}
