<?php

declare(strict_types=1);

namespace Waaseyaa\SSR\Http\AppController;

use Symfony\Component\Routing\Route;

/**
 * Optional extension point: invoked after built-in binding rules when a parameter
 * is still unresolved. Implementations run in registration order.
 */
interface AppControllerArgumentResolver
{
    public function supports(\ReflectionParameter $parameter, Route $route): bool;

    public function resolve(\ReflectionParameter $parameter, AppInvocationContext $ctx): mixed;
}
