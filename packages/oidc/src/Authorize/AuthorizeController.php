<?php

declare(strict_types=1);

namespace Waaseyaa\Oidc\Authorize;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Oidc\ClientRegistry\OidcClientLookup;
use Waaseyaa\Oidc\Repository\AuthorizationCodeRepositoryInterface;

/**
 * OAuth 2.1 / OIDC authorization code flow entry point (ADR-006 §7).
 *
 * Flow:
 *   1. Anonymous caller → 302 to the login page with `return_to` preserving
 *      the original authorize URL (query string intact).
 *   2. Authenticated caller → look up client_id, validate the request, issue
 *      an authorization code, and 302 back to the client's redirect_uri with
 *      `code` (and `state` if provided).
 *   3. Any rejection before redirect_uri is verified → direct HTML error page
 *      (OAuth 2.0 §4.1.2.1 forbids redirecting to an unregistered URI).
 *   4. Any rejection after redirect_uri is verified → 302 to redirect_uri with
 *      `error`, `error_description`, and `state`.
 *
 * First-party clients auto-consent for now; a consent screen is a deferred
 * follow-up (see #1284 "Non-goals").
 */
final readonly class AuthorizeController
{
    public function __construct(
        private OidcClientLookup $clientLookup,
        private AuthorizationRequestValidator $validator,
        private AuthorizationCodeRepositoryInterface $codeRepository,
        private string $loginPath = '/login',
    ) {}

    public function __invoke(Request $request): Response
    {
        $account = $request->attributes->get('_account');
        if (!$account instanceof AccountInterface) {
            return $this->directError(500, 'server_error', 'Session middleware did not set account.');
        }

        if (!$account->isAuthenticated()) {
            return new RedirectResponse(
                $this->appendQuery($this->loginPath, ['return_to' => $request->getRequestUri()]),
                302,
            );
        }

        $query = $request->query->all();

        $clientId = $query['client_id'] ?? null;
        if (!is_string($clientId) || $clientId === '') {
            return $this->directError(400, 'invalid_request', 'Missing client_id.');
        }

        $client = $this->clientLookup->findByClientId($clientId);
        if ($client === null) {
            return $this->directError(400, 'invalid_request', 'Unknown client_id.');
        }

        try {
            $validated = $this->validator->validate($client, $query);
        } catch (AuthorizationRequestException $e) {
            if (!$e->canRedirect()) {
                return $this->directError(400, $e->errorCode, $e->errorDescription);
            }

            return $this->errorRedirect($e);
        }

        $code = $this->codeRepository->issue(
            clientId: $validated->client->getClientId(),
            account: $account,
            redirectUri: $validated->redirectUri,
            scopes: $validated->scopes,
            codeChallenge: $validated->codeChallenge,
            codeChallengeMethod: $validated->codeChallengeMethod,
            nonce: $validated->nonce,
        );

        $params = ['code' => $code->code];
        if ($validated->state !== null) {
            $params['state'] = $validated->state;
        }

        return new RedirectResponse($this->appendQuery($validated->redirectUri, $params), 302);
    }

    /**
     * @param array<string, string> $params
     */
    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }

    private function errorRedirect(AuthorizationRequestException $e): RedirectResponse
    {
        // canRedirect() guarantees non-null redirectUri.
        $redirectUri = $e->redirectUri ?? '';

        $params = [
            'error' => $e->errorCode,
            'error_description' => $e->errorDescription,
        ];
        if ($e->state !== null) {
            $params['state'] = $e->state;
        }

        return new RedirectResponse($this->appendQuery($redirectUri, $params), 302);
    }

    private function directError(int $status, string $code, string $description): Response
    {
        $body = sprintf(
            '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Authorization Error</title></head>'
            . '<body><h1>Authorization Error</h1>'
            . '<p><strong>%s</strong></p><p>%s</p></body></html>',
            htmlspecialchars($code, \ENT_QUOTES),
            htmlspecialchars($description, \ENT_QUOTES),
        );

        return new Response($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
