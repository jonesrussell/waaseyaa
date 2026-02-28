<?php

declare(strict_types=1);

namespace Aurora\Api\Tests\Unit;

use Aurora\Api\JsonApiError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonApiError::class)]
final class JsonApiErrorTest extends TestCase
{
    #[Test]
    public function constructsWithAllFields(): void
    {
        $error = new JsonApiError(
            status: '422',
            title: 'Validation Error',
            detail: 'The title field is required.',
            source: ['pointer' => '/data/attributes/title'],
        );

        $this->assertSame('422', $error->status);
        $this->assertSame('Validation Error', $error->title);
        $this->assertSame('The title field is required.', $error->detail);
        $this->assertSame(['pointer' => '/data/attributes/title'], $error->source);
    }

    #[Test]
    public function constructsWithMinimalFields(): void
    {
        $error = new JsonApiError(
            status: '500',
            title: 'Internal Server Error',
        );

        $this->assertSame('500', $error->status);
        $this->assertSame('Internal Server Error', $error->title);
        $this->assertSame('', $error->detail);
        $this->assertSame([], $error->source);
    }

    #[Test]
    public function toArrayIncludesRequiredFields(): void
    {
        $error = new JsonApiError(status: '404', title: 'Not Found');
        $array = $error->toArray();

        $this->assertSame('404', $array['status']);
        $this->assertSame('Not Found', $array['title']);
        $this->assertArrayNotHasKey('detail', $array);
        $this->assertArrayNotHasKey('source', $array);
    }

    #[Test]
    public function toArrayIncludesOptionalFieldsWhenPresent(): void
    {
        $error = new JsonApiError(
            status: '422',
            title: 'Unprocessable Entity',
            detail: 'Field validation failed.',
            source: ['pointer' => '/data/attributes/name'],
        );

        $array = $error->toArray();

        $this->assertSame('Field validation failed.', $array['detail']);
        $this->assertSame(['pointer' => '/data/attributes/name'], $array['source']);
    }

    #[Test]
    public function notFoundFactory(): void
    {
        $error = JsonApiError::notFound('Entity not found.');

        $this->assertSame('404', $error->status);
        $this->assertSame('Not Found', $error->title);
        $this->assertSame('Entity not found.', $error->detail);
    }

    #[Test]
    public function forbiddenFactory(): void
    {
        $error = JsonApiError::forbidden('Access denied.');

        $this->assertSame('403', $error->status);
        $this->assertSame('Forbidden', $error->title);
        $this->assertSame('Access denied.', $error->detail);
    }

    #[Test]
    public function unprocessableFactory(): void
    {
        $error = JsonApiError::unprocessable(
            'Invalid value.',
            ['pointer' => '/data/attributes/status'],
        );

        $this->assertSame('422', $error->status);
        $this->assertSame('Unprocessable Entity', $error->title);
        $this->assertSame('Invalid value.', $error->detail);
        $this->assertSame(['pointer' => '/data/attributes/status'], $error->source);
    }

    #[Test]
    public function badRequestFactory(): void
    {
        $error = JsonApiError::badRequest('Malformed JSON.');

        $this->assertSame('400', $error->status);
        $this->assertSame('Bad Request', $error->title);
        $this->assertSame('Malformed JSON.', $error->detail);
    }

    #[Test]
    public function internalErrorFactory(): void
    {
        $error = JsonApiError::internalError('Something went wrong.');

        $this->assertSame('500', $error->status);
        $this->assertSame('Internal Server Error', $error->title);
        $this->assertSame('Something went wrong.', $error->detail);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new \ReflectionClass(JsonApiError::class);
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->isFinal());
    }
}
