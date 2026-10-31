<?php

namespace Drupal\bc_staff_absence\Service;

use Drupal\bc_staff_absence\Access\AbsenceApprovalAccess;
use Drupal\user\UserInterface;

/**
 * Sends minimal vacation workflow email notifications.
 */
final class AbsenceNotifier {

  /**
   * Notify only reviewers authorized for the requesting employee.
   */
  public static function reviewers(int $employee_uid, string $event): void {
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $employee = $storage->load($employee_uid);
    if (!$employee instanceof UserInterface || !$employee->isActive()) {
      return;
    }
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('status', 1)->condition('uid', 0, '>')->execute();
    foreach ($storage->loadMultiple($ids) as $manager) {
      if ((int) $manager->id() === $employee_uid
        || !AbsenceApprovalAccess::canReview($manager, $manager, $employee)) {
        continue;
      }
      self::send($manager, $event, [
        '@employee' => $employee->getDisplayName(),
      ], 'bc_staff_absence.review');
    }
  }

  /**
   * Notify the employee of the manager's decision.
   */
  public static function employee(int $uid, string $event): void {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    if ($account instanceof UserInterface && $account->isActive()) {
      self::send($account, $event, [], 'bc_staff_absence.list');
    }
  }

  private static function send(UserInterface $recipient, string $event, array $variables, string $route): void {
    $address = trim((string) $recipient->getEmail());
    if ($address === '' || str_ends_with(strtolower($address), '@example.invalid') || !\Drupal::service('email.validator')->isValid($address)) {
      return;
    }
    $subjects = [
      'new' => t('New vacation request'),
      'changed' => t('Vacation request updated'),
      'change' => t('Vacation change awaiting approval'),
      'approved' => t('Vacation request approved'),
      'rejected' => t('Vacation request rejected'),
      'change_approved' => t('Vacation change approved'),
      'change_rejected' => t('Vacation change rejected'),
      'cancel_approved' => t('Vacation cancellation approved'),
      'cancel_rejected' => t('Vacation cancellation rejected'),
    ];
    if (!isset($subjects[$event])) {
      return;
    }
    $url = \Drupal\Core\Url::fromRoute($route, [], ['absolute' => TRUE])->toString();
    $body = t('A BC Staff vacation request has been updated. Sign in to review the details: @url', ['@url' => $url]);
    if (isset($variables['@employee'])) {
      $body = t('A vacation request for @employee requires your review. Sign in: @url', [
        '@employee' => $variables['@employee'], '@url' => $url,
      ]);
    }
    try {
      $result = \Drupal::service('plugin.manager.mail')->mail('bc_staff_absence', 'notification', $address,
        $recipient->getPreferredLangcode(), [
          'subject' => (string) $subjects[$event],
          'body' => (string) $body,
        ]);
      if (empty($result['result'])) {
        \Drupal::logger('bc_staff_absence')->warning('Unable to send vacation notification to user @uid.', ['@uid' => $recipient->id()]);
      }
    }
    catch (\Throwable $exception) {
      // Never undo the saved approval if mail transport is unavailable.
      \Drupal::logger('bc_staff_absence')->error('Vacation notification failed for user @uid: @error', [
        '@uid' => $recipient->id(), '@error' => $exception->getMessage(),
      ]);
    }
  }

}
