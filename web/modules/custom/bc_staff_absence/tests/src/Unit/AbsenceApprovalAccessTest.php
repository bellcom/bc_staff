<?php

namespace Drupal\Tests\bc_staff_absence\Unit;

use Drupal\bc_staff_absence\Access\AbsenceApprovalAccess;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Entity\User;

/**
 * Tests absence approval authorization.
 *
 * @group bc_staff_absence
 */
final class AbsenceApprovalAccessTest extends UnitTestCase {

  /**
   * Ensures absent or inactive employees cannot be reviewed.
   */
  public function testInactiveEmployeeIsDenied(): void {
    $reviewer = $this->createMock(AccountInterface::class);
    $reviewer->method('id')->willReturn('1');
    $employee = $this->createMock(User::class);
    $employee->method('isActive')->willReturn(FALSE);
    $this->assertFalse(AbsenceApprovalAccess::canReview($reviewer, NULL, $employee));
    $this->assertFalse(AbsenceApprovalAccess::canReview($reviewer, NULL, NULL));
  }

  /**
   * Ensures unrelated employees cannot be reviewed by ordinary users.
   */
  public function testOrdinaryAccountIsDenied(): void {
    $reviewer = $this->createMock(AccountInterface::class);
    $reviewer->method('id')->willReturn('25');
    $reviewer->method('hasPermission')->willReturn(FALSE);
    $employee = $this->createMock(User::class);
    $employee->method('isActive')->willReturn(TRUE);
    $this->assertFalse(AbsenceApprovalAccess::canReview($reviewer, NULL, $employee));
  }

  /**
   * Verifies the special absence administration permission.
   */
  public function testGlobalReviewerPermission(): void {
    $reviewer = $this->createMock(AccountInterface::class);
    $reviewer->method('id')->willReturn('25');
    $reviewer->method('hasPermission')->willReturnCallback(static fn (string $permission): bool => $permission === 'manage all bc staff absences');
    $employee = $this->createMock(User::class);
    $employee->method('isActive')->willReturn(TRUE);
    $this->assertTrue(AbsenceApprovalAccess::canReview($reviewer, NULL, $employee));
  }

}
