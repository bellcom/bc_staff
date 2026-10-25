<?php

namespace Drupal\bc_staff\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

/**
 * Server-side access control for BC Staff employee administration.
 */
final class EmployeeAccess {

  public static function access(AccountInterface $account): AccessResult {
    if ($account->id() === '1' || $account->hasPermission('administer bc staff employees')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    if (!$account->hasPermission('manage own bc staff department')) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    $leader = User::load($account->id());
    $department = self::departmentId($leader);
    if (!$department) {
      return AccessResult::forbidden()->cachePerUser();
    }

    $target = \Drupal::routeMatch()->getParameter('user');
    if ($target !== NULL) {
      $employee = $target instanceof User ? $target : User::load(is_object($target) ? $target->id() : $target);
      if (!$employee || !self::canManage($leader, $employee)) {
        return AccessResult::forbidden()->cachePerUser();
      }
    }
    return AccessResult::allowed()->cachePerUser();
  }

  public static function departmentId(?User $user): ?string {
    if (!$user || !$user->hasField('field_bc_staff_department') || $user->get('field_bc_staff_department')->isEmpty()) {
      return NULL;
    }
    return (string) $user->get('field_bc_staff_department')->target_id;
  }

  public static function canManage(?User $leader, ?User $target): bool {
    if (!$leader || !$target || (int) $target->id() === 1
      || $target->hasRole('administrator') || $target->hasRole('bc_staff_admin')
      || $target->hasRole('bc_staff_manager') || $leader->id() === $target->id()) {
      return FALSE;
    }
    return self::departmentId($leader) !== NULL
      && self::departmentId($leader) === self::departmentId($target);
  }

}
