<?php

namespace Drupal\Tests\bc_staff_absence\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\bc_staff_absence\Controller\AbsenceController;
use Drupal\bc_staff_absence\Controller\AbsenceCalendarController;
use Drupal\bc_staff_absence\Form\AbsenceDecisionForm;
use Drupal\bc_staff_absence\Form\AbsenceForm;

/**
 * Tests absence workflow class compatibility with Drupal core.
 *
 * @group bc_staff_absence
 */
final class AbsenceClassesTest extends UnitTestCase {

  /**
   * Confirms controllers and forms can be autoloaded.
   */
  public function testClassesLoad(): void {
    foreach ([AbsenceController::class, AbsenceCalendarController::class, AbsenceDecisionForm::class, AbsenceForm::class] as $class) {
      $this->assertTrue(class_exists($class), sprintf('%s should be loadable.', $class));
    }
  }

}
