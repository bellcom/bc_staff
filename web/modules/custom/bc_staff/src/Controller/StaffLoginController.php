<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\user\Form\UserLoginForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the BC Staff login experience using Drupal authentication.
 */
final class StaffLoginController extends ControllerBase {

  /**
   * Constructs the BC Staff login controller.
   */
  public function __construct(
    private readonly FormBuilderInterface $staffFormBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('form_builder'));
  }

  /**
   * Renders the staff login page.
   */
  public function page(): array {
    if ($this->currentUser()->isAuthenticated()) {
      return [
        'intro' => [
          '#markup' => '<p>' . $this->t('You are already signed in to BC Staff.') . '</p>',
        ],
        'mobile' => [
          '#type' => 'link',
          '#title' => $this->t('My status'),
          '#url' => Url::fromRoute('bc_staff.mobile'),
        ],
        'overview' => [
          '#type' => 'link',
          '#title' => $this->t('Staff overview'),
          '#url' => Url::fromRoute('bc_staff.overview'),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bc-staff-login']],
      'title' => [
        '#markup' => '<h2>' . $this->t('Welcome to BC Staff') . '</h2>'
        . '<p>' . $this->t('Sign in with your employee account.') . '</p>',
      ],
      'login' => $this->staffFormBuilder->getForm(UserLoginForm::class),
      '#attached' => ['library' => ['bc_staff/login']],
      '#cache' => [
        'contexts' => ['user'],
        'max-age' => 0,
      ],
    ];
  }

}
