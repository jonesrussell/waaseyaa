<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Http\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Foundation\Http\JsonApiResponseTrait;
use Waaseyaa\SSR\SsrPageHandler;

/**
 * Routes app-level controllers registered via ServiceProvider::routes()
 * with the `Class::method` controller string format.
 *
 * Delegates to SsrPageHandler::dispatchAppController, which already
 * implements reflection-based constructor injection (EntityTypeManager,
 * Twig, HttpRequest, AccountInterface, plus the kernel's serviceResolver
 * fallback) and method invocation with ($params, $query, $account,
 * $httpRequest).
 *
 * Wired into ControllerDispatcher's router chain after SsrRouter so
 * `render.page` retains its existing precedence; this router only claims
 * controllers that look like fully-qualified Class::method strings.
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

        // Class::method format: must contain `::`, no whitespace, and the
        // segment before `::` must look like a class name (not a named
        // sentinel like 'render.page' or 'graphql.endpoint').
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

        // Class names start with an uppercase letter (top-level) or contain
        // a backslash (namespaced). Reserved framework sentinels in other
        // routers (`render.page`, `broadcast`, `graphql.endpoint`, ...) all
        // start with a lowercase letter and contain no backslash, so they
        // are excluded by this rule.
        return str_contains($class, '\\') || ctype_upper($class[0]);
    }

    public function handle(Request $request): Response
    {
        $controller = (string) $request->attributes->get('_controller', '');
        $ctx = WaaseyaaContext::fromRequest($request);

        // Strip framework-internal `_*` attributes; pass route params only.
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

        // Fallback: dispatchAppController returned a structured array
        // (htmlResult / jsonResult shape) instead of a Response. The typed
        // return shape guarantees `content` matches the `type` discriminator
        // (string for html, array for json).
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
