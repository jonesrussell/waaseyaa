<?php

declare(strict_types=1);

namespace Waaseyaa\Migration\Tests\Unit\Plugin\Process;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Migration\Exception\ProcessException;
use Waaseyaa\Migration\Plugin\Process\TypeCoerceProcessor;
use Waaseyaa\Migration\Plugin\ProcessContext;
use Waaseyaa\Migration\Plugin\ReservedPluginIds;
use Waaseyaa\Migration\Plugin\SourceRecord;
use Waaseyaa\Migration\Plugin\WriteResult;
use Waaseyaa\Migration\SourceId;

#[CoversClass(TypeCoerceProcessor::class)]
final class TypeCoerceProcessorTest extends TestCase
{
    #[Test]
    public function id_is_type_coerce(): void
    {
        $p = new TypeCoerceProcessor('string');
        self::assertSame(ReservedPluginIds::TYPE_COERCE, $p->id());
        self::assertSame('stable', $p->stability());
    }

    #[Test]
    public function null_passes_through_unchanged(): void
    {
        $p = new TypeCoerceProcessor('int');
        self::assertNull($p->transform(null, $this->context()));
    }

    /**
     * @return iterable<string, array{string, mixed, mixed}>
     */
    public static function happyPathCases(): iterable
    {
        yield 'string from int'    => ['string', 42, '42'];
        yield 'string from bool'   => ['string', true, '1'];
        yield 'int from string'    => ['int', '7', 7];
        yield 'int from float'     => ['int', 3.0, 3];
        yield 'int from bool'      => ['int', true, 1];
        yield 'int from int'       => ['int', 5, 5];
        yield 'float from string'  => ['float', '3.14', 3.14];
        yield 'float from int'     => ['float', 2, 2.0];
        yield 'bool from "true"'   => ['bool', 'true', true];
        yield 'bool from "false"'  => ['bool', 'false', false];
        yield 'bool from "1"'      => ['bool', '1', true];
        yield 'bool from "0"'      => ['bool', '0', false];
        yield 'bool from true'     => ['bool', true, true];
        yield 'array from scalar'  => ['array', 'x', ['x']];
        yield 'array passthrough'  => ['array', ['a', 'b'], ['a', 'b']];
    }

    #[Test]
    #[DataProvider('happyPathCases')]
    public function coerces_happy_path(string $target, mixed $input, mixed $expected): void
    {
        $p = new TypeCoerceProcessor($target);
        self::assertSame($expected, $p->transform($input, $this->context()));
    }

    #[Test]
    public function int_coercion_failure_raises_process_exception(): void
    {
        $p = new TypeCoerceProcessor('int');

        try {
            $p->transform('not-an-int', $this->context());
            self::fail('Expected ProcessException');
        } catch (ProcessException $e) {
            self::assertSame(ProcessException::CODE_TYPE_COERCE_FAIL, $e->processCode);
            self::assertStringContainsString("'not-an-int'", $e->getMessage());
            self::assertStringContainsString('int', $e->getMessage());
        }
    }

    #[Test]
    public function float_coercion_failure_raises_process_exception(): void
    {
        $p = new TypeCoerceProcessor('float');

        $this->expectException(ProcessException::class);
        $p->transform('not-a-float', $this->context());
    }

    #[Test]
    public function bool_coercion_failure_raises_process_exception(): void
    {
        $p = new TypeCoerceProcessor('bool');

        $this->expectException(ProcessException::class);
        $p->transform('definitely-not-bool', $this->context());
    }

    #[Test]
    public function string_coercion_handles_arrays_as_json(): void
    {
        $p = new TypeCoerceProcessor('string');
        $result = $p->transform(['a' => 1, 'b' => 2], $this->context());

        self::assertSame('{"a":1,"b":2}', $result);
    }

    #[Test]
    public function rejects_unknown_target_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TypeCoerceProcessor('object');
    }

    private function context(): ProcessContext
    {
        return new ProcessContext(
            sourceRecord: new SourceRecord('csv', []),
            migrationId: 'm1',
            destinationField: 'count',
            lookup: static fn (string $m, SourceId $id): ?WriteResult => null,
        );
    }
}
