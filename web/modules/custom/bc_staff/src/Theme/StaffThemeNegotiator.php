<?php

namespace Drupal\bc_staff\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Selects the isolated staff interface on BC Staff routes only.
 */
final class StaffThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * Constructs the staff theme negotiator.
   */
  public function __construct(
    private readonly ThemeHandlerInterface $staffThemeHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    $route_name = $route_match->getRouteName();
    return $route_name !== NULL
      && (str_starts_with($route_name, 'bc_staff.') || str_starts_with($route_name, 'bc_staff_absence.') || str_starts_with($route_name, 'bc_staff_display.'))
      && $this->staffThemeHandler->themeExists('bc_staff_frontend');
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {
    return 'bc_staff_frontend';
  }

}
