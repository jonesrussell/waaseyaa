<?php

declare(strict_types=1);

namespace Waaseyaa\Bimaaji\Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Bimaaji\Mutation\MutationRequest;
use Waaseyaa\Bimaaji\Mutation\MutationResult;

#[CoversClass(MutationResult::class)]
final class MutationResultTest extends TestCase
{
    #[Test]
    public function success_result(): void
    {
        $request = new MutationRequest('add_field', 'node', 'body', ['type' => 'text']);
        $result = MutationResult::success($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($request, $result->request);
        $this->assertSame([], $result->errors);
    }

    #[Test]
    public function failure_result_with_errors(): void
    {
        $request = new MutationRequest('add_field', 'node', 'nonexistent');
        $result = MutationResult::failure($request, ['UNKNOWN_ENTITY_TYPE', 'UNKNOWN_FIELD']);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(['UNKNOWN_ENTITY_TYPE', 'UNKNOWN_FIELD'], $result->errors);
    }

    #[Test]
    public function to_array_includes_request_and_status(): void
    {
        $request = new MutationRequest('add_field', 'node', 'body', ['type' => 'text']);
        $result = MutationResult::success($request);

        $array = $result->toArray();
        $this->assertSame('success', $array['status']);
        $this->assertArrayHasKey('request', $array);
        $this->assertSame([], $array['errors']);
    }

    #[Test]
    public function failure_to_array_includes_errors(): void
    {
        $request = new MutationRequest('add_field', 'unknown', 'body');
        $result = MutationResult::failure($request, ['UNKNOWN_ENTITY_TYPE']);

        $array = $result->toArray();
        $this->assertSame('failure', $array['status']);
        $this->assertSame(['UNKNOWN_ENTITY_TYPE'], $array['errors']);
    }
}
