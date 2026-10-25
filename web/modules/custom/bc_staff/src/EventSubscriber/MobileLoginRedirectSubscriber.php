<?php

namespace Drupal\bc_staff\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects anonymous users of the mobile status page to Drupal login.
 */
final class MobileLoginRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 75]];
  }

  /**
   * Handles onException.
   */
  public function onException(ExceptionEvent $event): void {
    if (!$event->getThrowable() instanceof AccessDeniedHttpException
      || $this->currentUser->isAuthenticated()) {
      return;
    }

    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'bc_staff.mobile') {
      return;
    }

    $login = Url::fromRoute('bc_staff.login', [], [
      'query' => ['destination' => '/staff/mobil'],
      'absolute' => FALSE,
    ]);
    $response = new RedirectResponse($login->toString(), 302);
    $response->headers->set('Cache-Control', 'no-store, private');
    $event->setResponse($response);
  }

}
