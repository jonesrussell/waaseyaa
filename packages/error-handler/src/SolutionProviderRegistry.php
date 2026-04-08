<?php

declare(strict_types=1);

namespace Waaseyaa\ErrorHandler;

final class SolutionProviderRegistry
{
    /** @var list<SolutionProviderInterface> */
    private array $providers = [];

    public function register(SolutionProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return list<SolutionInterface>
     */
    public function solutionsFor(\Throwable $e): array
    {
        $out = [];
        foreach ($this->providers as $provider) {
            if (!$provider->canSolve($e)) {
                continue;
            }
            foreach ($provider->getSolutions($e) as $solution) {
                $out[] = $solution;
            }
        }

        return $out;
    }
}
