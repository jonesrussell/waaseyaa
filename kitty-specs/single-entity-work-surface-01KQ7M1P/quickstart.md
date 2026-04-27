# Quickstart: Single-Entity Work Surface

**Mission**: `single-entity-work-surface-01KQ7M1P`

This walkthrough shows a downstream-app developer (the "consumer") wiring all six primitives end-to-end. It doubles as the script for the cross-cutting integration test (Success Criterion 5).

The scenario: a fictional consumer app, "Vignette", has a `node` entity type with a `profile` bundle. Vignette wants a deep-link editing workspace at `/edit/node/{id}`, with auto-save, attachments, and structured import.

## Step 1 — Declare a bundle template (F2)

```php
// app/src/Templates/ProfileTemplate.php
namespace Vignette\Templates;

use Waaseyaa\Field\Attribute\BundleTemplate;
use Waaseyaa\Field\Attribute\FieldTemplate;

#[BundleTemplate(entityType: 'node', bundle: 'profile')]
final class ProfileTemplate
{
    #[FieldTemplate(
        key: 'name',
        type: 'string',
        label: 'Display Name',
        promptAliases: ['name', 'display name', 'full name'],
        required: true,
    )]
    public string $name;

    #[FieldTemplate(
        key: 'bio',
        type: 'string_long',
        label: 'Biography',
        group: 'about',
        promptAliases: ['bio', 'biography', 'about you'],
    )]
    public string $bio;

    #[FieldTemplate(
        key: 'birthplace',
        type: 'string',
        label: 'Place of Birth',
        group: 'about',
        promptAliases: ['birthplace', 'place of birth', 'born in'],
    )]
    public string $birthplace;

    #[FieldTemplate(
        key: 'website',
        type: 'string',
        label: 'Website',
        group: 'links',
        promptAliases: ['website', 'homepage', 'url'],
    )]
    public string $website;

    #[FieldTemplate(
        key: 'is_published',
        type: 'boolean',
        label: 'Published',
    )]
    public bool $isPublished;
}
```

After app boot, `BundleTemplateCompiler` discovers this class and registers all five fields against `(node, profile)` in the `FieldDefinitionRegistry`. No imperative registration calls required.

## Step 2 — Wire the deep-link route (F1)

```php
// app/src/ServiceProvider.php
namespace Vignette;

use Waaseyaa\Foundation\ServiceProvider;
use Waaseyaa\Routing\EntityDeepLinkRouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class VignetteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var WaaseyaaRouter $router */
        $router = $this->resolve(WaaseyaaRouter::class);

        $route = EntityDeepLinkRouteBuilder::for('/edit', 'node')
            ->controller(EditWorkspaceController::class . '::view')
            ->build();

        $router->addRoute('vignette.edit_node', $route);
    }
}
```

```php
// app/src/Controller/EditWorkspaceController.php
namespace Vignette\Controller;

use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Field\Form\FormDescriptorBuilder;
use Waaseyaa\Access\AccountInterface;

final class EditWorkspaceController
{
    public function __construct(
        private readonly FormDescriptorBuilder $formBuilder,
    ) {}

    public function view(EntityInterface $node, AccountInterface $account): Response
    {
        $descriptors = $this->formBuilder->build($node, $node->bundle(), $account);

        // Hand off to consumer's template layer (Twig / Vue / whatever).
        // F6 emits structured arrays; the consumer renders.
        return $this->renderTemplate('edit-workspace.html.twig', [
            'entity' => $node,
            'fields' => $descriptors,
        ]);
    }
}
```

The `RouteBuilder` returned by `EntityDeepLinkRouteBuilder::for(...)->controller(...)` is fully chainable. The consumer can add `methods()`, `requirePermission()`, etc. before `build()`.

## Step 3 — Auto-save fires automatically (F3)

The auto-save route is registered by `JsonApiRouteProvider` for every entity type. The consumer does **not** register this route per-entity-type.

Client-side (consumer's responsibility — out of scope for this mission, but illustrative):

```javascript
// Debounced auto-save on field blur or input (consumer choice).
async function autoSave(entityType, id, key, value) {
  const response = await fetch(`/api/${entityType}/${id}/field/${key}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ value }),
    credentials: 'include',  // session cookie
  });
  if (!response.ok) {
    handleError(response.status, await response.json());
  }
}
```

Server-side: `FieldAutoSaveController::update` runs entity-policy + field-policy access checks, persists via `EntityRepository`, returns 200 with the persisted value. No per-field controller code in the consumer app.

## Step 4 — Attach files and select an active one (F4)

```php
// app/src/Controller/AttachmentController.php
namespace Vignette\Controller;

use Waaseyaa\Attachment\Attachment;
use Waaseyaa\Attachment\AttachmentRepository;

final class AttachmentController
{
    public function __construct(
        private readonly AttachmentRepository $attachments,
    ) {}

    public function upload(string $nodeId, UploadedFile $file): Response
    {
        $attachment = new Attachment([
            'parent_entity_type' => 'node',
            'parent_entity_id' => $nodeId,
            'filename' => $file->getClientOriginalName(),
            'content_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'storage_uri' => $this->saveBytesSomewhere($file),  // consumer's storage layer
            'is_active' => false,
        ]);
        $this->attachments->save($attachment);
        return new JsonResponse(['id' => $attachment->id()]);
    }

    public function setActive(string $attachmentId): Response
    {
        $this->attachments->setActive($attachmentId);
        return new JsonResponse(['ok' => true]);
    }

    public function listForNode(string $nodeId): Response
    {
        $list = $this->attachments->listFor('node', $nodeId);
        $active = $this->attachments->getActive('node', $nodeId);
        return new JsonResponse([
            'attachments' => array_map(fn($a) => $a->toArray(), $list),
            'active_id' => $active?->id(),
        ]);
    }
}
```

Access decisions are inherited from the parent `node` policy automatically — `ParentDelegatedAccessPolicy` is registered with `#[PolicyAttribute(entityType: 'attachment')]` and discovered at boot. Consumer does not write any access policy code for attachments.

`setActive` is atomic: under concurrent calls against the same parent, exactly one attachment ends up active.

## Step 5 — Import a markdown table (F5)

```php
// app/src/Controller/ImportController.php
namespace Vignette\Controller;

use Waaseyaa\StructuredImport\StructuredImporterInterface;

final class ImportController
{
    public function __construct(
        private readonly StructuredImporterInterface $importer,   // bound to GfmTableImporter
    ) {}

    public function preview(string $nodeId, string $payload): Response
    {
        $result = $this->importer->import($payload, 'node', 'profile');
        return new JsonResponse([
            'matched' => $result->matched,
            'unmatched' => array_map(fn($u) => ['prompt' => $u->prompt, 'value' => $u->value], $result->unmatched),
            'errors' => $result->errors,
        ]);
    }
}
```

User uploads:

```markdown
| Field | Value |
| --- | --- |
| Display Name | Aanikoobijigan |
| Biography | Storyteller, knowledge keeper. |
| Born In | Naotkamegwanning |
| Website | https://example.test |
| Status | Active |
```

Result against `(node, profile)`:

```php
ImportResult(
    matched: [
        'name' => 'Aanikoobijigan',           // alias 'display name' matched
        'bio' => 'Storyteller, knowledge keeper.',  // alias 'biography' matched
        'birthplace' => 'Naotkamegwanning',   // alias 'born in' matched
        'website' => 'https://example.test',  // alias 'website' matched
    ],
    unmatched: [
        UnmatchedRow(prompt: 'Status', value: 'Active'),  // no alias 'status' on this bundle
    ],
    errors: [],
)
```

The consumer decides whether to apply `matched` to the entity (typically via the auto-save endpoint per field, or via a single `EntityRepository::save` call), and how to present `unmatched` to the user (e.g., let them map manually, or discard).

## Step 6 — Render the form (F6)

The consumer calls `FormDescriptorBuilder::build($node, 'profile', $account)` and gets:

```php
[
    FormFieldDescriptor(name: 'name', type: 'string', label: 'Display Name', group: '', value: '...', readOnly: false, required: true, errors: []),
    FormFieldDescriptor(name: 'bio', type: 'string_long', label: 'Biography', group: 'about', value: '...', readOnly: false, required: false, errors: []),
    FormFieldDescriptor(name: 'birthplace', type: 'string', label: 'Place of Birth', group: 'about', value: '...', readOnly: false, required: false, errors: []),
    FormFieldDescriptor(name: 'website', type: 'string', label: 'Website', group: 'links', value: '...', readOnly: false, required: false, errors: []),
    FormFieldDescriptor(name: 'is_published', type: 'boolean', label: 'Published', group: '', value: false, readOnly: true, required: false, errors: []),
    // ^ readOnly because anonymous user's FieldAccessPolicyInterface returned Forbidden for 'update' on is_published
]
```

The consumer's template layer (Twig, Vue, whatever) walks this list and emits HTML. F6 has no opinion on rendering.

Typical Twig rendering pattern (consumer-owned):

```twig
{% for group, fields in fields|group_by('group') %}
  {% if group %}<fieldset><legend>{{ group|capitalize }}</legend>{% endif %}
  {% for field in fields %}
    <label for="f-{{ field.name }}">{{ field.label }}{% if field.required %} *{% endif %}</label>
    <input type="text" id="f-{{ field.name }}" name="{{ field.name }}"
           value="{{ field.value }}" {% if field.readOnly %}disabled{% endif %}
           data-autosave-url="/api/node/{{ entity.id }}/field/{{ field.name }}">
  {% endfor %}
  {% if group %}</fieldset>{% endif %}
{% endfor %}
```

Adding a new field on the bundle is one attribute on `ProfileTemplate.php` — no template edits required. The next page load picks up the new field automatically (boot-time registry recompile, then `FormDescriptorBuilder` walks the updated registry).

## End-to-end integration test (Success Criterion 5)

The integration test in `tests/Integration/Phase##/SingleEntityWorkSurfaceTest.php` mirrors this quickstart against a fresh `DBALDatabase::createSqlite()` instance:

1. Declare `ProfileTemplate` (above).
2. Boot the kernel; assert `FieldDefinitionRegistry::bundleFieldsFor('node', 'profile')` returns 5 fields with correct labels, groups, aliases.
3. Register the deep-link route via `EntityDeepLinkRouteBuilder`.
4. Make a `GET /edit/node/<id>` request; assert 200 + controller invoked with hydrated entity.
5. PUT 5 auto-save requests; assert each persists via `EntityRepository::find()` re-read.
6. Create 3 attachments via `AttachmentRepository::save()`; call `setActive()` on the second; assert `getActive()` returns it; assert `listFor()` returns all 3 in order.
7. Import the markdown table above via `GfmTableImporter::import()`; assert `matched` count = 4, `unmatched` count = 1.
8. Build descriptors via `FormDescriptorBuilder::build()`; assert order, values, group structure, and that one field is read-only due to field-access policy.
9. Run `bin/check-package-layers` and `bin/check-composer-policy`; assert both exit 0.

When this test passes, the mission ships.

## What the consumer does NOT have to write

- ❌ A custom controller for each editable field (F3 handles all fields generically).
- ❌ A custom access policy for attachments (F4's `ParentDelegatedAccessPolicy` is registered automatically).
- ❌ A markdown parser (F5's `GfmTableImporter` ships in `waaseyaa/structured-import`).
- ❌ Imperative `FieldDefinitionRegistry::registerBundleFields()` calls (F2's compiler discovers attribute-decorated classes).
- ❌ HTML-rendering plumbing inside `waaseyaa/field` (F6 emits descriptors only; consumer's template layer renders).

Total consumer code for a fully wired workspace per entity bundle: one `BundleTemplate` class (~40 lines for 5 fields) + one route registration (~5 lines) + one controller (~15 lines for the GET endpoint). Auto-save and attachment endpoints are zero-config.
