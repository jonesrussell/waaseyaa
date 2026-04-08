<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler;

interface SolutionProviderInterface
{
    public function canSolve(\Throwable $e): bool;

    /**
     * @return list<SolutionInterface>
     */
    public function getSolutions(\Throwable $e): array;
}
