<?php

namespace Drupal\Tests\bc_staff\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\bc_staff\Controller\StaffController;
use Drupal\bc_staff\Controller\StaffLoginController;
use Drupal\bc_staff\Controller\EmployeeAdminController;
use Drupal\bc_staff\Form\EmployeeForm;
use Drupal\bc_staff\Form\MyStatusForm;

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
      StaffController::class,
      StaffLoginController::class,
      EmployeeAdminController::class,
      EmployeeForm::class,
      MyStatusForm::class,
    ] as $class) {
      $this->assertTrue(class_exists($class), sprintf('%s must be loadable.', $class));
    }
  }

}
