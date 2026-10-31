<?php

namespace Drupal\bc_staff_absence\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Registers an absence request for the current employee.
 */
final class AbsenceForm extends FormBase {

  public function __construct(
    private readonly Connection $staffDb,
    private readonly TimeInterface $staffTime,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('datetime.time'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bc_staff_absence_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'bc-staff-absence-form';
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Absence type'),
      '#required' => TRUE,
      '#options' => [
        'vacation' => $this->t('Vacation'),
        'course' => $this->t('Course'),
        'child_sick' => $this->t('Child sick day'),
        'sick' => $this->t('Sickness'),
        'day_off' => $this->t('Day off'),
        'leave' => $this->t('Leave of absence'),
      ],
    ];
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start date'),
      '#required' => TRUE,
    ];
    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End date'),
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register absence'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $start = (string) $form_state->getValue('start_date');
    $end = (string) $form_state->getValue('end_date');
    if ($start > $end) {
      $form_state->setErrorByName('end_date', $this->t('The end date must not precede the start date.'));
      return;
    }
    // Prevent duplicate and conflicting live registrations.
    $query = $this->staffDb->select('bc_staff_absence', 'a');
    $conflicts = $query->condition('uid', (int) $this->currentUser()->id())
      ->condition('state', ['pending', 'approved', 'registered'], 'IN')
      ->condition('start_date', $end, '<=')
      ->condition('end_date', $start, '>=')
      ->countQuery()->execute()->fetchField();
    if ($conflicts) {
      $form_state->setErrorByName('start_date', $this->t('You already have an absence covering one or more of these dates.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $type = (string) $form_state->getValue('type');
    $now = $this->staffTime->getRequestTime();
    $this->staffDb->insert('bc_staff_absence')
      ->fields([
        'uid' => (int) $this->currentUser()->id(),
        'type' => $type,
        'start_date' => $form_state->getValue('start_date'),
        'end_date' => $form_state->getValue('end_date'),
        'state' => $type === 'vacation' ? 'pending' : 'registered',
        'created' => $now,
        'changed' => $now,
      ])->execute();
    $this->messenger()->addStatus($this->t('Absence registered.'));
    $form_state->setRedirect('bc_staff_absence.list');
  }

}
