<?php

namespace Drupal\bc_staff_absence\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Displays and acknowledges the logged-in user's notifications.
 */
final class NotificationController extends ControllerBase {

  public function listing(): array {
    $uid = (int) $this->currentUser()->id();
    $db = \Drupal::database();
    $items = [];
    $unread_only = \Drupal::request()->query->get('filter') === 'unread';
    $unread_count = 0;
    if ($uid && $db->schema()->tableExists('bc_staff_notification')) {
      $unread_count = (int) $db->select('bc_staff_notification', 'n')
        ->condition('uid', $uid)->isNull('read_at')->countQuery()->execute()->fetchField();
      $query = $db->select('bc_staff_notification', 'n')->fields('n')
        ->condition('uid', $uid);
      if ($unread_only) { $query->isNull('read_at'); }
      $records = $query->orderBy('created', 'DESC')->orderBy('id', 'DESC')
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
      '#unread_only' => $unread_only, '#unread_count' => $unread_count,
      '#mark_all_token' => \Drupal::service('csrf_token')->get('bc_staff_notification_mark_all:' . $uid),
      '#cache' => ['contexts' => ['user'], 'max-age' => 0]];
  }

  /**
   * Mark all of the current user's messages as read (CSRF-protected POST).
   */
  public function markAll(Request $request): RedirectResponse {
    $uid = (int) $this->currentUser()->id();
    if (!$uid || !\Drupal::service('csrf_token')->validate((string) $request->request->get('token'), 'bc_staff_notification_mark_all:' . $uid)) {
      throw new AccessDeniedHttpException();
    }
    $db = \Drupal::database();
    if ($db->schema()->tableExists('bc_staff_notification')) {
      $db->update('bc_staff_notification')->fields(['read_at' => \Drupal::time()->getCurrentTime()])
        ->condition('uid', $uid)->isNull('read_at')->execute();
    }
    return new RedirectResponse(Url::fromRoute('bc_staff_absence.notifications')->toString());
  }

  /**
   * Poll only the current user's count and five latest notifications.
   */
  public function status(): JsonResponse {
    $uid = (int) $this->currentUser()->id();
    $db = \Drupal::database();
    $count = 0;
    $items = [];
    if ($uid && $db->schema()->tableExists('bc_staff_notification')) {
      $count = (int) $db->select('bc_staff_notification', 'n')->condition('uid', $uid)
        ->isNull('read_at')->countQuery()->execute()->fetchField();
      $query = $db->select('bc_staff_notification', 'n');
      foreach ($query->fields('n', ['id', 'message', 'created', 'read_at'])
        ->condition('uid', $uid)->orderBy('created', 'DESC')->orderBy('id', 'DESC')
        ->range(0, 5)->execute() as $entry) {
        $items[] = [
          'message' => $entry->message,
          'time' => \Drupal::service('date.formatter')->format((int) $entry->created, 'short'),
          'unread' => $entry->read_at === NULL,
          'url' => Url::fromRoute('bc_staff_absence.notification_open', ['notification' => $entry->id])->toString(),
        ];
      }
    }
    $response = new JsonResponse(['count' => $count, 'items' => $items]);
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
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
