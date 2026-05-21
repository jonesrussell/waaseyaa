<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Kernel\Bootstrap;

use Waaseyaa\Foundation\Discovery\PackageManifest;
use Waaseyaa\Foundation\Kernel\Bootstrap\Exception\ScheduleEntryInstantiationException;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Scheduler\ScheduleEntriesInterface;
use Waaseyaa\Scheduler\ScheduleInterface;

/**
 * Enumerates manifest schedule entries and registers them at kernel boot.
 *
 * Adopts M-B's PolicyDependencyResolverInterface for constructor resolution —
 * the same resolver used by AccessPolicyRegistry. This avoids introducing a
 * parallel resolver interface and ensures consistent DI semantics at boot.
 *
 * Fail-closed: any unresolvable dependency aborts boot via
 * ScheduleEntryInstantiationException. Entries listed in
 * `schedule.disabled_entries` are silently skipped (FR-007).
 *
 * @internal
 */
final class ScheduleEntryRegistry
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PolicyDependencyResolverInterface $resolver,
    ) {}

    /**
     * Instantiate and register all discovered schedule entries.
     *
     * @param array<string, mixed> $config Full kernel config array.
     * @throws ScheduleEntryInstantiationException When a constructor dependency cannot be resolved.
     */
    public function boot(PackageManifest $manifest, ScheduleInterface $schedule, array $config): void
    {
        /** @var list<string> $disabledEntries */
        $disabledEntries = $config['schedule']['disabled_entries'] ?? [];

        foreach ($manifest->scheduleEntries as $fqcn) {
            if (in_array($fqcn, $disabledEntries, true)) {
                $this->logger->info('Schedule entry disabled by configuration', ['class' => $fqcn]);
                continue;
            }

            $instance = $this->instantiate($fqcn);
            $instance->register($schedule);

            $this->logger->debug('Schedule entry registered', ['class' => $fqcn]);
        }
    }

    /**
     * Resolve constructor parameters and instantiate the schedule entry class.
     *
     * @throws ScheduleEntryInstantiationException
     */
    private function instantiate(string $fqcn): ScheduleEntriesInterface
    {
        if (!class_exists($fqcn)) {
            throw new ScheduleEntryInstantiationException(sprintf(
                "Failed to boot schedule entry '%s': class not found. "
                . 'Run "composer dump-autoload --optimize" to update the classmap.',
                $fqcn,
            ));
        }

        try {
            $ref = new \ReflectionClass($fqcn);
            $constructor = $ref->getConstructor();

            if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
                /** @var ScheduleEntriesInterface */
                return $ref->newInstance();
            }

            $args = $this->resolveParameters($fqcn, $constructor);

            /** @var ScheduleEntriesInterface */
            return $ref->newInstanceArgs($args);
        } catch (ScheduleEntryInstantiationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ScheduleEntryInstantiationException::fromThrowable($fqcn, $e);
        }
    }

    /**
     * @return list<mixed>
     * @throws ScheduleEntryInstantiationException
     */
    private function resolveParameters(string $fqcn, \ReflectionMethod $constructor): array
    {
        $args = [];

        foreach ($constructor->getParameters() as $param) {
            try {
                // Delegate to the M-B resolver; pass empty entity-types array since
                // schedule entries do not have the ConfigEntityAccessPolicy array-param convention.
                $args[] = $this->resolver->resolveParameter($fqcn, $param, []);
            } catch (\Waaseyaa\Foundation\Kernel\Bootstrap\Exception\PolicyInstantiationException $e) {
                // Translate M-B's exception type to ours for clear attribution.
                $type = $param->getType();
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '(unknown)';
                throw ScheduleEntryInstantiationException::fromUnresolvableDependency(
                    $fqcn,
                    $param->getName(),
                    $typeName,
                );
            }
        }

        return $args;
    }
}
