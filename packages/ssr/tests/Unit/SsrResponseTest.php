<?php

declare(strict_types=1);

namespace Aurora\SSR\Tests\Unit;

use Aurora\SSR\SsrResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SsrResponse::class)]
final class SsrResponseTest extends TestCase
{
    #[Test]
    public function constructionWithDefaults(): void
    {
        $response = new SsrResponse(content: '<h1>Hello</h1>');

        $this->assertSame('<h1>Hello</h1>', $response->content);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['Content-Type' => 'text/html; charset=UTF-8'], $response->headers);
    }

    #[Test]
    public function constructionWithCustomStatusAndHeaders(): void
    {
        $headers = ['Content-Type' => 'text/plain', 'X-Custom' => 'value'];
        $response = new SsrResponse(
            content: 'Not Found',
            statusCode: 404,
            headers: $headers,
        );

        $this->assertSame('Not Found', $response->content);
        $this->assertSame(404, $response->statusCode);
        $this->assertSame($headers, $response->headers);
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $reflection = new \ReflectionClass(SsrResponse::class);

        $this->assertTrue($reflection->getProperty('content')->isReadOnly());
        $this->assertTrue($reflection->getProperty('statusCode')->isReadOnly());
        $this->assertTrue($reflection->getProperty('headers')->isReadOnly());
    }
}
