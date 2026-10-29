<?php

namespace Drupal\bc_staff_absence\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Resolves today's planned absence without modifying saved presence fields.
 */
final class PlannedStatusResolver {

  /**
   * Constructs the resolver.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Returns a neutral display override for employees absent today.
   *
   * Vacation requires approval. Other registered absence is shown neutrally.
   *
   * @param int[] $uids
   *   User IDs to inspect.
   *
   * @return array<int, string>
   *   IDs mapped to safe display categories, without health-related reasons.
   */
  public function forUsers(array $uids): array {
    if (!$uids) {
      return [];
    }
    $today = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
      ->format('Y-m-d');
    $query = $this->database->select('bc_staff_absence', 'a');
    $query->fields('a', ['uid', 'type', 'state'])
      ->condition('uid', $uids, 'IN')
      ->condition('start_date', $today, '<=')
      ->condition('end_date', $today, '>=')
      ->condition('state', ['approved', 'registered'], 'IN');
    $results = [];
    foreach ($query->execute() as $record) {
      if ($record->type === 'vacation' && $record->state !== 'approved') {
        continue;
      }
      $category = $record->type === 'vacation' ? 'vacation' : 'absent';
      // A generic absence takes precedence over vacation for overlaps.
      if (!isset($results[$record->uid]) || $category === 'absent') {
        $results[$record->uid] = $category;
      }
    }
    return $results;
  }

}
