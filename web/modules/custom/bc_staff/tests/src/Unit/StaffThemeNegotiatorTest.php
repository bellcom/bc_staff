<?php

namespace Drupal\Tests\bc_staff\Unit;

use Drupal\bc_staff\Theme\StaffThemeNegotiator;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests theme negotiation on staff routes.
 *
 * @group bc_staff
 */
final class StaffThemeNegotiatorTest extends UnitTestCase {

  /**
   * Tests staff-only theme selection with the Drupal theme handler interface.
   */
  public function testThemeSelection(): void {
    $handler = $this->createMock(ThemeHandlerInterface::class);
    $handler->method('themeExists')->willReturn(TRUE);
    $negotiator = new StaffThemeNegotiator($handler);

    $staff_route = $this->createMock(RouteMatchInterface::class);
    $staff_route->method('getRouteName')->willReturn('bc_staff.overview');
    $this->assertTrue($negotiator->applies($staff_route));
    $this->assertSame('bc_staff_frontend', $negotiator->determineActiveTheme($staff_route));

    $admin_route = $this->createMock(RouteMatchInterface::class);
    $admin_route->method('getRouteName')->willReturn('system.admin');
    $this->assertFalse($negotiator->applies($admin_route));
  }

}
