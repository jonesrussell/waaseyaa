<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Api\Workflow\WorkflowDefinitionsController;
use Waaseyaa\Api\Workflow\WorkflowDryRunController;
use Waaseyaa\Foundation\Http\JsonApiResponseTrait;

/**
 * Dispatches workflow-definition read endpoints for the admin SPA.
 */
final class WorkflowDefinitionsApiRouter implements DomainRouterInterface
{
    use JsonApiResponseTrait;

    public function supports(Request $request): bool
    {
        $controller = $request->attributes->get('_controller', '');

        return is_string($controller) && (
            str_contains($controller, 'WorkflowDefinitionsController::')
            || str_contains($controller, 'WorkflowDryRunController::')
        );
    }

    public function handle(Request $request): Response
    {
        $controllerRef = $request->attributes->get('_controller', '');
        if (!is_string($controllerRef) || !str_contains($controllerRef, '::')) {
            return $this->jsonApiResponse(500, [
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '500', 'title' => 'Internal Server Error', 'detail' => 'Invalid workflow definitions controller reference.']],
            ]);
        }

        [, $action] = explode('::', $controllerRef, 2);

        // --- WorkflowDryRunController actions ---
        if (str_contains($controllerRef, 'WorkflowDryRunController::')) {
            if ($action === 'dryRun') {
                $rawBody = $request->attributes->get('_parsed_body', []);
                $body = is_array($rawBody) ? $rawBody : [];
                $dryRunPayload = new WorkflowDryRunController()->dryRun($body);

                // Error shapes carry an explicit 'status' int key; success shapes carry 'data'.
                if (array_key_exists('status', $dryRunPayload)) {
                    /** @var array{status: int, errors: list<array{status: string, title: string, detail: string}>} $dryRunPayload */
                    return $this->jsonApiResponse($dryRunPayload['status'], [
                        'jsonapi' => ['version' => '1.1'],
                        'errors'  => $dryRunPayload['errors'],
                    ]);
                }

                return $this->jsonApiResponse(200, $dryRunPayload);
            }

            return $this->jsonApiResponse(404, [
                'jsonapi' => ['version' => '1.1'],
                'errors'  => [['status' => '404', 'title' => 'Not Found', 'detail' => sprintf('Unknown dry-run action: %s', $action)]],
            ]);
        }

        // --- WorkflowDefinitionsController actions ---
        $apiController = new WorkflowDefinitionsController();

        $payload = match ($action) {
            'list' => $apiController->list(),
            default => null,
        };

        if ($payload === null) {
            return $this->jsonApiResponse(404, [
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '404', 'title' => 'Not Found', 'detail' => sprintf('Unknown workflow definitions action: %s', $action)]],
            ]);
        }

        return $this->jsonApiResponse(200, $payload);
    }
}
