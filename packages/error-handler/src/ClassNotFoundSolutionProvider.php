<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler;

final class ClassNotFoundSolutionProvider implements SolutionProviderInterface
{
    public function canSolve(\Throwable $e): bool
    {
        return $e instanceof \Error && str_contains($e->getMessage(), 'Class ')
            && str_contains($e->getMessage(), ' not found');
    }

    public function getSolutions(\Throwable $e): array
    {
        return [
            new SimpleSolution(
                title: 'Autoload / class name',
                description: 'Verify the class exists, the namespace matches the file path, and composer.json autoload covers it. Run composer dump-autoload after adding new classes.',
                documentationLinks: [
                    'Composer autoload' => 'https://getcomposer.org/doc/04-schema.md#autoload',
                ],
            ),
        ];
    }
}
