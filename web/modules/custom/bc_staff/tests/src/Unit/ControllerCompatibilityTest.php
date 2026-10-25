<?php

namespace Drupal\Tests\bc_staff\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Ensures BC Staff classes remain compatible with Drupal core inheritance.
 *
 * @group bc_staff
 */
final class ControllerCompatibilityTest extends UnitTestCase {

  /**
   * Checks that controllers and forms can be loaded without PHP fatal errors.
   */
  public function testClassesLoad(): void {
    foreach ([
      \Drupal\bc_staff\Controller\StaffController::class,
      \Drupal\bc_staff\Controller\StaffLoginController::class,
      \Drupal\bc_staff\Controller\EmployeeAdminController::class,
      \Drupal\bc_staff\Form\EmployeeForm::class,
      \Drupal\bc_staff\Form\MyStatusForm::class,
    ] as $class) {
      $this->assertTrue(class_exists($class), sprintf('%s must be loadable.', $class));
    }
  }

}
