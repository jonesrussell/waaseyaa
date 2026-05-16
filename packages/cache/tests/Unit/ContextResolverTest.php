<?php

declare(strict_types=1);

namespace Waaseyaa\Cache\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\ContextNames;
use Waaseyaa\Cache\ContextRegistry;
use Waaseyaa\Cache\ContextResolver;
use Waaseyaa\Foundation\Http\RequestContext;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\Log\LoggerTrait;
use Waaseyaa\Foundation\Log\LogLevel;

#[CoversClass(ContextResolver::class)]
#[CoversClass(ContextRegistry::class)]
#[CoversClass(ContextNames::class)]
#[CoversClass(RequestContext::class)]
final class ContextResolverTest extends TestCase
{
    private function resolver(?LoggerInterface $logger = null): ContextResolver
    {
        return new ContextResolver(new ContextRegistry(), $logger);
    }

    #[Test]
    public function resolveUserRolesReturnsSortedJoined(): void
    {
        // Roles intentionally provided in non-alphabetical order: the resolver
        // must sort them ascending before joining. This pins determinism for
        // cache-key parity across PHP workers (FR-037).
        $request = new RequestContext(roles: ['editor', 'admin', 'reviewer']);

        $result = $this->resolver()->resolve(ContextNames::USER_ROLES, $request);

        $this->assertSame('admin,editor,reviewer', $result);
    }

    #[Test]
    public function resolveUserRolesAnonymousReturnsEmpty(): void
    {
        $request = new RequestContext(roles: []);

        $result = $this->resolver()->resolve(ContextNames::USER_ROLES, $request);

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveUserIdReturnsIntegerAsString(): void
    {
        $request = new RequestContext(accountId: 42);

        $result = $this->resolver()->resolve(ContextNames::USER_ID, $request);

        $this->assertSame('42', $result);
    }

    #[Test]
    public function resolveUserIdAnonymousReturnsEmpty(): void
    {
        $request = new RequestContext(accountId: null);

        $result = $this->resolver()->resolve(ContextNames::USER_ID, $request);

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveLanguageContentReturnsActiveLangcode(): void
    {
        $request = new RequestContext(activeLangcode: 'fr-CA');

        $result = $this->resolver()->resolve(ContextNames::LANGUAGE_CONTENT, $request);

        $this->assertSame('fr-CA', $result);
    }

    #[Test]
    public function resolveLanguageContentMissingReturnsEmpty(): void
    {
        $request = new RequestContext(activeLangcode: null);

        $result = $this->resolver()->resolve(ContextNames::LANGUAGE_CONTENT, $request);

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveLanguageInterfaceReturnsInterfaceLangcode(): void
    {
        $request = new RequestContext(interfaceLangcode: 'oj');

        $result = $this->resolver()->resolve(ContextNames::LANGUAGE_INTERFACE, $request);

        $this->assertSame('oj', $result);
    }

    #[Test]
    public function resolveLanguageInterfaceMissingReturnsEmpty(): void
    {
        $request = new RequestContext(interfaceLangcode: null);

        $result = $this->resolver()->resolve(ContextNames::LANGUAGE_INTERFACE, $request);

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveUrlQueryParamReturnsDecodedValue(): void
    {
        // RequestContext stores params already URL-decoded (single decode).
        $request = new RequestContext(queryParams: [
            'page' => '3',
            'q' => 'hello world',
            'category' => 'news',
        ]);

        $this->assertSame('3', $this->resolver()->resolve('url.query.page', $request));
        $this->assertSame('hello world', $this->resolver()->resolve('url.query.q', $request));
        $this->assertSame('news', $this->resolver()->resolve('url.query.category', $request));
    }

    #[Test]
    public function resolveUrlQueryParamMissingReturnsEmpty(): void
    {
        $request = new RequestContext(queryParams: ['page' => '1']);

        $result = $this->resolver()->resolve('url.query.nonexistent', $request);

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveUrlQueryParamWithEmptyValueReturnsEmpty(): void
    {
        $request = new RequestContext(queryParams: ['page' => '']);

        $result = $this->resolver()->resolve('url.query.page', $request);

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveUrlQueryPrefixOnlyReturnsEmpty(): void
    {
        // 'url.query.' with no param after the prefix — defensive default.
        $request = new RequestContext(queryParams: ['' => 'whatever']);

        $result = $this->resolver()->resolve('url.query.', $request);

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveUnknownContextLogsWarningReturnsEmpty(): void
    {
        $logger = new RecordingLogger();

        $result = $this->resolver($logger)->resolve('cookie.session', new RequestContext());

        $this->assertSame('', $result);
        $this->assertCount(1, $logger->records);
        $this->assertSame(LogLevel::WARNING, $logger->records[0]['level']);
        $this->assertSame('Unknown context name in resolver', $logger->records[0]['message']);
        $this->assertSame('cookie.session', $logger->records[0]['context']['context']);
    }

    #[Test]
    public function resolveUnknownContextNullLoggerDoesNotThrow(): void
    {
        // No logger argument -> NullLogger default -> still returns empty.
        $result = $this->resolver()->resolve('cookie.session', new RequestContext());

        $this->assertSame('', $result);
    }

    #[Test]
    public function resolveDeterministicAcrossInvocations(): void
    {
        $request = new RequestContext(
            roles: ['editor', 'admin', 'reviewer'],
            accountId: 7,
            activeLangcode: 'en',
            interfaceLangcode: 'oj',
            queryParams: ['page' => '2', 'category' => 'news'],
        );
        $resolver = $this->resolver();

        $names = [
            ContextNames::USER_ROLES,
            ContextNames::USER_ID,
            ContextNames::LANGUAGE_CONTENT,
            ContextNames::LANGUAGE_INTERFACE,
            'url.query.page',
            'url.query.category',
        ];

        foreach ($names as $name) {
            $first = $resolver->resolve($name, $request);
            $second = $resolver->resolve($name, $request);
            $third = $resolver->resolve($name, $request);

            $this->assertSame($first, $second, "Resolution of {$name} drifted on second call");
            $this->assertSame($second, $third, "Resolution of {$name} drifted on third call");
        }
    }

    #[Test]
    public function resolveUserRolesShuffledInputIsDeterministic(): void
    {
        // Two RequestContexts with the same role set but different orderings
        // MUST produce the same resolved string.
        $a = new RequestContext(roles: ['admin', 'editor', 'reviewer']);
        $b = new RequestContext(roles: ['reviewer', 'admin', 'editor']);
        $c = new RequestContext(roles: ['editor', 'reviewer', 'admin']);

        $resolver = $this->resolver();
        $resolvedA = $resolver->resolve(ContextNames::USER_ROLES, $a);
        $resolvedB = $resolver->resolve(ContextNames::USER_ROLES, $b);
        $resolvedC = $resolver->resolve(ContextNames::USER_ROLES, $c);

        $this->assertSame($resolvedA, $resolvedB);
        $this->assertSame($resolvedB, $resolvedC);
        $this->assertSame('admin,editor,reviewer', $resolvedA);
    }

    #[Test]
    public function resolveRespectsExtensionRegisteredNames(): void
    {
        // An extension package registers a custom name. The resolver does not
        // have a dedicated branch for it, so the default arm returns '' — but
        // the lookup must not log a warning (the name IS registered).
        $registry = new ContextRegistry();
        $registry->register('extension.tenant');

        $logger = new RecordingLogger();
        $resolver = new ContextResolver($registry, $logger);

        $result = $resolver->resolve('extension.tenant', new RequestContext());

        $this->assertSame('', $result);
        $this->assertCount(0, $logger->records, 'Registered extension name must not warn');
    }
}

/**
 * Test double that captures all log calls in memory.
 *
 * @internal
 */
final class RecordingLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<array{level: LogLevel, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log(LogLevel $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
