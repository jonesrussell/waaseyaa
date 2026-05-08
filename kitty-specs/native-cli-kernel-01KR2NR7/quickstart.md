# Quickstart — Native CLI Kernel

**Mission**: `native-cli-kernel-01KR2NR7`
**Audience**: framework / extension authors wiring a command after the cut.

This file is a forward-looking walkthrough of the post-merge developer experience. It does NOT describe code that exists today; it describes the contract that the implementation WPs land. Use it as the acceptance reference for "does the kernel behave the way the plan promised."

---

## 1. Define a handler

```php
// packages/myext/src/Command/HelloHandler.php
declare(strict_types=1);

namespace MyExt\Command;

use Waaseyaa\Cli\Io\CliIO;

final class HelloHandler
{
    public function __construct(
        private readonly \Waaseyaa\Foundation\Log\LoggerInterface $logger,
    ) {}

    public function execute(CliIO $io): int
    {
        $name = $io->getArgument('name') ?? 'world';
        $shout = (bool) $io->getOption('shout');

        $message = "Hello, {$name}!";
        if ($shout) {
            $message = strtoupper($message);
        }

        $io->writeln($message);
        $this->logger->info('hello.cmd', ['name' => $name]);
        return 0;
    }
}
```

## 2. Register it on a service provider

```php
// packages/myext/src/Provider/MyExtServiceProvider.php
declare(strict_types=1);

namespace MyExt\Provider;

use Waaseyaa\Cli\ArgumentDefinition;
use Waaseyaa\Cli\ArgumentMode;
use Waaseyaa\Cli\CommandDefinition;
use Waaseyaa\Cli\OptionDefinition;
use Waaseyaa\Cli\OptionMode;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use MyExt\Command\HelloHandler;

final class MyExtServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void
    {
        $this->bind(HelloHandler::class);
    }

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'hello',
            description: 'Print a greeting.',
            arguments: [
                new ArgumentDefinition(
                    name: 'name',
                    mode: ArgumentMode::Optional,
                    description: 'Who to greet (defaults to "world").',
                ),
            ],
            options: [
                new OptionDefinition(
                    name: 'shout',
                    mode: OptionMode::None,
                    description: 'Uppercase the greeting.',
                ),
            ],
            handler: [HelloHandler::class, 'execute'],
        );
    }
}
```

No `use Symfony\…` anywhere.

## 3. Run it

```bash
$ bin/waaseyaa hello
Hello, world!

$ bin/waaseyaa hello russell --shout
HELLO, RUSSELL!

$ bin/waaseyaa hello --help
Usage:
  hello [options] [--] [<name>]

Description:
  Print a greeting.

Arguments:
  name      Who to greet (defaults to "world").

Options:
      --help     Display help for this command.
      --shout    Uppercase the greeting.
  -v, --verbose  Enable verbose output (full traces on errors).
      --version  Display the framework version.
```

## 4. Test it

```php
// packages/myext/tests/Unit/Command/HelloHandlerTest.php
declare(strict_types=1);

namespace MyExt\Tests\Unit\Command;

use MyExt\Command\HelloHandler;
use MyExt\Provider\MyExtServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cli\Testing\CliTester;
use Waaseyaa\Foundation\Log\NullLogger;
use Waaseyaa\Foundation\Testing\TestContainer;

#[CoversClass(HelloHandler::class)]
final class HelloHandlerTest extends TestCase
{
    #[Test]
    public function it_greets_world_by_default(): void
    {
        $container = TestContainer::with([
            HelloHandler::class => new HelloHandler(new NullLogger()),
        ]);

        $provider = new MyExtServiceProvider();
        $definition = iterator_to_array($provider->nativeCommands())[0];

        $tester = CliTester::for($definition, $container);
        $tester->execute([]);

        self::assertSame(0, $tester->getExitCode());
        self::assertSame("Hello, world!\n", $tester->getStdout());
        self::assertSame('', $tester->getStderr());
    }

    #[Test]
    public function it_shouts_when_flagged(): void
    {
        $container = TestContainer::with([
            HelloHandler::class => new HelloHandler(new NullLogger()),
        ]);

        $definition = iterator_to_array((new MyExtServiceProvider())->nativeCommands())[0];

        $tester = CliTester::for($definition, $container);
        $tester->execute(['russell', '--shout']);

        self::assertSame(0, $tester->getExitCode());
        self::assertSame("HELLO, RUSSELL!\n", $tester->getStdout());
    }
}
```

## 5. Verify the bulk-edit gate locally

After WP-12 merges:

```bash
# No first-party Symfony Console references remain at runtime.
composer why symfony/console        # -> only test/dev/transitive chains, none through waaseyaa/cli runtime
grep -r "Symfony\\\\Component\\\\Console" packages/cli/src/ # -> empty
grep -r "Symfony\\\\Component\\\\Console" bin/waaseyaa      # -> empty

# Layer + policy gates clean.
bin/check-package-layers
bin/check-composer-policy
composer phpstan
composer cs-check
tools/drift-detector.sh

# Performance threshold met.
kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh list 10
kitty-specs/native-cli-kernel-01KR2NR7/scripts/perf-harness.sh health:check 10
```
