<?php

declare(strict_types=1);

/**
 * Waaseyaa-owned name for Symfony's HTTP foundation request.
 *
 * Per ratified contract C-002 of mission 1107-api-symfony-decoupling, this
 * file aliases Symfony's Request class so app code can type-hint the
 * Waaseyaa name. A real composition wrapper is explicitly out of scope.
 *
 * Loaded via composer.json autoload.files at bootstrap; the guard makes the
 * call idempotent under PSR-4 fallback or repeated includes.
 */
if (!class_exists('Waaseyaa\\Foundation\\Http\\Request', false)) {
    class_alias(
        \Symfony\Component\HttpFoundation\Request::class,
        'Waaseyaa\\Foundation\\Http\\Request',
    );
}
