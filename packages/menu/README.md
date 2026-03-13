# waaseyaa/menu

**Layer 2 — Content Types**

Menu and menu link entity types for Waaseyaa applications.

Defines the `menu` and `menu_link` entity types for site navigation. `MenuAccessPolicy` (auto-discovered via `#[PolicyAttribute]`) allows authenticated users to view menus and requires `administer menu` permission for create/edit operations.

Key classes: `Menu`, `MenuLink`, `MenuAccessPolicy`, `MenuServiceProvider`.
