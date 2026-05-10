<?php declare(strict_types=1);

namespace Waaseyaa\Tools\PHPStan;

use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

use function in_array;
use function interface_exists;
use function is_array;
use function is_file;
use function json_decode;
use function file_get_contents;
use function glob;
use function preg_match;
use function str_contains;

/**
 * Marks Waaseyaa-specific entrypoints as used so the dead-code detector does
 * not flag them. Covers the four discovery patterns Waaseyaa relies on that
 * are invisible to AST-level call graph analysis:
 *
 *  1. Classes carrying the access-policy attribute (resolved by
 *     AbstractKernel::discoverAccessPolicies via reflection).
 *  2. Classes carrying the middleware attribute (resolved by
 *     PackageManifestCompiler attribute scanning).
 *  3. Classes listed in any package composer.json under
 *     extra.waaseyaa.providers (instantiated by the kernel at boot).
 *  4. Classes whose FQCN sits under a `\Ingestion\EntityMapper\` namespace
 *     segment (resolved by ingestion runtime by namespace convention).
 *  5. Implementations of RouteProviderInterface (resolved by RouteBuilder
 *     when the interface exists in this checkout).
 */
final class WaaseyaaEntrypointProvider extends ReflectionBasedMemberUsageProvider
{
    private const POLICY_ATTRIBUTE = 'Waaseyaa\\Access\\Gate\\PolicyAttribute';
    private const MIDDLEWARE_ATTRIBUTE = 'Waaseyaa\\Foundation\\Attribute\\AsMiddleware';
    private const ROUTE_PROVIDER_INTERFACE = 'Waaseyaa\\Routing\\RouteProviderInterface';
    private const ENTITY_MAPPER_NAMESPACE_SEGMENT = '\\Ingestion\\EntityMapper\\';

    /** @var array<string, true> FQCN set of declared service providers. */
    private array $declaredProviders;

    public function __construct(string $projectRoot)
    {
        $this->declaredProviders = self::loadDeclaredProviders($projectRoot);
    }

    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        return $this->isEntrypointClass($method->getDeclaringClass()->getName(), $method->getDeclaringClass())
            ? VirtualUsageData::withNote('Waaseyaa entrypoint (policy/middleware/provider/mapper/route-provider)')
            : null;
    }

    protected function shouldMarkConstantAsUsed(ReflectionClassConstant $constant): ?VirtualUsageData
    {
        return $this->isEntrypointClass($constant->getDeclaringClass()->getName(), $constant->getDeclaringClass())
            ? VirtualUsageData::withNote('Waaseyaa entrypoint constant')
            : null;
    }

    protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
    {
        return $this->isEntrypointClass($property->getDeclaringClass()->getName(), $property->getDeclaringClass())
            ? VirtualUsageData::withNote('Waaseyaa entrypoint property')
            : null;
    }

    protected function shouldMarkPropertyAsWritten(ReflectionProperty $property): ?VirtualUsageData
    {
        return $this->shouldMarkPropertyAsRead($property);
    }

    private function isEntrypointClass(string $fqcn, \ReflectionClass $reflection): bool
    {
        if (isset($this->declaredProviders[$fqcn])) {
            return true;
        }

        if (str_contains($fqcn, self::ENTITY_MAPPER_NAMESPACE_SEGMENT)) {
            return true;
        }

        foreach ($reflection->getAttributes() as $attribute) {
            $name = $attribute->getName();
            if ($name === self::POLICY_ATTRIBUTE || $name === self::MIDDLEWARE_ATTRIBUTE) {
                return true;
            }
        }

        if (interface_exists(self::ROUTE_PROVIDER_INTERFACE)
            && in_array(self::ROUTE_PROVIDER_INTERFACE, $reflection->getInterfaceNames(), true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private static function loadDeclaredProviders(string $projectRoot): array
    {
        $providers = [];
        $manifests = glob($projectRoot . '/packages/*/composer.json') ?: [];
        foreach ($manifests as $manifest) {
            if (!is_file($manifest)) {
                continue;
            }
            $contents = file_get_contents($manifest);
            if ($contents === false) {
                continue;
            }
            $data = json_decode($contents, true);
            if (!is_array($data)) {
                continue;
            }
            $declared = $data['extra']['waaseyaa']['providers'] ?? null;
            if (!is_array($declared)) {
                continue;
            }
            foreach ($declared as $fqcn) {
                if (is_string($fqcn) && $fqcn !== '' && preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $fqcn) === 1) {
                    $providers[$fqcn] = true;
                }
            }
        }
        return $providers;
    }
}
