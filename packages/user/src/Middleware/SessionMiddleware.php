<?php

declare(strict_types=1);

namespace Waaseyaa\User\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\Storage\EntityStorageInterface;
use Waaseyaa\Foundation\Middleware\HttpHandlerInterface;
use Waaseyaa\Foundation\Middleware\HttpMiddlewareInterface;
use Waaseyaa\User\AnonymousUser;

final class SessionMiddleware implements HttpMiddlewareInterface
{
    public function __construct(
        private readonly EntityStorageInterface $userStorage,
    ) {}

    public function process(Request $request, HttpHandlerInterface $next): Response
    {
        $account = $this->resolveAccount($request);
        $request->attributes->set('_account', $account);

        return $next->handle($request);
    }

    private function resolveAccount(Request $request): AccountInterface
    {
        $session = $request->attributes->get('_session') ?? ($_SESSION ?? []);
        $uid = $session['waaseyaa_uid'] ?? null;

        if ($uid === null) {
            return new AnonymousUser();
        }

        $user = $this->userStorage->load($uid);

        if ($user instanceof AccountInterface) {
            return $user;
        }

        return new AnonymousUser();
    }
}
