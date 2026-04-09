<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Tests\Unit\Http\Inbound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Foundation\Http\Inbound\InboundHttpRequest;

#[CoversClass(InboundHttpRequest::class)]
final class InboundHttpRequestTest extends TestCase
{
    #[Test]
    public function uses_dispatcher_route_params_and_query_not_request_bags(): void
    {
        $request = Request::create('/path', 'GET', ['fromBag' => '1']);
        $inbound = InboundHttpRequest::fromSymfonyRequest(
            $request,
            ['communitySlug' => 'foo'],
            ['page' => '3'],
        );

        self::assertSame('foo', $inbound->routeParam('communitySlug'));
        self::assertSame('3', $inbound->queryParam('page'));
        self::assertNull($inbound->queryParam('fromBag'));
        self::assertSame([], $inbound->body());
    }

    #[Test]
    public function header_lookup_is_case_insensitive(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-Test', 'alpha');

        $inbound = InboundHttpRequest::fromSymfonyRequest($request, [], []);

        self::assertSame('alpha', $inbound->header('X-Test'));
        self::assertSame('alpha', $inbound->header('x-test'));
        self::assertNull($inbound->header('X-Missing'));
    }

    #[Test]
    public function body_from_form_only(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], 'ignored');
        $request->request->set('name', 'Form');

        $inbound = InboundHttpRequest::fromSymfonyRequest($request, [], []);

        self::assertSame(['name' => 'Form'], $inbound->body());
    }

    #[Test]
    public function body_from_parsed_json_only(): void
    {
        $request = Request::create('/', 'POST');
        $request->attributes->set('_parsed_body', ['title' => 'JSON']);

        $inbound = InboundHttpRequest::fromSymfonyRequest($request, [], []);

        self::assertSame(['title' => 'JSON'], $inbound->body());
    }

    #[Test]
    public function body_merges_form_with_json_json_overlays_keys(): void
    {
        $request = Request::create('/', 'POST');
        $request->request->set('a', 'form');
        $request->request->set('b', 'both-form');
        $request->attributes->set('_parsed_body', ['b' => 'both-json', 'c' => 'json']);

        $inbound = InboundHttpRequest::fromSymfonyRequest($request, [], []);

        self::assertSame([
            'a' => 'form',
            'b' => 'both-json',
            'c' => 'json',
        ], $inbound->body());
    }

    #[Test]
    public function cookies_and_raw_content_snapshot(): void
    {
        $request = Request::create('/', 'GET', [], ['sid' => 'abc']);
        $request->headers->set('Content-Type', 'application/json');
        $inbound = InboundHttpRequest::fromSymfonyRequest($request, [], []);

        self::assertSame('abc', $inbound->cookie('sid'));
        self::assertNull($inbound->cookie('missing'));
        self::assertSame('', $inbound->rawContent());
    }

    #[Test]
    public function path_and_method_reflect_request(): void
    {
        $request = Request::create('/communities/foo/search', 'PATCH');
        $inbound = InboundHttpRequest::fromSymfonyRequest($request, [], []);

        self::assertSame('/communities/foo/search', $inbound->path());
        self::assertSame('PATCH', $inbound->method());
    }
}
