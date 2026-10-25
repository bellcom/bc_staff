<?php

namespace Drupal\bc_staff_demo\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Adds or refreshes fictional, blocked demo accounts.
 */
final class DemoDataForm extends ConfirmFormBase {

  public function getFormId(): string {
    return 'bc_staff_demo_data_form';
  }

  public function getQuestion() {
    return $this->t('Opret eller opdater fiktive BC Staff-testdata?');
  }

  public function getDescription() {
    return $this->t('Opretter 4 afdelinger og 12 blokerede testkonti. Kun konti med navne, der starter med bc_demo_, ændres. Eksisterende konti med samme navn og uden demo-markering ændres ikke.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('bc_staff.overview');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $names = [
      'Næstved – Administration',
      'Næstved – Team Nord',
      'Roskilde – Administration',
      'Roskilde – Team Syd',
    ];
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $departments = [];
    foreach ($names as $name) {
      $existing = $term_storage->loadByProperties(['vid' => 'bc_staff_department', 'name' => $name]);
      $term = reset($existing);
      if (!$term) {
        $term = Term::create(['vid' => 'bc_staff_department', 'name' => $name]);
        $term->save();
      }
      $departments[$name] = $term->id();
    }

    $locations = [
      'Reception', 'Møderum 1', 'Møderum 2', 'Personalerum',
      'Kontor 2', 'Kontor 3', 'Hjemme', 'Ude af huset',
    ];
    foreach ($locations as $location_name) {
      $existing_location = $term_storage->loadByProperties([
        'vid' => 'bc_staff_location',
        'name' => $location_name,
      ]);
      if (!$existing_location) {
        Term::create([
          'vid' => 'bc_staff_location',
          'name' => $location_name,
        ])->save();
      }
    }

    $people = [
      ['Anna Madsen', 'present', 'Næstved – Administration', 'Reception'],
      ['Peter Holm', 'meeting', 'Næstved – Administration', 'Møderum 1'],
      ['Sofie Lund', 'break', 'Næstved – Administration', 'Personalerum'],
      ['Mikkel Andersen', 'away', 'Næstved – Team Nord', 'Ude af huset'],
      ['Emma Nielsen', 'present', 'Næstved – Team Nord', 'Kontor 2'],
      ['Jonas Pedersen', 'remote', 'Næstved – Team Nord', 'Hjemme'],
      ['Maria Jensen', 'present', 'Roskilde – Administration', 'Reception'],
      ['Lars Sørensen', 'meeting', 'Roskilde – Administration', 'Møderum 2'],
      ['Clara Rasmussen', 'absent', 'Roskilde – Administration', ''],
      ['Oliver Kristensen', 'present', 'Roskilde – Team Syd', 'Kontor 3'],
      ['Freja Mortensen', 'break', 'Roskilde – Team Syd', 'Personalerum'],
      ['Noah Christensen', 'remote', 'Roskilde – Team Syd', 'Hjemme'],
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $created = 0;
    $updated = 0;
    foreach ($people as $i => [$name, $status, $department, $location]) {
      $username = sprintf('bc_demo_%02d', $i + 1);
      $matches = $storage->loadByProperties(['name' => $username]);
      $account = reset($matches);
      if ($account && (!$account->hasField('field_bc_staff_demo') || !$account->get('field_bc_staff_demo')->value)) {
        $this->messenger()->addWarning($this->t('Springer over reserveret brugernavn @name.', ['@name' => $username]));
        continue;
      }
      if (!$account) {
        $account = User::create([
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
      $account->set('status', 0);
      $account->set('field_bc_staff_demo', 1);
      $account->set('field_bc_staff_display_name', $name);
      $account->set('field_bc_staff_status', $status);
      $account->set('field_bc_staff_location', $location);
      $account->set('field_bc_staff_department', ['target_id' => $departments[$department]]);
      $account->save();
    }
    $this->messenger()->addStatus($this->t('Testdata klar: @new nye, @updated opdaterede testmedarbejdere.', [
      '@new' => $created,
      '@updated' => $updated,
    ]));
    $form_state->setRedirect('bc_staff.overview');
  }

}
