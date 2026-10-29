<?php

namespace Drupal\bc_staff_display\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\bc_staff_display\Service\StaffStatusProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides the POC staff display and a restricted JSON feed.
 */
final class StaffDisplayController extends ControllerBase {

  /**
   * Constructs the display controller.
   */
  public function __construct(
    private readonly StaffStatusProvider $statusProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('bc_staff_display.status_provider'));
  }

  /**
   * Returns status data to accounts with the display permission.
   */
  public function feed(): JsonResponse {
    $response = new JsonResponse([
      'version' => 1,
      'staff' => $this->statusProvider->getStaff(),
    ]);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  /**
   * Renders the information-screen POC.
   */
  public function screen(): array {
    $rows = [];
    foreach ($this->statusProvider->getStaff() as $person) {
      $rows[] = [
        $person['name'],
        $person['department'],
        $person['status_label'],
        $person['location'],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bc-staff-display']],
      'intro' => [
        '#plain_text' => $this->t('Staff presence display'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Employee'),
          $this->t('Department'),
          $this->t('Status'),
          $this->t('Location'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('No staff to display.'),
      ],
      '#attached' => ['library' => ['bc_staff_display/screen']],
      '#cache' => ['max-age' => 0],
    ];
  }

}
