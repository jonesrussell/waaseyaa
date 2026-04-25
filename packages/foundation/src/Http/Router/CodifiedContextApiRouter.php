<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Api\CodifiedContext\CodifiedContextSessionStoreInterface;
use Waaseyaa\Api\Controller\CodifiedContextController;
use Waaseyaa\Foundation\Http\JsonApiResponseTrait;

/**
 * Dispatches Telescope agent-context (codified-context) session JSON endpoints.
 */
final class CodifiedContextApiRouter implements DomainRouterInterface
{
    use JsonApiResponseTrait;

    public function __construct(
        private readonly ?CodifiedContextSessionStoreInterface $sessionStore = null,
    ) {}

    public function supports(Request $request): bool
    {
        $controller = $request->attributes->get('_controller', '');

        return is_string($controller) && str_contains($controller, 'CodifiedContextController::');
    }

    public function handle(Request $request): Response
    {
        $controllerRef = $request->attributes->get('_controller', '');
        if (!is_string($controllerRef) || !str_contains($controllerRef, '::')) {
            return $this->jsonApiResponse(500, [
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '500', 'title' => 'Internal Server Error', 'detail' => 'Invalid codified context controller reference.']],
            ]);
        }

        [, $action] = explode('::', $controllerRef, 2);
        $ctx = WaaseyaaContext::fromRequest($request);
        $apiController = new CodifiedContextController($this->sessionStore);

        $params = $request->attributes->all();
        $sessionId = isset($params['sessionId']) && is_scalar($params['sessionId'])
            ? (string) $params['sessionId']
            : '';

        $payload = match ($action) {
            'listSessions' => $apiController->listSessions($ctx->query),
            'getSession' => $apiController->getSession($sessionId),
            'getSessionEvents' => $apiController->getSessionEvents($sessionId),
            'getSessionValidation' => $apiController->getSessionValidation($sessionId),
            default => null,
        };

        if ($payload === null) {
            return $this->jsonApiResponse(404, [
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '404', 'title' => 'Not Found', 'detail' => sprintf('Unknown codified context action: %s', $action)]],
            ]);
        }

        return $this->jsonApiResponse(200, $payload);
    }
}
