# Extraction Log

Tracks code extracted from app repos (minoo, claudriel) into the waaseyaa framework.

See `waaseyaa:framework-extraction` skill for the extraction process.

---

## 2026-04 — SlugGenerator (#692)

| | |
|---|---|
| **Source** | Minoo `Minoo\Support\SlugGenerator` (historical; removed from app tree) |
| **Package** | `waaseyaa/foundation` |
| **Class** | `Waaseyaa\Foundation\SlugGenerator` — `generate(string $value): string` |
| **Tests** | `packages/foundation/tests/Unit/SlugGeneratorTest.php` |
| **Consumers** | Minoo ingestion mappers (`NcArticleToEventMapper`, `DictionaryEntryMapper`, etc.) via `use Waaseyaa\Foundation\SlugGenerator` |

## 2026-04 — GeoDistance (#693)

| | |
|---|---|
| **Source** | Minoo `Minoo\Support\GeoDistance` (historical; removed from app tree) |
| **Package** | `waaseyaa/geo` (dedicated package; not merged into foundation to keep geospatial helpers optional) |
| **Class** | `Waaseyaa\Geo\GeoDistance` — `haversine(float $lat1, float $lon1, float $lat2, float $lon2): float` (kilometres) |
| **Tests** | `packages/geo/tests/Unit/GeoDistanceTest.php` |
| **Consumers** | Minoo (`waaseyaa/geo` in `composer.json`): `CommunityController`, `FeedController`, `FeedAssembler`, geo domain services, etc. |
