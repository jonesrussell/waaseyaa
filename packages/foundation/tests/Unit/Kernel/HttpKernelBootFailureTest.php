<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

/**
 * Verifies that HttpKernel handles boot() failures gracefully.
 *
 * The handle() method returns `never` (exits) so it cannot be called directly
 * in unit tests. These tests verify the precondition (boot can throw) and the
 * structural guards added to handle().
 */
#[CoversClass(HttpKernel::class)]
final class HttpKernelBootFailureTest extends TestCase
{
    #[Test]
    public function boot_throws_when_database_path_is_inaccessible(): void
    {
        $root = sys_get_temp_dir() . '/waaseyaa_boot_fail_' . uniqid();
        mkdir($root . '/config', 0755, true);
        // Point to a non-existent directory so PDO cannot create the file.
        file_put_contents(
            $root . '/config/waaseyaa.php',
            "<?php return ['database' => '/nonexistent/deep/path/db.sqlite'];",
        );

        $kernel = new HttpKernel($root);
        $boot = new \ReflectionMethod(AbstractKernel::class, 'boot');
        $boot->setAccessible(true);

        try {
            $this->expectException(\Throwable::class);
            $boot->invoke($kernel);
        } finally {
            @unlink($root . '/config/waaseyaa.php');
            @rmdir($root . '/config');
            @rmdir($root);
        }
    }

    #[Test]
    public function handle_method_wraps_boot_in_try_catch(): void
    {
        // Structural guard: verify the handle() method body contains a try-catch
        // around the boot() call, so boot failures cannot propagate as uncaught exceptions.
        $method = new \ReflectionMethod(HttpKernel::class, 'handle');
        $file = (string) $method->getFileName();
        $start = (int) $method->getStartLine();
        $end = (int) $method->getEndLine();

        $lines = file($file) ?: [];
        $body = implode('', array_slice($lines, $start - 1, $end - $start + 1));

        // The boot() call must be inside a try block.
        $this->assertMatchesRegularExpression(
            '/try\s*\{[^}]*\$this->boot\(\)/s',
            $body,
            'HttpKernel::handle() must wrap $this->boot() in a try-catch to handle boot failures gracefully.',
        );
    }

    #[Test]
    public function client_safe_boot_failure_detail_hides_database_path(): void
    {
        $kernel = new HttpKernel('/tmp');
        $method = new \ReflectionMethod(HttpKernel::class, 'clientSafeBootFailureDetail');
        $method->setAccessible(true);

        $e = new \RuntimeException(
            'Database not found at /secret/deploy/path/waaseyaa.sqlite. In production, the database must already exist. '
            . 'Run "bin/waaseyaa db:init" to create the database file and apply migrations. '
            . 'The command is idempotent and safe to run on every deploy.',
        );
        $detail = $method->invoke($kernel, $e);

        $this->assertStringNotContainsString('/secret/', $detail);
        $this->assertStringContainsString('SQLite database file is missing', $detail);
    }

    #[Test]
    public function client_safe_boot_failure_detail_passes_through_app_debug_guard_message(): void
    {
        $kernel = new HttpKernel('/tmp');
        $method = new \ReflectionMethod(HttpKernel::class, 'clientSafeBootFailureDetail');
        $method->setAccessible(true);

        $expected = 'APP_DEBUG must not be enabled in production (APP_ENV=production). Aborting boot.';
        $detail = $method->invoke($kernel, new \RuntimeException($expected));

        $this->assertSame($expected, $detail);
    }

    #[Test]
    public function client_safe_boot_failure_detail_describes_phpunit_autoload_mistake(): void
    {
        $kernel = new HttpKernel('/tmp');
        $method = new \ReflectionMethod(HttpKernel::class, 'clientSafeBootFailureDetail');
        $method->setAccessible(true);

        $detail = $method->invoke($kernel, new \Error('Class "PHPUnit\\Framework\\TestCase" not found'));

        $this->assertStringContainsString('PHPUnit-only class', $detail);
    }
}
