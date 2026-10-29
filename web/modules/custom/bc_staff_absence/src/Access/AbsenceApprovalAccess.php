<?php

namespace Drupal\bc_staff_absence\Access;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

/**
 * Centralizes the authorization rules for vacation approvals.
 */
final class AbsenceApprovalAccess {

  /**
   * Determines whether the reviewer may decide for the given employee.
   */
  public static function canReview(AccountInterface $reviewer, ?User $manager, ?User $employee): bool {
    if (!$employee || !$employee->isActive()) {
      return FALSE;
    }
    if ($reviewer->id() === '1' || $reviewer->hasPermission('manage all bc staff absences')) {
      return TRUE;
    }
    return $reviewer->hasPermission('manage own bc staff department')
      && EmployeeAccess::canManage($manager, $employee);
  }

}
