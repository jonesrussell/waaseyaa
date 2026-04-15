# Final Keyword Audit

**Date:** 2026-04-12
**Scope:** All `packages/*/src/**/*.php` production classes
**Method:** Full grep scan + per-class analysis of role, interfaces, DI, test usage
**Final methods in non-final classes:** None found anywhere in the codebase.

---

## Statistics

| Category | Count |
|---|---|
| Total `final class` in `src/` | ~250 |
| KEEP_FINAL | ~205 |
| REMOVE_FINAL | 22 |
| REVIEW_NEEDED | ~23 |
| Non-final concrete classes that should become `final` | ~26 |

---

## KEEP_FINAL

These are correctly sealed. Grouped by pattern.

### Entities (extends EntityBase/ContentEntityBase/ConfigEntityBase)
- `Node` (`packages/node/src/Node.php`)
- `NodeType` (`packages/node/src/NodeType.php`)
- `Term` (`packages/taxonomy/src/Term.php`)
- `Vocabulary` (`packages/taxonomy/src/Vocabulary.php`)
- `Media` (`packages/media/src/Media.php`)
- `MediaType` (`packages/media/src/MediaType.php`)
- `User` (`packages/user/src/User.php`)
- `UserBlock` (`packages/user/src/UserBlock.php`)
- `PathAlias` (`packages/path/src/PathAlias.php`)
- `Menu` (`packages/menu/src/Menu.php`)
- `MenuLink` (`packages/menu/src/MenuLink.php`)
- `Note` (`packages/note/src/Note.php`)
- `Relationship` (`packages/relationship/src/Relationship.php`)
- `Workflow` (`packages/workflows/src/Workflow.php`)
- `Pipeline` (`packages/ai-pipeline/src/Pipeline.php`)

### Value Objects / DTOs / Immutable Data
- `DiagnosticEntry`, `HealthCheckResult` (`packages/foundation/src/Diagnostic/`)
- `WaaseyaaContext` (`packages/foundation/src/Http/Router/`)
- `SovereigntyDefaults` (`packages/foundation/src/Sovereignty/`)
- `ColumnDefinition` (`packages/foundation/src/Migration/`)
- `DiscoveryCachePrimitives` (`packages/foundation/src/Cache/`)
- `EntityValues` (`packages/entity/src/EntityValues.php`)
- `EntityConstants` (`packages/entity/src/EntityConstants.php`)
- `EntityAuditEntry` (`packages/entity/src/Audit/`)
- `TimestampFieldConvention` (`packages/entity/src/DateTime/`)
- `EntityTypeValidationConstraints` (`packages/entity/src/Validation/`)
- `CacheConfiguration` (`packages/cache/src/`)
- `WorkerOptions` (`packages/queue/src/Worker/`)
- `ScheduledTask` (`packages/scheduler/src/`)
- `PaginationLinks` (`packages/api/src/Query/`)
- `PipelineContext` (`packages/ai-pipeline/src/`)
- `IngestionEnvelope`, `ValidationError` (`packages/note/src/Ingestion/`)
- `MenuTreeElement` (`packages/menu/src/`)
- `Envelope` (`packages/mail/src/`)
- `ActionDefinition`, `EntityDefinition`, `FieldDefinition` (`packages/admin-surface/src/Catalog/`)
- `TelescopeEntry`, `CodifiedContextEntry` (`packages/telescope/src/`)
- `InertiaResponse` (`packages/inertia/src/`)
- `PropertyValue` (`packages/field/src/`)

### Event DTOs
- `EntitySaved`, `EntityDeleted` (`packages/entity/src/Event/`)
- `ConfigEvent` (`packages/config/src/Listener/`)
- All `CodifiedContext/Event/*` classes (`packages/telescope/`)

### Exceptions (~24 classes)
All exception classes extending `\RuntimeException`, `\LogicException`, etc. Examples:
- `AccessDeniedException`, `StorageException`, `AuthenticationException`, `ConfigException`
- `CastException`, `EntityValidationException`, `CoercionException`
- `ImmutableConfigException`, `HttpRequestException`, `JsonApiDocumentException`
- `MaxIterationsException`, `RateLimitException`, `GitHubException`

### PHP Attribute Classes
- `AsEntityType`, `AsMiddleware`, `AsFieldType` (`packages/foundation/src/Attribute/`)
- `PolicyAttribute` (`packages/access/src/Gate/`)
- `OnQueue`, `RateLimited`, `UniqueJob` (`packages/queue/src/`)
- `GateAttribute` (`packages/routing/src/Attribute/`)
- `AsFormatter`, `Component` (`packages/ssr/src/Attribute/`)

### Classes Implementing Interfaces (~182 classes)

Substitution happens at the interface level; sealing the implementation is correct.

- All `HttpMiddlewareInterface` implementors (~15 across foundation, access, user, debug, telescope, inertia)
- All `AccessPolicyInterface` implementors (node, taxonomy, media, path, menu, note, relationship, user, access)
- All `CacheBackendInterface` / `TagAwareCacheInterface` implementors (cache)
- All `DatabaseInterface` / schema / query implementors (database-legacy)
- All `TransportInterface` / `QueueInterface` / `FailedJobRepositoryInterface` implementors (queue)
- All `StorageInterface` / `ConfigFactoryInterface` / `ConfigManagerInterface` / `ConfigInterface` implementors (config)
- All `LoggerInterface` / `HandlerInterface` / `FormatterInterface` / `ProcessorInterface` implementors (foundation)
- All `DomainRouterInterface` implementors (foundation, api, graphql, media, ssr)
- All `LanguageNegotiatorInterface` implementors (routing)
- All `SearchProviderInterface` / `SearchIndexerInterface` implementors (search)
- All `ChannelInterface` implementors (notification)
- All `EmbeddingInterface` / `VectorStoreInterface` / `EmbeddingStorageInterface` implementors (ai-vector)
- All `ProviderInterface` / `StreamingProviderInterface` / `ToolRegistryInterface` implementors (ai-agent)
- All `FieldFormatterInterface` implementors (ssr)
- All `CodifiedContextStoreInterface` / `TelescopeStoreInterface` implementors (telescope)
- All `LockInterface` implementors (scheduler)
- All `StateInterface` implementors (state)
- All `TypedDataManagerInterface` / `PrimitiveInterface` / `ComplexDataInterface` implementors (typed-data)
- All `PluginFactoryInterface` / `PluginDiscoveryInterface` implementors (plugin)
- All `EntityStorageDriverInterface` / `ConnectionResolverInterface` / `EntityRepositoryInterface` implementors (entity-storage)
- All `EntityClockInterface` / `EntityEventFactoryInterface` implementors (entity)
- `Mailer` implements `MailerInterface`, all `TransportInterface` implementors (mail)
- `StreamHttpClient` implements `HttpClientInterface` (http-client)
- `LanguageManager` implements `LanguageManagerInterface`, `Translator` implements `TranslatorInterface` (i18n)
- `WorkflowVisibilityFilter` implements `VisibilityFilterInterface` (workflows)
- `Schedule` implements `ScheduleInterface` (scheduler)
- `RateLimiter`, `DatabaseRateLimiter` implement `RateLimiterInterface` (auth)
- `AuthTokenRepository` implements `AuthTokenRepositoryInterface` (auth)
- `SovereigntyConfig` implements `SovereigntyConfigInterface` (foundation)

### Service Providers (~30 classes)
All `*ServiceProvider extends ServiceProvider` across all packages. Leaf wiring, never subclassed.

### Controllers (~32 classes)
All controllers across auth, api, ai-vector, debug, mcp, ssr. Leaf nodes wired to routes; extension is via new routes/controllers.

### CLI Commands (~40 classes)
All `*Command extends Command` in `packages/cli/src/Command/`. Leaf entry points.

### Kernels
- `HttpKernel`, `ConsoleKernel` (`packages/foundation/src/Kernel/`): Sealed intentionally; extension via service providers.

### Symfony Constraints / Validators
All constraint + validator pairs in `packages/validation/src/`.

### Twig Extensions
- `TranslationTwigExtension` (i18n), `SearchTwigExtension` (search), `SeoTwigExtension` (seo), `FlashTwigExtension`, `WaaseyaaExtension` (ssr)

### Infrastructure / Bootstrap / Compile-time
- `ContainerCompiler`, `ProviderDiscovery`, `EnvLoader`, `SlugGenerator` (foundation)
- All `*Bootstrapper` classes (foundation kernel bootstrap)
- `DiagnosticEmitter`, `BootDiagnosticReport`, `PackageManifest`, `PackageManifestCompiler` (foundation)
- `SchemaBuilder`, `Migrator`, `MigrationLoader`, `MigrationRepository`, `TableBuilder` (foundation migration)
- `EntityInstantiator` (entity-storage)
- `EditorLinkGenerator` (error-handler)
- `AdminPackagePathResolver`, `ComposerProvenanceReporter` (cli)
- `EmbeddingProviderFactory` (ai-vector)
- `CastTokenMapper` (typed-data), `EntityTypeIdNormalizer` (entity)

### Pipelines / Builders
- `HttpPipeline`, `JobPipeline` (foundation)
- `ScheduleBuilder` (scheduler), `RouteBuilder` (routing), `CatalogBuilder` (admin-surface)

### Null Objects / Test Doubles
- `NullLogger`, `NullTenantResolver`, `InMemoryRateLimiter` (foundation)
- `AnonymousUser`, `DevAdminAccount` (user)
- `NullLlmProvider` (ai-agent), `FakeStripeClient` (billing)
- `FakeEmbeddingProvider`, `InMemoryVectorStore` (ai-vector)
- `FixedEntityClock` (entity)

### Event Listeners / Subscribers (~18 classes)
All `*Listener`, `*Subscriber`, `*Invalidator` classes. Extension seam is the event dispatcher.

### Other correct uses
- All GraphQL internals: `GraphQlAccessGuard`, `EntityResolver`, `ReferenceLoader`, `EntityTypeBuilder`, `FieldTypeMapper`, `SchemaFactory`, `TypeRegistry`
- All MCP tools: `DiscoveryTools`, `EditorialTools`, `EntityTools`, `TraversalTools`
- All Telescope recorders and validators
- `OpenApiGenerator`, `SchemaBuilder`, `SchemaPresenter` (api)
- `QueryParser`, `QueryApplier`, `SparseFieldsetApplicator` (api)
- `ComponentRenderer`, `EntityRenderer`, `LanguageResolver`, `RenderCache` (ssr)
- `Flash`, `FlashMessageService` (ssr)
- `Inertia`, `PropResolver` (inertia)
- `ResponseFormatter`, `ToolIntrospector`, `ReadCache` (mcp)
- `AuthoringRoleMatrix`, `ContentModerator`, `DomainValidationListener`, `EditorialWorkflowPreset`, `WorkflowVisibility` (workflows)
- `JsonLdBuilder`, `MetaTagBuilder`, `RobotsTxtGenerator` (seo)
- `WebhookHandler` (billing), `McpServer` (ai-agent)
- `PipelineDispatcher` (ai-pipeline), `EntityEmbedder`, `SemanticIndexWarmer` (ai-vector)
- `EntityJsonSchemaGenerator`, `McpToolExecutor`, `McpToolGenerator`, `TranslationToolGenerator` (ai-schema)
- `DiscoveryApiHandler`, `SurfaceQueryParser` (admin-surface/api)
- `FieldDefinitionConstraintBuilder` (entity)
- `LanguageNegotiator` (routing, composite), `EntityParamConverter` (routing)
- `KnowledgeToolingExtensionRunner` (plugin), `AttributeGuard` (queue)
- `TenantContext` (foundation, `@internal`)

---

## REMOVE_FINAL

These classes are sealed but lack interfaces, blocking legitimate substitution or testability. The common pattern: `final class` with constructor DI but no interface.

**Important note:** For many of these, the ideal fix is **add an interface AND keep `final`**. The interface provides the substitution seam; `final` protects the implementation. "REMOVE_FINAL" means "the current state is wrong" because there is no substitution seam at all.

### Layer 1: Core Data

| Class | File | Reason | Proposed Seam |
|---|---|---|---|
| `AccessChecker` | `packages/access/src/AccessChecker.php` | Tested by routing package; consumers cannot substitute access-checking logic | Extract `AccessCheckerInterface` |
| `AuthManager` | `packages/auth/src/AuthManager.php` | Session-dependent auth service; tests cannot substitute session behaviour | Extract `AuthManagerInterface` |
| `TwoFactorManager` | `packages/auth/src/TwoFactorManager.php` | Uses `time()` internally; impossible to test time-window edge cases | Extract `TwoFactorManagerInterface` + inject `EntityClockInterface` |

### Layer 3: Services

| Class | File | Reason | Proposed Seam |
|---|---|---|---|
| `EditorialTransitionAccessResolver` | `packages/workflows/src/EditorialTransitionAccessResolver.php` | Hardcoded role-based access; apps cannot customize transition rules | Extract `TransitionAccessResolverInterface` |
| `EditorialVisibilityResolver` | `packages/workflows/src/EditorialVisibilityResolver.php` | No interface; apps need custom editorial visibility | Extract `VisibilityResolverInterface` |
| `EditorialWorkflowService` | `packages/workflows/src/EditorialWorkflowService.php` | Core workflow service with DI; blocks app-specific behaviour | Extract `EditorialWorkflowServiceInterface` |
| `SitemapGenerator` | `packages/seo/src/SitemapGenerator.php` | Has `EntityTypeManagerInterface` DI; apps need custom sitemap logic | Extract `SitemapGeneratorInterface` |
| `NotificationDispatcher` | `packages/notification/src/NotificationDispatcher.php` | Core dispatcher; cannot substitute with fake in tests | Extract `NotificationDispatcherInterface` |
| `BillingManager` | `packages/billing/src/BillingManager.php` | Core billing with tier logic; cannot extend for custom tiers | Extract `BillingManagerInterface` |

### Layer 4: API

| Class | File | Reason | Proposed Seam |
|---|---|---|---|
| `JsonApiController` | `packages/api/src/JsonApiController.php` | Core CRUD controller with heavy DI; apps may need custom resource behaviour | Remove `final`; extend or decorate via collaborator injection |
| `JsonApiRouteProvider` | `packages/api/src/JsonApiRouteProvider.php` | Route provider; apps need custom route generation | Extract `RouteProviderInterface` |
| `ResourceSerializer` | `packages/api/src/ResourceSerializer.php` | Heavy DI serializer; custom entity serialization blocked | Extract `ResourceSerializerInterface` |
| `WaaseyaaRouter` | `packages/routing/src/WaaseyaaRouter.php` | Core router wrapping Symfony; cannot decorate or extend | Extract `RouterInterface` |

### Layer 5: AI

| Class | File | Reason | Proposed Seam |
|---|---|---|---|
| `AgentExecutor` | `packages/ai-agent/src/AgentExecutor.php` | Core execution; blocks substitution for testing or custom strategies | Extract `AgentExecutorInterface` |
| `EmbeddingPipeline` | `packages/ai-pipeline/src/EmbeddingPipeline.php` | Service with DI; cannot substitute pipeline logic | Extract `EmbeddingPipelineInterface` |
| `PipelineExecutor` | `packages/ai-pipeline/src/PipelineExecutor.php` | Core execution engine; cannot customize strategy | Extract `PipelineExecutorInterface` |
| `SchemaRegistry` | `packages/ai-schema/src/SchemaRegistry.php` | Central coordinating registry; cannot substitute for custom schema | Extract `SchemaRegistryInterface` |

### Layer 6: Interfaces

| Class | File | Reason | Proposed Seam |
|---|---|---|---|
| `CliCommandRegistry` | `packages/cli/src/CliCommandRegistry.php` | Registry; apps cannot substitute command discovery | Extract `CommandRegistryInterface` |
| `GraphQlEndpoint` | `packages/graphql/src/GraphQlEndpoint.php` | Core endpoint, heavy DI; cannot substitute | Extract `GraphQlEndpointInterface` |
| `ComponentRegistry` | `packages/ssr/src/ComponentRegistry.php` | Apps cannot extend component registration | Extract `ComponentRegistryInterface` |
| `FieldFormatterRegistry` | `packages/ssr/src/FieldFormatterRegistry.php` | Hard-registers formatters; cannot add custom formatters | Extract `FieldFormatterRegistryInterface` |
| `SsrPageHandler` | `packages/ssr/src/SsrPageHandler.php` | Central handler with 10+ DI deps; cannot substitute or decorate | Extract `PageHandlerInterface` |

---

## REVIEW_NEEDED

These require deeper architectural judgment. Grouped by concern.

### Core Services Without Interfaces

| Class | File | What to inspect |
|---|---|---|
| `ControllerDispatcher` | `packages/foundation/src/Http/ControllerDispatcher.php` | Is this ever swapped? Would apps need custom dispatch? |
| `EventListenerRegistrar` | `packages/foundation/src/Kernel/EventListenerRegistrar.php` | Should listener wiring be configurable? Has tight coupling to AI/Broadcast types. |
| `BuiltinRouteRegistrar` | `packages/foundation/src/Kernel/BuiltinRouteRegistrar.php` | Can apps modify built-in route registration? |
| `UnitOfWork` | `packages/entity-storage/src/UnitOfWork.php` | Is UoW ever substituted in tests or for custom transaction strategies? |
| `EntityStorageFactory` | `packages/entity-storage/src/EntityStorageFactory.php` | Do apps need custom storage factory logic? |
| `EntityTypeLifecycleManager` | `packages/entity/src/EntityTypeLifecycleManager.php` | Is lifecycle management ever customized per-app? |
| `EntityValidator` | `packages/entity/src/Validation/EntityValidator.php` | Do apps add custom validation logic beyond constraints? |
| `ValueCaster` | `packages/entity/src/Cast/ValueCaster.php` | Can apps define custom casts beyond the built-in set? |

### Queue / Scheduler Infrastructure

| Class | File | What to inspect |
|---|---|---|
| `Worker` | `packages/queue/src/Worker/Worker.php` | Would apps need custom worker loop behaviour? |
| `ScheduleRunner` | `packages/scheduler/src/ScheduleRunner.php` | Is custom schedule execution needed? |
| `ScheduleStateRepository` | `packages/scheduler/src/ScheduleStateRepository.php` | Would alternate state backends be needed? |
| `BatchedJobs` / `ChainedJobs` | `packages/queue/src/` | Is batching/chaining customizable? |

### Error Handling

| Class | File | What to inspect |
|---|---|---|
| `ExceptionRenderer` | `packages/error-handler/src/ExceptionRenderer.php` | Do apps customize error page rendering? SSR has `ErrorPageRendererInterface` but this class doesn't implement it. |
| `DevExceptionRenderer` | `packages/error-handler/src/DevExceptionRenderer.php` | Same question for dev mode. |
| `SolutionProviderRegistry` | `packages/error-handler/src/SolutionProviderRegistry.php` | Can apps register custom solution providers? |

### Content Domain Services

| Class | File | What to inspect |
|---|---|---|
| `MenuTreeBuilder` | `packages/menu/src/MenuTreeBuilder.php` | Do apps need custom tree-building logic? |
| `PathProcessor` | `packages/path/src/PathProcessor.php` | Is path processing customizable? |
| `NoteIngester` | `packages/note/src/Ingestion/NoteIngester.php` | Is ingestion extended per-app? |
| `IngestionEnvelopeValidator` | `packages/note/src/Ingestion/IngestionEnvelopeValidator.php` | Same. |
| `RelationshipTraversalService` | `packages/relationship/src/RelationshipTraversalService.php` | Is relationship traversal extended by apps? |
| `RelationshipDiscoveryService` | `packages/relationship/src/RelationshipDiscoveryService.php` | Same. |
| `RelationshipSchemaManager` | `packages/relationship/src/RelationshipSchemaManager.php` | Same. |
| `RelationshipValidator` | `packages/relationship/src/RelationshipValidator.php` | Same. |

### Other

| Class | File | What to inspect |
|---|---|---|
| `EntityFactory` | `packages/testing/src/Factory/EntityFactory.php` | Do app test suites extend this? |
| `EntityTypeFixtureValues` | `packages/testing/src/Factory/EntityTypeFixtureValues.php` | Same. |
| `UserBlockService` | `packages/user/src/UserBlockService.php` | Tested, no interface. |
| `UserSession` | `packages/user/src/UserSession.php` | Tested, no interface. |
| `UploadHandler` | `packages/media/src/UploadHandler.php` | Alternative upload strategies needed? |
| `EntityAuditLogger` | `packages/entity/src/Audit/EntityAuditLogger.php` | Substitution for custom audit logging? |
| `SqlSchemaHandler` | `packages/entity-storage/src/SqlSchemaHandler.php` | Schema management substitution? |
| `EntitySchemaSync` | `packages/entity-storage/src/EntitySchemaSync.php` | Same. |
| `SqlEntityQueryResultCache` | `packages/entity-storage/src/SqlEntityQueryResultCache.php` | Cache strategy substitution? |
| `CommunityScope` | `packages/entity-storage/src/Tenancy/CommunityScope.php` | Scope filter substitution? |
| `RevisionableStorageDriver` | `packages/entity-storage/src/Driver/RevisionableStorageDriver.php` | May not implement `EntityStorageDriverInterface`; check. |
| `ConfigSchemaValidator` | `packages/config/src/Schema/ConfigSchemaValidator.php` | Substitution needed? |
| `ConstraintFactory` | `packages/validation/src/ConstraintFactory.php` | Substitution needed? |
| `CacheConfigResolver` | `packages/cache/src/CacheConfigResolver.php` | Substitution needed? |
| `TwigMailRenderer` | `packages/mail/src/Twig/TwigMailRenderer.php` | Renderer substitution? |
| `RedirectValidator` | `packages/access/src/RedirectValidator.php` | Custom redirect policies? |
| Ingestion pipeline classes in `packages/cli/src/Ingestion/` (~11 classes) | Various | Internal pipeline internals; tightly coupled; may need interfaces if apps add custom sources/validators. |

---

## Non-Final Concrete Classes That Should Become Final

These are `class Foo` (no `final`, no `abstract`) that are leaf classes with no protected extension points and not extended anywhere.

| Class | File | Notes |
|---|---|---|
| `EntityAccessHandler` | `packages/access/src/EntityAccessHandler.php` | Leaf, no protected members |
| `PermissionHandler` | `packages/access/src/PermissionHandler.php` | Has interface |
| `GenericAdminSurfaceHost` | `packages/admin-surface/src/Host/GenericAdminSurfaceHost.php` | Extends abstract base |
| `EntityTypeManager` | `packages/entity/src/EntityTypeManager.php` | Has interface |
| `EntityEvent` | `packages/entity/src/Event/EntityEvent.php` | Event DTO |
| `FieldTypeManager` | `packages/field/src/FieldTypeManager.php` | Extends base, has interface |
| `AuthMailer` | `packages/user/src/AuthMailer.php` | Leaf, no protected members |
| `BooleanItem` | `packages/field/src/Item/BooleanItem.php` | Leaf field item |
| `ComputedItem` | `packages/field/src/Item/ComputedItem.php` | Leaf field item |
| `DateItem` | `packages/field/src/Item/DateItem.php` | Leaf field item |
| `DateTimeItem` | `packages/field/src/Item/DateTimeItem.php` | Leaf field item |
| `DecimalItem` | `packages/field/src/Item/DecimalItem.php` | Leaf field item |
| `EmailItem` | `packages/field/src/Item/EmailItem.php` | Leaf field item |
| `EntityReferenceItem` | `packages/field/src/Item/EntityReferenceItem.php` | Leaf field item |
| `FileItem` | `packages/field/src/Item/FileItem.php` | Leaf field item |
| `FloatItem` | `packages/field/src/Item/FloatItem.php` | Leaf field item |
| `ImageItem` | `packages/field/src/Item/ImageItem.php` | Leaf field item |
| `IntegerItem` | `packages/field/src/Item/IntegerItem.php` | Leaf field item |
| `JsonItem` | `packages/field/src/Item/JsonItem.php` | Leaf field item |
| `LinkItem` | `packages/field/src/Item/LinkItem.php` | Leaf field item |
| `ListItem` | `packages/field/src/Item/ListItem.php` | Leaf field item |
| `StringItem` | `packages/field/src/Item/StringItem.php` | Leaf field item |
| `TextItem` | `packages/field/src/Item/TextItem.php` | Leaf field item |
| `AccessPolicy` | `packages/access/src/Attribute/AccessPolicy.php` | Attribute leaf |
| `EntityTypeAttribute` | `packages/entity/src/Attribute/EntityTypeAttribute.php` | Attribute leaf |
| `FieldType` | `packages/field/src/Attribute/FieldType.php` | Attribute leaf |

### Intentional Base Classes (keep non-final)
- `WaaseyaaPlugin` (`packages/plugin/src/Attribute/WaaseyaaPlugin.php`): Extended by 3 attribute classes
- `DefaultPluginManager` (`packages/plugin/src/DefaultPluginManager.php`): Extended by `FieldTypeManager`
- `GitHubClient` (`packages/github/src/GitHubClient.php`): Has `protected function request()` extension point
- `FieldItemList` (`packages/field/src/FieldItemList.php`): Has `protected` properties, intended as base

---

## Recommendations

### Priority 1: Extract interfaces for REMOVE_FINAL classes (22 classes)
The pattern is consistent: add an interface, keep `final` on the implementation, bind via the interface in service providers. This gives consumers the substitution seam without weakening the implementation boundary.

### Priority 2: Add `final` to non-final leaf classes (26 classes)
These are straightforward. No test changes needed since none of these are mocked.

### Priority 3: Resolve REVIEW_NEEDED (~23 classes)
Work through each by answering: "Does any consumer (app or test) need to substitute this class?" If yes, extract an interface. If no, the current `final` is correct.

### Architectural Note
The AI layer (packages `ai-agent`, `ai-pipeline`, `ai-schema`) has fewer interfaces than other layers. Given that AI is Layer 5 and likely to have diverse consumer needs, this is the layer most likely to benefit from additional seams. Consider addressing this as part of Track 2 (Bimaaji & agentic) milestone work.
