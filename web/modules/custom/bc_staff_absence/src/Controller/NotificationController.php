<?php

namespace Drupal\bc_staff_absence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Displays and acknowledges the logged-in user's notifications.
 */
final class NotificationController extends ControllerBase {

  public function listing(): array {
    $uid = (int) $this->currentUser()->id();
    $db = \Drupal::database();
    $items = [];
    if ($uid && $db->schema()->tableExists('bc_staff_notification')) {
      $records = $db->select('bc_staff_notification', 'n')->fields('n')
        ->condition('uid', $uid)->orderBy('created', 'DESC')->orderBy('id', 'DESC')
        ->range(0, 100)->execute();
      foreach ($records as $record) {
        $items[] = [
          'message' => $record->message,
          'time' => \Drupal::service('date.formatter')->format((int) $record->created, 'short'),
          'unread' => $record->read_at === NULL,
          'url' => Url::fromRoute('bc_staff_absence.notification_open', ['notification' => $record->id])->toString(),
        ];
      }
    }
    return ['#theme' => 'bc_staff_notifications', '#items' => $items,
      '#cache' => ['contexts' => ['user'], 'max-age' => 0]];
  }

  public function open(int $notification): RedirectResponse {
    $db = \Drupal::database();
    $record = $db->select('bc_staff_notification', 'n')->fields('n')
      ->condition('id', $notification)->condition('uid', (int) $this->currentUser()->id())
      ->execute()->fetchObject();
    if (!$record || !in_array($record->target, ['bc_staff_absence.list', 'bc_staff_absence.review'], TRUE)) {
      throw new AccessDeniedHttpException();
    }
    $db->update('bc_staff_notification')->fields(['read_at' => \Drupal::time()->getCurrentTime()])
      ->condition('id', $notification)->condition('uid', (int) $this->currentUser()->id())
      ->isNull('read_at')->execute();
    return new RedirectResponse(Url::fromRoute($record->target)->toString());
  }
}
