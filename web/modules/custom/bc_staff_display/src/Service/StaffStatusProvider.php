<?php

namespace Drupal\bc_staff_display\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides staff status values for authorized display consumers.
 */
final class StaffStatusProvider {

  /**
   * Constructs the status provider.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $staffEntityTypeManager,
  ) {}

  /**
   * Gets the minimal status payload, excluding inactive and demo accounts.
   */
  public function getStaff(): array {
    $storage = $this->staffEntityTypeManager->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->condition('status', 1)
      ->sort('name')
      ->execute();
    $staff = [];
    foreach ($storage->loadMultiple($ids) as $account) {
      if (!$account->hasField('field_bc_staff_status')
        || $account->get('field_bc_staff_status')->isEmpty()
        || ($account->hasField('field_bc_staff_demo') && (bool) $account->get('field_bc_staff_demo')->value)) {
        continue;
      }
      $field = $account->get('field_bc_staff_status');
      $status = (string) $field->value;
      $allowed = $field->getFieldDefinition()->getSetting('allowed_values');
      if (!isset($allowed[$status])) {
        continue;
      }
      $name = $account->getDisplayName();
      if ($account->hasField('field_bc_staff_display_name')
        && !$account->get('field_bc_staff_display_name')->isEmpty()) {
        $name = (string) $account->get('field_bc_staff_display_name')->value;
      }
      $department = '';
      if ($account->hasField('field_bc_staff_department')
        && !$account->get('field_bc_staff_department')->isEmpty()) {
        $term = $account->get('field_bc_staff_department')->entity;
        $department = $term ? (string) $term->label() : '';
      }
      $location = '';
      if ($account->hasField('field_bc_staff_location')
        && !$account->get('field_bc_staff_location')->isEmpty()) {
        $location = (string) $account->get('field_bc_staff_location')->value;
      }
      $staff[] = [
        'name' => $name,
        'department' => $department,
        'status' => $status,
        'status_label' => (string) $allowed[$status],
        'location' => $location,
      ];
    }
    return $staff;
  }

}
