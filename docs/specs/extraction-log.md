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

## 2026-04 — Mail API consolidation (#798, tracker #1157)

| | |
|---|---|
| **Change** | Removed parallel `MailDriverInterface` / `MailMessage` / `SendGridDriver` stack. |
| **Package** | `waaseyaa/mail` |
| **API** | `MailerInterface::send(Envelope)` only; `MailServiceProvider` binds `TransportInterface` → `SendGridTransport` when `mail.sendgrid_api_key` and `mail.from_address` are set (after trim), else `array` or `LocalTransport` per `mail.transport`. |
| **Framework consumers** | `AuthMailer` (injected `MailerInterface` + `authEmailConfigured` flag; `UserServiceProvider`), `MailChannel` / notifications (unchanged). |
| **App follow-up** | Minoo dropped duplicate `SendGridDriver` registration; `MailTestCommand`, `MessageDigestCommand` use `MailerInterface` / `Envelope`. |

## 2026-04 — Flash in SSR (#697, tracker #1157)

| | |
|---|---|
| **Status** | No new package: `Waaseyaa\SSR\Flash\Flash`, `FlashMessageService`, `FlashTwigExtension` already live under `packages/ssr`; tracker #1157 mail work does not relocate flash. |
