# waaseyaa/queue

**Layer 0 — Foundation**

Async job queue for Waaseyaa applications.

Provides a `JobInterface`, `JobMiddlewareInterface`, and queue backend abstraction for dispatching and processing background jobs. Uses Symfony Messenger conventions. Workers consume jobs outside the HTTP request lifecycle.

Key classes: `JobInterface`, `JobMiddlewareInterface`, `QueueInterface`.
