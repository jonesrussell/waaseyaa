# Controller-Shape Audit

**Mission**: `post-1390-dispatcher-reconciliation-01KQTTJS`
**Generated**: 2026-05-05 against `main` (alpha.172).
**Authoritative for**: WP02 / WP03 (sets the framework's own deprecation noise budget post-#1390).

---

## Summary

| Metric                                                                                          | Count |
|------------------------------------------------------------------------------------------------|-------|
| First-party controller files surveyed                                                           | 22    |
| Controllers wired through `SsrPageHandler` → `AppControllerMethodInvoker` (subject to dispatcher) | 1     |
| Controller methods with `array $params` / `array $query` (any) inside dispatcher-subject controllers | 4     |
| Methods classified `relies_on_shim`                                                             | 4     |
| Methods classified `attribute_annotated`                                                        | 0     |
| Methods classified `no_array_params` (in dispatcher-subject controllers)                        | 0     |

**Headline**: when #1390's shim + WP02's deprecation emission ship, the framework itself emits **8 deprecation events per process** (4 methods × 2 implicit-array params each = 8), all from `Waaseyaa\Genealogy\Ssr\GenealogySsrController`. Every other framework-shipped "controller" uses a different invocation pipeline and is not subject to this dispatcher.

## Why the dispatcher-subject set is so small

`AppControllerMethodInvoker` (the host of `AppParameterBindingBuilder`) has exactly one caller in the codebase: `packages/ssr/src/SsrPageHandler.php`. Every controller that does NOT route through `SsrPageHandler` uses an independent invocation path — JSON:API has its own dispatcher (`packages/api/src/JsonApiController.php` is invoked directly from JSON:API routing); auth controllers run inside Fortify-style flows; MCP routing lives in `McpEndpoint`; the various router classes (`DiscoveryRouter`, `EntityTypeLifecycleRouter`) are dispatched manually and use their own positional-arg conventions.

This means the framework's *own* exposure to the post-#1390 deprecation contract is narrow. **Consumer apps (Minoo, etc.)** that wire all their controllers through SSR-style routing have wide exposure (#1390 quotes 184 methods across 37 files in Minoo).

Verification: `rg -l AppControllerMethodInvoker packages/` returns only `packages/ssr/src/SsrPageHandler.php` and the invoker file itself.

## Dispatcher-subject controllers (full audit)

Only the SSR-routed controller surface participates in the shim. Listed top-down:

### `Waaseyaa\Genealogy\Ssr\GenealogySsrController`

File: `packages/genealogy/src/Ssr/GenealogySsrController.php`

| controller_class                                         | method         | category        | notes                                                                                                                       |
|----------------------------------------------------------|----------------|-----------------|------------------------------------------------------------------------------------------------------------------------------|
| `Waaseyaa\Genealogy\Ssr\GenealogySsrController`          | `landing`      | relies_on_shim  | Signature: `(array $params, array $query, AccountInterface $account, Request $request)`. Two events fire (params + query).   |
| `Waaseyaa\Genealogy\Ssr\GenealogySsrController`          | `person`       | relies_on_shim  | Same signature shape. Two events.                                                                                            |
| `Waaseyaa\Genealogy\Ssr\GenealogySsrController`          | `family`       | relies_on_shim  | Same signature shape. Two events.                                                                                            |
| `Waaseyaa\Genealogy\Ssr\GenealogySsrController`          | `ancestorChart`| relies_on_shim  | Same signature shape. Two events.                                                                                            |

Total events emitted per process by this controller: **8** (4 methods × 2 implicit-array params). All can be silenced post-shim by adding `#[MapRoute]` / `#[MapQuery]` to the `params` / `query` parameters respectively.

## Non-dispatcher-subject matches (informational, NOT subject to the shim)

These files contain `array $params` / `array $query` but are NOT subject to the `AppParameterBindingBuilder` contract. Listed for completeness so a future audit doesn't re-flag them:

| File                                                              | Method                          | Why excluded                                                                            |
|-------------------------------------------------------------------|---------------------------------|------------------------------------------------------------------------------------------|
| `packages/api/src/JsonApiController.php`                          | `index`, `show`                 | JSON:API uses its own dispatch path; not routed through `SsrPageHandler`.               |
| `packages/api/src/Controller/CodifiedContextController.php`       | `listSessions`                  | Same as above.                                                                           |
| `packages/api/src/Controller/{Translation,Broadcast,FieldAutoSave,Schema}Controller.php` | various | JSON:API path.                                                                           |
| `packages/api/src/ApiDiscoveryController.php`                     | various                         | API path.                                                                                |
| `packages/auth/src/Controller/*Controller.php` (9 files)          | various                         | Fortify-style auth flows; not SSR-page routed.                                            |
| `packages/user/src/Http/AuthController.php`                       | various                         | Auth path.                                                                               |
| `packages/mcp/src/McpController.php`                              | `handleToolIntrospection`, `handleToolCall` (private) | Not controller actions; private helpers inside MCP endpoint.            |
| `packages/mcp/src/McpEndpoint.php`                                | `handleToolsCall` (private)     | Same — private helper.                                                                   |
| `packages/api/src/Query/QueryParser.php`                          | various                         | Not a controller; query-string utility.                                                  |
| `packages/api/src/Http/Router/DiscoveryRouter.php`                | `handleTopicHub`, etc. (private)| Router; takes a `WaaseyaaContext`, not a Symfony `Request`. Not subject to AppController. |
| `packages/foundation/src/Http/Router/EntityTypeLifecycleRouter.php` | `disableType`, `enableType` (private) | Router; same exclusion.                                                              |
| `packages/foundation/src/Http/Inbound/InboundHttpRequest.php`     | `fromSymfonyRequest` (static)   | Factory method, not a controller.                                                        |
| `packages/oidc/src/Authorize/AuthorizeController.php`             | `appendQuery` (private)         | Private utility.                                                                         |
| `packages/oidc/src/Authorize/AuthorizationRequestValidator.php`   | `validate`, `stringOrNull`      | Validator, not a controller.                                                             |
| `packages/admin-surface/src/Host/{Abstract,Generic}AdminSurfaceHost.php` | `list`                  | Admin surface host abstraction; no Symfony Request involvement.                          |
| `packages/i18n/src/Translator.php` and `TranslatorInterface.php`  | `trans`, `replaceParams`        | Translator service; not a controller.                                                    |
| `packages/i18n/src/Twig/TranslationTwigExtension.php`             | `trans`                         | Twig extension; not a controller.                                                        |
| `packages/billing/src/StripeClientInterface.php` and `FakeStripeClient.php` | `createCheckoutSession` | Stripe client.                                                                           |
| `packages/northcloud/src/Client/NorthCloudClient.php`             | `search`, `buildQueryString`    | HTTP client.                                                                             |
| `packages/search/src/Fts5/Fts5SearchProvider.php`                 | `buildFacets` (private)         | Search provider internal.                                                                |

## Implications for the next alpha

1. **Framework's deprecation-noise floor is 8 lines per process** (one per `(GenealogySsrController, method, parameter_name)` triple, consolidated by dedup). Acceptable.
2. **Genealogy controller is the canonical "good migration" example** — once the next alpha ships, an immediate follow-up issue can convert these four methods to `#[MapRoute] array $params, #[MapQuery] array $query` and shrink the floor to zero. This is voluntary, not blocking; filed as a separate issue if desired.
3. **Audit is brittle to controller-discovery method**. The grep used here found controller-shaped files by `extends.*Controller` and `^final class.*Controller`. If future controllers adopt different conventions, re-run the audit by also tracing `RouteBuilder` registrations. For this alpha cycle, the inventory above is complete.

## How to reproduce this audit

```bash
# 1. Locate the dispatcher's only host:
rg -l 'AppControllerMethodInvoker' packages/ --type php
# Expected: packages/ssr/src/SsrPageHandler.php (only)

# 2. Find every method with implicit-array params/query in the codebase:
rg -n 'function \w+\([^)]*array \$(params|query)\b' packages/ --type php | grep -v tests/ | grep -v fixtures

# 3. For each match, decide: does the enclosing class route through SsrPageHandler?
#    The straightforward way is:
#      - Open the file.
#      - If the method is `private`, exclude (not a controller action).
#      - If the class is a router/utility/client/validator/factory, exclude.
#      - If the class is a controller registered to an SSR route, include.
#    Today only GenealogySsrController matches.

# 4. Cross-check by reading SsrPageHandler.php to see which controller classes
#    its routes resolve to.
```

## Out-of-band note

If a consumer app needs to inventory *its own* deprecation backlog post-shim, the canonical method is `tail -F` on the configured logger and `grep '"channel":"dispatcher.deprecation"'`. The schema is documented in `post-1390-dispatcher-contract.md` §5.
