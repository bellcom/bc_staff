<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\bc_staff\Access\EmployeeAccess;
use Drupal\bc_staff\Utility\StaffPhoto;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * BC Staff employee administration.
 */
final class EmployeeAdminController extends ControllerBase {

  public function __construct(
    private readonly FileUrlGeneratorInterface $photoUrls,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('file_url_generator'));
  }

  /**
   * Handles listing.
   */
  public function listing(): array {
    $employees = [];
    $fallback = base_path() . $this->moduleHandler()->getModule('bc_staff')->getPath() . '/assets/avatar.svg';
    $departments = [];
    $houses = [];
    $workgroups = [];
    $statuses = [];
    $storage = $this->entityTypeManager()->getStorage('user');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)->condition('uid', 0, '>')->sort('name')->execute();
    $manager = $this->currentUser()->hasPermission('manage own bc staff department')
      && !$this->currentUser()->hasPermission('administer bc staff employees');
    $leader = $manager ? $storage->load($this->currentUser()->id()) : NULL;
    foreach ($storage->loadMultiple($ids) as $user) {
      if ($manager && !EmployeeAccess::canManage($leader, $user)) {
        continue;
      }
      if (!$manager && ((int) $user->id() === 1 || $user->hasRole('administrator'))) {
        continue;
      }
      if (!$user->hasField('field_bc_staff_status')) {
        continue;
      }
      $name = $user->hasField('field_bc_staff_display_name') && !$user->get('field_bc_staff_display_name')->isEmpty()
        ? $user->get('field_bc_staff_display_name')->value : $user->getAccountName();
      $department = '—';
      if ($user->hasField('field_bc_staff_department') && !$user->get('field_bc_staff_department')->isEmpty()) {
        $term = $user->get('field_bc_staff_department')->entity;
        $department = $term ? $term->label() : '—';
      }
      $house = '';
      if ($user->hasField('field_bc_staff_house') && !$user->get('field_bc_staff_house')->isEmpty()) {
        $house = $user->get('field_bc_staff_house')->entity?->label() ?? '';
      }
      $groups = [];
      if ($user->hasField('field_bc_staff_workgroups')) {
        foreach ($user->get('field_bc_staff_workgroups')->referencedEntities() as $group) {
          $groups[] = $group->label();
        }
      }
      $departments[$department] = $department;
      if ($house !== '') {
        $houses[$house] = $house;
      }
      foreach ($groups as $group) {
        $workgroups[$group] = $group;
      }
      $status_key = $user->hasField('field_bc_staff_status')
        ? (string) ($user->get('field_bc_staff_status')->value ?? '') : '';
      $choices = $user->get('field_bc_staff_status')->getFieldDefinition()->getSetting('allowed_values');
      foreach ($choices as $key => $label) {
        $statuses[$key] = ['key' => $key, 'label' => $label];
      }
      $employees[] = [
        'photo_url' => StaffPhoto::url($user, $this->photoUrls, $fallback),
        'name' => $name,
        'login' => $user->getAccountName(),
        'email' => str_ends_with((string) $user->getEmail(), '@example.invalid')
          ? '' : (string) $user->getEmail(),
        'department' => $department,
        'house' => $house,
        'groups' => $groups,
        'active' => $user->isActive(),
        'status_key' => $status_key,
        'status' => $choices[$status_key] ?? $this->t('Not specified'),
        'location' => $user->hasField('field_bc_staff_location')
          ? (string) ($user->get('field_bc_staff_location')->value ?? '') : '',
        'edit_url' => Url::fromRoute('bc_staff.employee_edit', [
          'user' => $user->id(),
        ])->toString(),
      ];
    }
    natcasesort($departments);
    natcasesort($houses);
    natcasesort($workgroups);
    return [
      '#theme' => 'bc_staff_employee_admin',
      '#employees' => $employees,
      '#departments' => array_values($departments),
      '#houses' => array_values($houses),
      '#workgroups' => array_values($workgroups),
      '#statuses' => array_values($statuses),
      '#add_url' => Url::fromRoute('bc_staff.employee_add')->toString(),
      '#attached' => ['library' => ['bc_staff/employee_admin']],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

}
