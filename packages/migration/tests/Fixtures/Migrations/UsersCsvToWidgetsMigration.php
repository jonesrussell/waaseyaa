<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Fixtures\Migrations;

use Waaseyaa\Migration\MigrationDefinition;
use Waaseyaa\Migration\Plugin\Destination\EntityDestination;
use Waaseyaa\Migration\Plugin\Process\DefaultValueProcessor;
use Waaseyaa\Migration\Plugin\Process\HtmlSanitizeProcessor;
use Waaseyaa\Migration\Plugin\Process\PassThroughProcessor;
use Waaseyaa\Migration\Plugin\Process\TypeCoerceProcessor;
use Waaseyaa\Migration\Tests\Fixtures\CsvSource;

/**
 * Test-fixture migration declaring the WP11 acceptance pipeline:
 *
 *   CsvSource(users-1000.csv, key=[id])
 *     → process chain:
 *         title     = pass-through "username"   (string shorthand)
 *         body      = HtmlSanitize("bio_html")  (strips <script>)
 *         value_int = [PassThrough("signup_year"), TypeCoerce("int")]
 *         tags      = [PassThrough("tags"), DefaultValue("")]
 *     → EntityDestination("migration_test_widget")
 *
 * The process map intentionally exercises every framework-reserved process
 * plugin needed by the mission acceptance gate (FR-053): a string shorthand
 * (`PassThrough` sugar), a single-step `HtmlSanitize`, a two-step chain
 * (`PassThrough` → `TypeCoerce`), and a chain with `DefaultValue` as the
 * tail so the 10% of source rows with empty `tags` strings land as a
 * non-null fallback at the destination.
 *
 * The destination is wired by the test setUp — this factory only needs the
 * fully-built {@see EntityDestination} and the CSV file path.
 *
 * @internal — Test fixture for {@see \Waaseyaa\Migration\Tests\Integration\EndToEndCsvImportTest}.
 *
 * @spec FR-053 — full CSV → entity pipeline acceptance
 */
final class UsersCsvToWidgetsMigration
{
    /** Canonical migration id used by the WP11 acceptance test. */
    public const string MIGRATION_ID = 'users_csv_to_widgets';

    /**
     * Canonical absolute path to the 1000-row CSV fixture committed for WP11.
     */
    public static function fixturePath(): string
    {
        return \dirname(__DIR__) . '/data/users-1000.csv';
    }

    /**
     * Build the canonical {@see MigrationDefinition} for the WP11 e2e suite.
     *
     * @param EntityDestination $destination Pre-wired entity destination
     *        bound to `migration_test_widget`; the factory does NOT
     *        construct the destination itself because the storage seam,
     *        gate, dispatcher, etc., live in the test rig.
     * @param string|null $csvPath Override the CSV path (defaults to
     *        {@see fixturePath()}). Tests use the override to point at a
     *        smaller fixture for fast smoke checks.
     */
    public static function create(
        EntityDestination $destination,
        ?string $csvPath = null,
    ): MigrationDefinition {
        return new MigrationDefinition(
            id: self::MIGRATION_ID,
            source: new CsvSource(
                filePath: $csvPath ?? self::fixturePath(),
                keyFields: ['id'],
                sourceType: 'csv_users',
                pluginId: 'csv_users_to_widgets',
            ),
            process: [
                // String shorthand → PassThrough sugar (FR-010).
                'title' => 'username',

                // HtmlSanitize strips the embedded <script> tags injected
                // into every 50th source row.
                'body' => new HtmlSanitizeProcessor('bio_html'),

                // Two-step chain: read the signup_year string then coerce
                // to int. Exercises chain composition (FR-010 §6.2).
                'value_int' => [
                    new PassThroughProcessor('signup_year'),
                    new TypeCoerceProcessor('int'),
                ],

                // Two-step chain ending in DefaultValue — about 10% of
                // source rows have empty tag strings; DefaultValue
                // substitutes a sentinel so the destination never sees
                // null/empty in this column.
                'tags' => [
                    new PassThroughProcessor('tags'),
                    new DefaultValueProcessor(default: 'untagged'),
                ],
            ],
            destination: $destination,
        );
    }
}
