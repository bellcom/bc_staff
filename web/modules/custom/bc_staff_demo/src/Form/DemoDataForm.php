<?php

namespace Drupal\bc_staff_demo\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\bc_staff_demo\Service\DemoAbsenceSeeder;
use Drupal\bc_staff_demo\Service\DemoOrganization;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds or refreshes fictional, blocked demo accounts.
 */
final class DemoDataForm extends ConfirmFormBase {

  /**
   * Constructs the demo data confirmation form.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $staffEntityTypeManager,
    private readonly ModuleHandlerInterface $staffModuleHandler,
    private readonly DemoAbsenceSeeder $demoAbsenceSeeder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('bc_staff_demo.absence_seeder'),
    );
  }

  /**
   * Returns the form ID.
   */
  public function getFormId(): string {
    return 'bc_staff_demo_data_form';
  }

  /**
   * Returns the confirmation question.
   */
  public function getQuestion() {
    return $this->t('Create or refresh fictional BC Staff demo data?');
  }

  /**
   * Returns the confirmation description.
   */
  public function getDescription() {
    return $this->t('Creates four departments, demo houses, workgroups and locations, twelve blocked display accounts and, when BC Staff Absence is enabled, eight active demonstration accounts with vacation and absence scenarios. Existing non-demo accounts remain unchanged.');
  }

  /**
   * Returns the cancel URL.
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('bc_staff.overview');
  }

  /**
   * Submits the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $names = [
      'Næstved – Administration',
      'Næstved – North Team',
      'Roskilde – Administration',
      'Roskilde – South Team',
    ];
    $term_storage = $this->staffEntityTypeManager->getStorage('taxonomy_term');
    $departments = [];
    foreach ($names as $name) {
      $existing = $term_storage->loadByProperties(['vid' => 'bc_staff_department', 'name' => $name]);
      $term = reset($existing);
      if (!$term) {
        $term = $term_storage->create(['vid' => 'bc_staff_department', 'name' => $name]);
        $term->save();
      }
      $departments[$name] = $term->id();
    }

    $locations = [
      'Reception',
      'Meeting room 1',
      'Meeting room 2',
      'Staff room',
      'Office 2',
      'Office 3',
      'Home',
      'Out of office',
    ];
    foreach ($locations as $location_name) {
      $existing_location = $term_storage->loadByProperties([
        'vid' => 'bc_staff_location',
        'name' => $location_name,
      ]);
      if (!$existing_location) {
        $term_storage->create([
          'vid' => 'bc_staff_location',
          'name' => $location_name,
        ])->save();
      }
    }

    $organization = DemoOrganization::seed($this->staffEntityTypeManager, $departments);
    $people = [
      ['Anna Madsen', 'present', 'Næstved – Administration', 'Reception'],
      ['Peter Holm', 'meeting', 'Næstved – Administration', 'Meeting room 1'],
      ['Sofie Lund', 'break', 'Næstved – Administration', 'Staff room'],
      ['Mikkel Andersen', 'away', 'Næstved – North Team', 'Out of office'],
      ['Emma Nielsen', 'present', 'Næstved – North Team', 'Office 2'],
      ['Jonas Pedersen', 'remote', 'Næstved – North Team', 'Home'],
      ['Maria Jensen', 'present', 'Roskilde – Administration', 'Reception'],
      ['Lars Sørensen', 'meeting', 'Roskilde – Administration', 'Meeting room 2'],
      ['Clara Rasmussen', 'absent', 'Roskilde – Administration', ''],
      ['Oliver Kristensen', 'present', 'Roskilde – South Team', 'Office 3'],
      ['Freja Mortensen', 'break', 'Roskilde – South Team', 'Staff room'],
      ['Noah Christensen', 'remote', 'Roskilde – South Team', 'Home'],
    ];

    $storage = $this->staffEntityTypeManager->getStorage('user');
    $created = 0;
    $updated = 0;
    foreach ($people as $i => [$name, $status, $department, $location]) {
      $username = sprintf('bc_demo_%02d', $i + 1);
      $matches = $storage->loadByProperties(['name' => $username]);
      $account = reset($matches);
      if ($account && (!$account->hasField('field_bc_staff_demo')
        || !$account->get('field_bc_staff_demo')->value)) {
        $this->messenger()->addWarning($this->t('Skipping reserved username @name.', ['@name' => $username]));
        continue;
      }
      if (!$account) {
        $account = $storage->create([
          'name' => $username,
          'mail' => $username . '@example.invalid',
          'pass' => bin2hex(random_bytes(24)),
          'status' => 0,
        ]);
        $created++;
      }
      else {
        $updated++;
      }
      // Keep existing demo account states; new accounts remain blocked.
      if ($account->isActive()) {
        $account->addRole('bc_staff_employee');
      }
      $account->set('field_bc_staff_demo', 1);
      $account->set('field_bc_staff_display_name', $name);
      $account->set('field_bc_staff_status', $status);
      $account->set('field_bc_staff_location', $location);
      $account->set('field_bc_staff_department', ['target_id' => $departments[$department]]);
      DemoOrganization::assign($account, $department, $organization, $i);
      $account->save();
    }
    if ($this->staffModuleHandler->moduleExists('bc_staff_absence')) {
      $counts = $this->demoAbsenceSeeder->seed($departments, $organization);
      $this->messenger()->addStatus($this->t('Absence scenarios: @users new active demo accounts, @absences new records, @skipped reserved names skipped.', [
        '@users' => $counts['users'],
        '@absences' => $counts['absences'],
        '@skipped' => $counts['skipped'],
      ]));
    }
    else {
      $this->messenger()->addWarning($this->t('Enable BC Staff Absence to generate vacation and absence scenarios.'));
    }
    $this->messenger()->addStatus($this->t('Demo data ready: @new new employees and @updated updated employees.', [
      '@new' => $created,
      '@updated' => $updated,
    ]));
    $form_state->setRedirect('bc_staff.overview');
  }

}
