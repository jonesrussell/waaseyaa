# waaseyaa/error-handler

**Layer 0 — Foundation**

Rich exception pages, solution hints, and editor links for Waaseyaa.

`DevExceptionRenderer` renders unhandled exceptions in development with stack frames, request context, and clickable file paths via `EditorLinkGenerator`. `SolutionProviderRegistry` looks up `SolutionInterface` providers by exception class — for example, `ClassNotFoundSolutionProvider` suggests likely `use` statements for unresolved class references.

Key classes: `DevExceptionRenderer`, `ExceptionRenderer`, `EditorLinkGenerator`, `SolutionProviderRegistry`, `SolutionInterface`.
