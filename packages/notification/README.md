# waaseyaa/notification

**Layer 3 — Services**

Multi-channel notification system for Waaseyaa.

`Notification`s are dispatched to `Notifiable` recipients through one or more `ChannelInterface` implementations (mail, in-app, web push). Channel routing per recipient is resolved by `DefaultNotifiable` from the account's preferences, with per-channel queueing via the framework `Job` system so user-facing requests never wait on email or push delivery.

Key classes: `ChannelInterface`, `DefaultNotifiable`, `NotificationServiceProvider`.
