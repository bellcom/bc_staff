<?php

namespace Drupal\Tests\bc_staff\Unit;

use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Entity\User;

/**
 * Tests department-scoped employee access rules.
 *
 * @coversDefaultClass \Drupal\bc_staff\Access\EmployeeAccess
 *
 * @group bc_staff
 */
final class EmployeeAccessTest extends UnitTestCase {

  /**
   * Tests that managers can only manage employees in their department.
   */
  public function testDepartmentScope(): void {
    $manager = $this->mockUser(10, 'north');
    $same_department = $this->mockUser(11, 'north');
    $other_department = $this->mockUser(12, 'south');
    $no_department = $this->mockUser(13, NULL);

    $this->assertTrue(EmployeeAccess::canManage($manager, $same_department));
    $this->assertFalse(EmployeeAccess::canManage($manager, $other_department));
    $this->assertFalse(EmployeeAccess::canManage($manager, $no_department));
    $this->assertFalse(EmployeeAccess::canManage($manager, $manager));
    $this->assertFalse(EmployeeAccess::canManage($this->mockUser(14, NULL), $same_department));
  }

  /**
   * Tests privileged and protected accounts cannot be managed.
   */
  public function testProtectedAccounts(): void {
    $manager = $this->mockUser(10, 'north');
    $this->assertFalse(EmployeeAccess::canManage($manager, $this->mockUser(1, 'north')));
    foreach (['administrator', 'bc_staff_admin', 'bc_staff_manager'] as $role) {
      $this->assertFalse(EmployeeAccess::canManage(
        $manager,
        $this->mockUser(11, 'north', [$role]),
      ));
    }
  }

  /**
   * Creates a user mock with a department and optional roles.
   *
   * @param int $uid
   *   User ID.
   * @param string|null $department
   *   Department ID, or NULL.
   * @param string[] $roles
   *   Roles assigned to the mocked user.
   *
   * @return \Drupal\user\Entity\User
   *   Mocked user.
   */
  private function mockUser(int $uid, ?string $department, array $roles = []): User {
    $user = $this->createMock(User::class);
    $user->method('id')->willReturn((string) $uid);
    $user->method('hasRole')->willReturnCallback(
      static fn (string $role): bool => in_array($role, $roles, TRUE),
    );
    $user->method('hasField')->willReturn(TRUE);
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn($department === NULL);
    $field->method('__get')->willReturnCallback(
      static fn (string $name): ?string => $name === 'target_id' ? $department : NULL,
    );
    $user->method('get')->willReturn($field);
    return $user;
  }

}
