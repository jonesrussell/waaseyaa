# waaseyaa/scheduler

**Layer 0 — Foundation**

Task scheduling with cron expression support for Waaseyaa.

`Schedule` is a registry of `ScheduledTask`s configured via the `ScheduleBuilder` fluent API. `ScheduleRunner` dispatches due tasks from `bin/waaseyaa schedule:run` (one cron tick) using `Lock/`-backed mutual exclusion so the same task does not run twice across overlapping ticks or multi-host deployments. Each invocation produces a `ScheduleRunResult` summarizing dispatched, skipped (locked), and failed task IDs.

Key classes: `Schedule`, `ScheduleInterface`, `ScheduleBuilder`, `ScheduleRunner`, `ScheduleRunResult`, `ScheduledTask`.
