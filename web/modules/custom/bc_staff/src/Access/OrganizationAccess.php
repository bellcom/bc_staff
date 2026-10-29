<?php

namespace Drupal\bc_staff\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Limits organization management to an admin or a manager's own locations.
 */
final class OrganizationAccess {

  /**
   * Checks access to the organization overview and term forms.
   */
  public static function access(AccountInterface $account): AccessResult {
    if ($account->id() === '1' || $account->hasPermission('administer bc staff employees')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if (!$account->hasPermission('manage own bc staff department')) {
      return AccessResult::forbidden()->cachePerPermissions();
    }
    $leader = \Drupal::entityTypeManager()->getStorage('user')->load($account->id());
    $department = EmployeeAccess::departmentId($leader);
    if (!$department) {
      return AccessResult::forbidden()->cachePerUser();
    }
    $route = \Drupal::routeMatch();
    $vocabulary = $route->getParameter('vocabulary');
    if ($vocabulary !== NULL && $vocabulary !== 'bc_staff_location') {
      return AccessResult::forbidden()->cachePerUser();
    }
    $id = $route->getParameter('term');
    if ($id !== NULL) {
      $term = $id instanceof Term ? $id : \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($id);
      if (!$term || $term->bundle() !== 'bc_staff_location'
        || !$term->hasField('field_bc_staff_department')
        || (string) ($term->get('field_bc_staff_department')->target_id ?? '') !== $department) {
        return AccessResult::forbidden()->cachePerUser();
      }
    }
    return AccessResult::allowed()->cachePerUser();
  }

}
