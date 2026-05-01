# waaseyaa/billing

**Layer 3 — Services**

Stripe billing for Waaseyaa: subscriptions, checkout, customer portal, plan tiers.

`BillingManager` is the single entry point for plan tier resolution, checkout session creation, and customer-portal URLs. `StripeClientInterface` plus `FakeStripeClient` make the integration testable without hitting Stripe; `WebhookHandler` dispatches Stripe webhook events into framework `DomainEvent`s.

Key classes: `BillingManager`, `BillingServiceProvider`, `CheckoutSession`, `PlanTier`, `StripeClientInterface`, `WebhookHandler`.
