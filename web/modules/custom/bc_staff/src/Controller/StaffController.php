<?php

namespace Drupal\bc_staff\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Displays staff presence cards.
 */
final class StaffController extends ControllerBase {

  /**
   * Constructs the overview controller.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $staffEntityTypeManager,
    private readonly ModuleHandlerInterface $staffModuleHandler,
    private readonly ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Renders the staff overview.
   */
  public function overview(): array {
    $demo_enabled = $this->staffModuleHandler->moduleExists('bc_staff_demo');
    $module_path = $this->moduleExtensionList->getPath('bc_staff');
    $avatar = base_path() . $module_path . '/assets/avatar.svg';
    $ids = $this->staffEntityTypeManager->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0, '>')
      ->sort('name')
      ->range(0, 500)
      ->execute();

    $staff = [];
    $departments = [];
    foreach ($this->staffEntityTypeManager->getStorage('user')->loadMultiple($ids) as $account) {
      $is_demo = $demo_enabled && $account->hasField('field_bc_staff_demo') && (bool) $account->get('field_bc_staff_demo')->value;
      if (!$account->isActive() && !$is_demo) {
        continue;
      }
      if (!$account->hasField('field_bc_staff_status') || $account->get('field_bc_staff_status')->isEmpty()) {
        continue;
      }
      $status_field = $account->get('field_bc_staff_status');
      $status_key = $status_field->value;
      $choices = $status_field->getFieldDefinition()->getSetting('allowed_values');
      $name = $account->hasField('field_bc_staff_display_name')
        ? ($account->get('field_bc_staff_display_name')->value ?: $account->getDisplayName())
        : $account->getDisplayName();
      $department = '';
      if ($account->hasField('field_bc_staff_department') && !$account->get('field_bc_staff_department')->isEmpty()) {
        $term = $account->get('field_bc_staff_department')->entity;
        $department = $term ? $term->label() : '';
      }
      if ($department !== '') {
        $departments[$department] = $department;
      }
      $staff[] = [
        'name' => $name,
        'is_me' => (int) $account->id() === (int) $this->currentUser()->id(),
        'profile_url' => ((int) $account->id() === (int) $this->currentUser()->id()
          || $this->currentUser()->hasPermission('administer users'))
          ? Url::fromRoute('entity.user.canonical', ['user' => $account->id()])->toString() : '',
        'department' => $department,
        'status_key' => $status_key,
        'status' => $choices[$status_key] ?? $this->t('Not specified'),
        'location' => $account->get('field_bc_staff_location')->value ?: '',
      ];
    }
    natcasesort($departments);
    $my_name = '';
    if ($this->currentUser()->isAuthenticated()) {
      $current = $this->staffEntityTypeManager->getStorage('user')->load($this->currentUser()->id());
      if ($current) {
        $my_name = $current->hasField('field_bc_staff_display_name')
          && !$current->get('field_bc_staff_display_name')->isEmpty()
            ? $current->get('field_bc_staff_display_name')->value
            : $current->getDisplayName();
      }
    }
    return [
      '#theme' => 'bc_staff_dashboard',
      '#staff' => $staff,
      '#departments' => array_values($departments),
      '#avatar' => $avatar,
      '#my_name' => $my_name,
      '#my_profile_url' => $this->currentUser()->isAuthenticated()
        ? Url::fromRoute('entity.user.canonical', ['user' => $this->currentUser()->id()])->toString()
        : '',
      '#status_url' => $this->currentUser()->hasPermission('update own bc staff status')
        ? Url::fromRoute('bc_staff.my_status')->toString() : '',
      '#employees_url' => ($this->currentUser()->hasPermission('administer bc staff employees')
        || $this->currentUser()->hasPermission('manage own bc staff department'))
        ? Url::fromRoute('bc_staff.employee_list')->toString() : '',
      '#demo_url' => $demo_enabled && $this->currentUser()->hasPermission('administer users')
        ? Url::fromRoute('bc_staff_demo.admin')->toString() : '',
      '#attached' => ['library' => ['bc_staff/dashboard']],
      '#cache' => ['max-age' => 0],
    ];
  }

}
