<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Diagnostic;

interface HealthCheckerInterface
{
    /** @return list<HealthCheckResult> */
    public function runAll(): array;

    /** @return list<HealthCheckResult> */
    public function checkBoot(): array;

    /** @return list<HealthCheckResult> */
    public function checkRuntime(): array;

    /** @return list<HealthCheckResult> */
    public function checkIngestion(): array;

    /** @return list<HealthCheckResult> */
    public function checkSchemaDrift(): array;
}
