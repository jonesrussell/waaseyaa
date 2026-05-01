# waaseyaa/messaging

**Layer 2 — Content Types**

Direct messaging infrastructure for Waaseyaa: threads, messages, participants.

`MessageThread` is the conversation container; `ThreadParticipant` records membership and read state per account; `ThreadMessage` is the individual message. Access policies enforce that only participants can read or post, and unread counts are derived from per-participant `last_read_at` rather than a separate read-status table.

Key classes: `MessageThread`, `ThreadMessage`, `ThreadParticipant`, `MessagingServiceProvider`.
