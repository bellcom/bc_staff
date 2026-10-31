<?php

namespace Drupal\bc_staff_absence\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\bc_staff_absence\Access\AbsenceApprovalAccess;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Restricts each vacation decision to the correct manager.
 */
final class AbsenceDecisionForm extends FormBase {

  public function __construct(
    private readonly Connection $staffDb,
    private readonly EntityTypeManagerInterface $staffEntities,
    private readonly TimeInterface $staffTime,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'), $container->get('entity_type.manager'), $container->get('datetime.time'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bc_staff_absence_decision_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $absence_id = NULL): array {
    $form['absence_id'] = ['#type' => 'value', '#value' => $absence_id];
    $form['approve'] = ['#type' => 'submit', '#value' => $this->t('Approve'), '#name' => 'approve'];
    $form['reject'] = ['#type' => 'submit', '#value' => $this->t('Reject'), '#name' => 'reject'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (int) $form_state->getValue('absence_id');
    $record = $this->staffDb->select('bc_staff_absence', 'a')
      ->fields('a', ['uid', 'state', 'type'])
      ->condition('id', $id)
      ->execute()->fetchObject();
    $account = $this->currentUser();
    $manager = $this->staffEntities->getStorage('user')->load($account->id());
    $employee = $record ? $this->staffEntities->getStorage('user')->load($record->uid) : NULL;
    if (!$record || !$employee || $record->type !== 'vacation' || $record->state !== 'pending'
      || !AbsenceApprovalAccess::canReview($account, $manager, $employee)) {
      throw new AccessDeniedHttpException();
    }
    $decision = $form_state->getTriggeringElement()['#name'] === 'approve' ? 'approved' : 'rejected';
    $updated = $this->staffDb->update('bc_staff_absence')
      ->fields([
        'state' => $decision,
        'reviewed_by' => (int) $account->id(),
        'changed' => $this->staffTime->getRequestTime(),
      ])
      ->condition('id', $id)
      ->condition('state', 'pending')
      ->execute();
    if ($updated && $this->staffDb->schema()->tableExists('bc_staff_absence_audit')) {
      $this->staffDb->insert('bc_staff_absence_audit')->fields([
        'absence_id' => $id,
        'actor_uid' => (int) $account->id(),
        'action' => 'vacation_' . $decision,
        'details' => '{}',
        'created' => $this->staffTime->getRequestTime(),
      ])->execute();
    }
    $this->messenger()->addStatus($this->t('Request updated.'));
    $form_state->setRedirect('bc_staff_absence.review');
  }

}
