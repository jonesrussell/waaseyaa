<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Http\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Http\JsonApiResponseTrait;
use Waaseyaa\Foundation\Http\Router\DomainRouterInterface;
use Waaseyaa\Foundation\Http\Router\WaaseyaaContext;
use Waaseyaa\SSR\SsrPageHandler;

/**
 * Routes app-level controllers registered via ServiceProvider::routes()
 * with the `Class::method` controller string format.
 *
 * @see SsrPageHandler::dispatchAppController()
 */
final class AppControllerRouter implements DomainRouterInterface
{
    use JsonApiResponseTrait;

    public function __construct(
        private readonly SsrPageHandler $ssrPageHandler,
    ) {}

    public function supports(Request $request): bool
    {
        $controller = $request->attributes->get('_controller', '');

        if (!is_string($controller) || $controller === '') {
            return false;
        }

        if (!str_contains($controller, '::')) {
            return false;
        }
        if (preg_match('/\s/', $controller) === 1) {
            return false;
        }

        [$class, $method] = explode('::', $controller, 2);
        if ($class === '' || $method === '') {
            return false;
        }

        return str_contains($class, '\\') || ctype_upper($class[0]);
    }

    public function handle(Request $request): Response
    {
        $controller = (string) $request->attributes->get('_controller', '');
        $ctx = WaaseyaaContext::fromRequest($request);

        $params = array_filter(
            $request->attributes->all(),
            static fn(string $key): bool => !str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY,
        );

        $result = $this->ssrPageHandler->dispatchAppController(
            $controller,
            $params,
            $ctx->query,
            $ctx->account,
            $request,
        );

        if ($result instanceof Response) {
            return $result;
        }

        if ($result['type'] === 'json') {
            /** @var array<string, mixed> $content */
            $content = $result['content'];
            return $this->jsonApiResponse($result['status'], $content, $result['headers']);
        }

        /** @var string $content */
        $content = $result['content'];
        return new Response(
            $content,
            $result['status'],
            array_merge(
                ['Content-Type' => 'text/html; charset=UTF-8'],
                $result['headers'],
            ),
        );
    }
}
