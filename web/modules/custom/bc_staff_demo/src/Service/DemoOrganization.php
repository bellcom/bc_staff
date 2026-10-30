<?php

namespace Drupal\bc_staff_demo\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Keeps fictional organization fixtures repeatable and isolated.
 */
final class DemoOrganization {

  /**
   * Ensures demo houses, workgroups and department-owned locations exist.
   *
   * @return array
   *   Maps of house and workgroup taxonomy IDs.
   */
  public static function seed(EntityTypeManagerInterface $entities, array $departments): array {
    $storage = $entities->getStorage('taxonomy_term');
    $houses = [];
    foreach (['Næstved', 'Roskilde'] as $house) {
      $name = $house . ' – Demo house';
      $houses[$house] = self::termId($storage, 'bc_staff_house', $name);
    }
    $workgroups = [];
    foreach (['Administration', 'Casework', 'Coordination'] as $group) {
      $workgroups[$group] = self::termId($storage, 'bc_staff_workgroup', 'Demo – ' . $group);
    }
    foreach (['Næstved', 'Roskilde'] as $city) {
      $department = $departments[$city . ' – Administration'] ?? NULL;
      if (!$department) {
        continue;
      }
      $name = $city . ' – Demo reception';
      $id = self::termId($storage, 'bc_staff_location', $name);
      $term = $storage->load($id);
      if ($term && $term->hasField('field_bc_staff_department')) {
        $term->set('field_bc_staff_department', ['target_id' => $department]);
        $term->save();
      }
    }
    return ['houses' => $houses, 'workgroups' => $workgroups];
  }

  /**
   * Assigns fictitious organizational values without changing other fields.
   */
  public static function assign(UserInterface $account, string $department, array $organization, int $index): void {
    $city = str_starts_with($department, 'Roskilde') ? 'Roskilde' : 'Næstved';
    if ($account->hasField('field_bc_staff_house')) {
      $account->set('field_bc_staff_house', [
        'target_id' => $organization['houses'][$city],
      ]);
    }
    if ($account->hasField('field_bc_staff_workgroups')) {
      $names = $index % 3 === 0 ? ['Administration', 'Coordination']
        : ($index % 3 === 1 ? ['Casework'] : ['Coordination']);
      $account->set('field_bc_staff_workgroups', array_map(
        static fn (string $name): array => [
          'target_id' => $organization['workgroups'][$name],
        ],
        $names,
      ));
    }
  }

  /**
   * Loads or creates a single named demo taxonomy term.
   */
  private static function termId($storage, string $vocabulary, string $name): int {
    $matches = $storage->loadByProperties(['vid' => $vocabulary, 'name' => $name]);
    $term = reset($matches);
    if (!$term) {
      $term = $storage->create(['vid' => $vocabulary, 'name' => $name]);
      $term->save();
    }
    return (int) $term->id();
  }

}
