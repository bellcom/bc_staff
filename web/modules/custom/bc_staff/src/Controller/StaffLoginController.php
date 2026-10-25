<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Form\UserLoginForm;

/**
 * BC Staff-branded login screen using Drupal core authentication.
 */
final class StaffLoginController extends ControllerBase {

  public function page(): array {
    if ($this->currentUser()->isAuthenticated()) {
      return [
        'intro' => ['#markup' => '<p>Du er allerede logget ind i BC Staff.</p>'],
        'mobile' => ['#type' => 'link', '#title' => $this->t('Min status'), '#url' => \Drupal\Core\Url::fromRoute('bc_staff.mobile')],
        'overview' => ['#type' => 'link', '#title' => $this->t('Medarbejderoversigt'), '#url' => \Drupal\Core\Url::fromRoute('bc_staff.overview'), '#prefix' => '<p>', '#suffix' => '</p>'],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['bc-staff-login']],
      'title' => ['#markup' => '<h2>Velkommen til BC Staff</h2><p>Log ind med din medarbejderkonto.</p>'],
      'login' => \Drupal::formBuilder()->getForm(UserLoginForm::class),
      '#attached' => ['library' => ['bc_staff/login']],
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
  }

}
