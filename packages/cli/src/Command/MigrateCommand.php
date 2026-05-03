<?php

declare(strict_types=1);

namespace Waaseyaa\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\CLI\Command\Migrate\DryRunFormatter;
use Waaseyaa\CLI\Command\Migrate\DryRunPlanner;
use Waaseyaa\CLI\Command\Migrate\OutputSanitizer;
use Waaseyaa\CLI\Command\Migrate\VerifyFormatter;
use Waaseyaa\CLI\Command\Migrate\VerifyRunner;
use Waaseyaa\Foundation\Diagnostic\DiagnosticCode;
use Waaseyaa\Foundation\Migration\MigrationRepository;
use Waaseyaa\Foundation\Migration\Migrator;
use Waaseyaa\Foundation\Schema\Compiler\Sqlite\SqliteCompiler;
use Waaseyaa\Foundation\Schema\Migration\MigrationInterfaceV2;

/**
 * `bin/waaseyaa migrate` — apply, dry-run, or verify pending migrations.
 *
 * **Modes (per spec §15 Q8 — one CLI surface, two flags):**
 *
 * - default (no flags): runs the unified Migrator (legacy + v2 in one
 *   batch). Backward compatible with pre-WP10 callers.
 * - `--dry-run`: walks the same {@see \Waaseyaa\Foundation\Migration\Dag\MigrationGraph},
 *   compiles every pending v2 plan via {@see SqliteCompiler}, and
 *   prints what WOULD execute. Zero ledger writes, zero SQL on the
 *   live DB. Legacy nodes appear with a placeholder body.
 * - `--verify`: walks the ledger via {@see MigrationRepository::allWithChecksums()}
 *   and recomputes each row's source checksum. Reports match /
 *   mismatch / unknown / orphan. Zero apply, zero ledger writes.
 *   Exit code is non-zero if any row is mismatched or orphaned.
 *
 * `--dry-run` and `--verify` are mutually exclusive — passing both
 * exits with the {@see DiagnosticCode::INCOMPATIBLE_FLAGS} code on
 * stderr (exit code 2).
 *
 * `--json` works with both modes and produces the locked schema
 * documented in {@see DryRunFormatter} and {@see VerifyFormatter}.
 */
#[AsCommand(
    name: 'migrate',
    description: 'Run pending database migrations (use --dry-run to preview, --verify to audit)',
)]
final class MigrateCommand extends Command
{
    /** @var \Closure(): array<string, array<string, \Waaseyaa\Foundation\Migration\Migration>> */
    private \Closure $migrationsProvider;

    /** @var \Closure(): list<MigrationInterfaceV2> */
    private \Closure $v2MigrationsProvider;

    /**
     * @param \Closure(): array<string, array<string, \Waaseyaa\Foundation\Migration\Migration>> $migrationsProvider
     * @param \Closure(): list<MigrationInterfaceV2>                                             $v2MigrationsProvider
     */
    public function __construct(
        private readonly Migrator $migrator,
        \Closure $migrationsProvider,
        ?\Closure $v2MigrationsProvider = null,
        private readonly ?MigrationRepository $repository = null,
        private readonly ?SqliteCompiler $compiler = null,
        private readonly bool $isProduction = true,
    ) {
        $this->migrationsProvider = $migrationsProvider;
        $this->v2MigrationsProvider = $v2MigrationsProvider ?? static fn(): array => [];
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview pending migrations without applying any SQL or writing to the ledger.');
        $this->addOption('verify', null, InputOption::VALUE_NONE, 'Compare ledger checksums against the live source. Read-only.');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON instead of human-readable text.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $verify = (bool) $input->getOption('verify');
        $json = (bool) $input->getOption('json');

        if ($dryRun && $verify) {
            $output->writeln(sprintf(
                '<error>%s: --dry-run and --verify are mutually exclusive. Pass one or the other.</error>',
                DiagnosticCode::INCOMPATIBLE_FLAGS->value,
            ));
            return 2;
        }

        if ($dryRun) {
            return $this->handleDryRun($output, $json);
        }
        if ($verify) {
            return $this->handleVerify($output, $json);
        }

        return $this->handleApply($output);
    }

    private function handleApply(OutputInterface $output): int
    {
        $migrations = ($this->migrationsProvider)();
        $v2 = ($this->v2MigrationsProvider)();
        $result = $this->migrator->run($migrations, $v2);

        if ($result->count === 0) {
            $output->writeln('Nothing to migrate.');
            return self::SUCCESS;
        }

        foreach ($result->migrations as $name) {
            $output->writeln("  Migrated: {$name}");
        }

        $label = $result->count === 1 ? 'migration' : 'migrations';
        $output->writeln("Ran {$result->count} {$label}.");

        return self::SUCCESS;
    }

    private function handleDryRun(OutputInterface $output, bool $json): int
    {
        if ($this->repository === null || $this->compiler === null) {
            $output->writeln('<error>--dry-run requires a MigrationRepository and SqliteCompiler to be wired into MigrateCommand. See packages/foundation/src/Kernel/ConsoleKernel.php.</error>');
            return 2;
        }

        $sanitizer = new OutputSanitizer($this->isProduction);
        $planner = new DryRunPlanner($this->repository, $this->compiler);
        $formatter = new DryRunFormatter($sanitizer);

        $result = $planner->plan(($this->migrationsProvider)(), ($this->v2MigrationsProvider)());

        $output->write($json ? $formatter->toJson($result) : $formatter->toText($result));

        return self::SUCCESS;
    }

    private function handleVerify(OutputInterface $output, bool $json): int
    {
        if ($this->repository === null) {
            $output->writeln('<error>--verify requires a MigrationRepository to be wired into MigrateCommand. See packages/foundation/src/Kernel/ConsoleKernel.php.</error>');
            return 2;
        }

        $sanitizer = new OutputSanitizer($this->isProduction);
        $runner = new VerifyRunner($this->repository);
        $formatter = new VerifyFormatter($sanitizer);

        $outcome = $runner->verify(($this->migrationsProvider)(), ($this->v2MigrationsProvider)());

        $output->write($json ? $formatter->toJson($outcome) : $formatter->toText($outcome));

        return $outcome->summary->hasFailure() ? self::FAILURE : self::SUCCESS;
    }
}
