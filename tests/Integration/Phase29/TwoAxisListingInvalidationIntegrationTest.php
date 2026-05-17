<?php

declare(strict_types=1);

namespace Waaseyaa\Tests\Integration\Phase29;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\CacheBackendInterface;
use Waaseyaa\Cache\CacheItem;
use Waaseyaa\Cache\TaggedCacheInterface;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\TranslatableInterface;
use Waaseyaa\EntityStorage\Event\AfterDeleteEvent;
use Waaseyaa\EntityStorage\Event\AfterSaveEvent;
use Waaseyaa\EntityStorage\SaveContext;
use Waaseyaa\Listing\ListingCacheInvalidator;

/**
 * Phase 29 integration: M-004 / WP07 — listing-cache invalidation for
 * two-axis (revisionable × translatable) entities.
 *
 * **Substrate audit:** M-007 already shipped {@see ListingCacheInvalidator}
 * with the `entity:<type>:<id>:<langcode>` tag vocabulary (FR-039) and the
 * additive {@see AfterSaveEvent::affectedLangcodes()} surface. This test
 * verifies the substrate composes correctly with the WP03 two-axis save
 * path described in §3.6 of the M-004 spec:
 *
 *  - **FR-032** — saving a two-axis entity emits both the langcode-less
 *    base tag (`entity:<type>:<id>`) AND a langcode-scoped tag
 *    (`entity:<type>:<id>:<langcode>`) per affected langcode.
 *  - Multi-language atomic saves (via `SaveContext::withTranslations`) emit
 *    **one langcode-scoped tag per affected langcode** plus the langcode-less
 *    tag (no duplicate-tag inflation).
 *  - Default-langcode-only saves still emit a default-langcode-scoped tag
 *    (no "single-axis fallback" shortcut that drops the langcode dimension).
 *  - `AfterDeleteEvent` mirrors `AfterSaveEvent`: every affected langcode
 *    gets a scoped invalidation.
 *
 * The test does not invoke `RevisionableStorageDriver::writeRevision()`
 * directly — that path is exercised by `TwoAxisSaveLoadIntegrationTest`.
 * Here we synthesise the `AfterSaveEvent`/`AfterDeleteEvent` shapes that
 * the dispatcher will emit, and assert the invalidator emits the contractual
 * tag set. This isolates the FR-032 verification from coordinator wiring
 * (which is still in flight).
 */
#[CoversNothing]
final class TwoAxisListingInvalidationIntegrationTest extends TestCase
{
    private function makeTwoAxisEntity(
        string $entityTypeId,
        int|string $id,
        string $activeLangcode,
    ): EntityInterface&TranslatableInterface {
        return new class ($entityTypeId, $id, $activeLangcode) implements EntityInterface, TranslatableInterface {
            public function __construct(
                private readonly string $typeId,
                private readonly int|string $idValue,
                private readonly string $activeLc,
            ) {}

            // --- EntityInterface ---

            public function id(): int|string|null
            {
                return $this->idValue;
            }

            public function uuid(): string
            {
                return '00000000-0000-0000-0000-00000000004A';
            }

            public function label(): string
            {
                return 'teaching-' . $this->idValue;
            }

            public function getEntityTypeId(): string
            {
                return $this->typeId;
            }

            public function bundle(): string
            {
                return $this->typeId;
            }

            public function isNew(): bool
            {
                return false;
            }

            public function get(string $name): mixed
            {
                return null;
            }

            public function set(string $name, mixed $value): static
            {
                return $this;
            }

            public function toArray(): array
            {
                return [];
            }

            public function language(): string
            {
                return $this->activeLc;
            }

            // --- TranslatableInterface ---

            public function defaultLangcode(): string
            {
                return 'en';
            }

            public function activeLangcode(): string
            {
                return $this->activeLc;
            }

            public function hasTranslation(string $langcode): bool
            {
                return $langcode === $this->activeLc || $langcode === 'en';
            }

            public function getTranslation(string $langcode): static
            {
                return $this;
            }

            public function addTranslation(string $langcode): static
            {
                return $this;
            }

            public function removeTranslation(string $langcode): void {}

            public function translations(): iterable
            {
                return [];
            }

            public function getTranslationLanguages(): array
            {
                return ['en', $this->activeLc];
            }
        };
    }

    #[Test]
    public function singleLangcodeSaveEmitsBaseAndLangcodeScopedTags(): void
    {
        // FR-032 — saving the French translation of teaching/42 emits
        // entity:teaching, entity:teaching:42, entity:teaching:42:fr.
        $cache = new RecordingTwoAxisCache();
        $invalidator = new ListingCacheInvalidator($cache);

        $entity = $this->makeTwoAxisEntity('teaching', 42, 'fr');
        $event = new AfterSaveEvent(
            $entity,
            SaveContext::default(),
            true,
            affectedLangcodes: ['fr'],
        );

        $invalidator->onAfterSave($event);

        self::assertSame(
            ['entity:teaching', 'entity:teaching:42', 'entity:teaching:42:fr'],
            $cache->invalidatedTags,
        );
    }

    #[Test]
    public function multiLangcodeAtomicSaveEmitsOneScopedTagPerAffectedLangcode(): void
    {
        // FR-032 — multi-language atomic save (SaveContext::withTranslations)
        // emits exactly one langcode-scoped tag per affected langcode plus
        // the langcode-less tag.
        $cache = new RecordingTwoAxisCache();
        $invalidator = new ListingCacheInvalidator($cache);

        $entity = $this->makeTwoAxisEntity('teaching', 42, 'en');
        $event = new AfterSaveEvent(
            $entity,
            SaveContext::default()->withTranslations(['en', 'oj', 'fr']),
            true,
            affectedLangcodes: ['en', 'fr', 'oj'],
        );

        $invalidator->onAfterSave($event);

        self::assertSame(
            [
                'entity:teaching',
                'entity:teaching:42',
                'entity:teaching:42:en',
                'entity:teaching:42:fr',
                'entity:teaching:42:oj',
            ],
            $cache->invalidatedTags,
            'Multi-language atomic save must emit one scoped tag per affected langcode + the base tag.',
        );
    }

    #[Test]
    public function defaultLangcodeOnlySaveStillEmitsScopedTag(): void
    {
        // FR-032 — saving a non-translatable field on the default langcode
        // still emits the default-langcode-scoped tag. No "single-axis
        // fallback" shortcut.
        $cache = new RecordingTwoAxisCache();
        $invalidator = new ListingCacheInvalidator($cache);

        $entity = $this->makeTwoAxisEntity('teaching', 42, 'en');
        $event = new AfterSaveEvent(
            $entity,
            SaveContext::default(),
            true,
            affectedLangcodes: ['en'],
        );

        $invalidator->onAfterSave($event);

        self::assertSame(
            ['entity:teaching', 'entity:teaching:42', 'entity:teaching:42:en'],
            $cache->invalidatedTags,
        );
    }

    #[Test]
    public function deleteMirrorsSaveForAffectedLangcodes(): void
    {
        // FR-032 (delete arm) — translation deletion emits the same tag
        // shape, ensuring downstream listing cache entries scoped to the
        // removed langcode are invalidated.
        $cache = new RecordingTwoAxisCache();
        $invalidator = new ListingCacheInvalidator($cache);

        $entity = $this->makeTwoAxisEntity('teaching', 42, 'oj');
        $event = new AfterDeleteEvent(
            $entity,
            affectedLangcodes: ['oj'],
        );

        $invalidator->onAfterDelete($event);

        self::assertSame(
            ['entity:teaching', 'entity:teaching:42', 'entity:teaching:42:oj'],
            $cache->invalidatedTags,
        );
    }

    #[Test]
    public function fallsBackToActiveLangcodeWhenAffectedLangcodesIsNull(): void
    {
        // Backwards compatibility: pre-WP03 dispatcher paths that did not
        // populate affectedLangcodes() must still emit a scoped tag based
        // on TranslatableInterface::activeLangcode().
        $cache = new RecordingTwoAxisCache();
        $invalidator = new ListingCacheInvalidator($cache);

        $entity = $this->makeTwoAxisEntity('teaching', 7, 'oj');
        $event = new AfterSaveEvent(
            $entity,
            SaveContext::default(),
            false,
            // affectedLangcodes deliberately null
        );

        $invalidator->onAfterSave($event);

        self::assertSame(
            ['entity:teaching', 'entity:teaching:7', 'entity:teaching:7:oj'],
            $cache->invalidatedTags,
        );
    }
}

/**
 * Records every invalidateByTag() call so tests can assert the exact tag
 * vocabulary M-007 emits for two-axis entities.
 *
 * @internal
 */
final class RecordingTwoAxisCache implements TaggedCacheInterface
{
    /** @var list<string> */
    public array $invalidatedTags = [];

    public function setWithTags(string $key, mixed $value, array $tags, ?int $ttl = null): void {}

    public function invalidateByTag(string $tag): int
    {
        $this->invalidatedTags[] = $tag;

        return 0;
    }

    public function getTagsFor(string $key): array
    {
        return [];
    }

    public function get(string $cid): CacheItem|false
    {
        return false;
    }

    public function getMultiple(array &$cids): array
    {
        return [];
    }

    public function set(string $cid, mixed $data, int $expire = CacheBackendInterface::PERMANENT, array $tags = []): void {}

    public function delete(string $cid): void {}

    public function deleteMultiple(array $cids): void {}

    public function deleteAll(): void {}

    public function invalidate(string $cid): void {}

    public function invalidateMultiple(array $cids): void {}

    public function invalidateAll(): void {}

    public function removeBin(): void {}
}
