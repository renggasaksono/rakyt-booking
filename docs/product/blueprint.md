---
source: source/Rakyt_Booking_Product_Strategy_Blueprint_v1.1.docx
version: 1.1 (v1.0 blueprint + v1.1 commercial addendum)
date: 2026-10-15
status: reference
---

> Markdown conversion of the original Word blueprint so agents and humans can read and diff it in the repo.
> The .docx in `source/` is the original. If the strategy changes, update this file and record why in `docs/decisions/`.

**RAKYT**

Rakyt Booking

**Product Strategy, MVP Scope & Technical Blueprint**

*Version 1.0 \| 15 October 2026*

**A hosted, mobile-first booking platform for small Indonesian tour
operators and activity providers.**

Built around lessons from Liburdulu: reduce operational complexity,
avoid unnecessary payment friction, and deliver value without depending
on marketplace traffic.

# Executive Summary

Rakyt Booking should help small tour operators and activity providers
turn demand they already generate through WhatsApp, Instagram, TikTok,
referrals, and other channels into organized bookings. It is not a
marketplace and should not require sellers to learn a complex ecommerce
platform.

- Target market: small Indonesian tour operators, local guides, and
  activity providers with recurring bookings, date-based capacity, and
  manual administrative work.

- Core product: a shareable booking page, simple trip and departure
  management, a booking dashboard, and payment-status tracking.

- Payment model: seller receives payment directly; Rakyt manages the
  booking workflow and records payment status.

- Delivery: hosted SaaS first, with an architecture that can support
  extensions later.

- Recommended starting stack: Laravel + Inertia.js + Vue 3 + Tailwind
  CSS, deployed as a modular monolith.

- Commercial model: free entry tier plus paid subscriptions; avoid
  mandatory Rakyt transaction fees at launch.

## Strategic Thesis

Liburdulu demonstrated three important sources of friction: a
Magento-shaped dashboard was too complex for many small or less
technical operators; transaction and withdrawal costs reduced seller
margins; and the marketplace did not deliver enough traffic to justify
maintaining a store. Rakyt Booking should directly address all three.

# 1. Product Positioning

## Positioning statement

Your trips. Your customers. Booking made simple.

Rakyt Booking gives tour operators a simple way to publish trips, manage
departure dates and available seats, and receive organized bookings
through one shareable link—without the complexity of a marketplace or
traditional ecommerce software.

## Initial ideal customer profile

- Small tour operators, local guides, and activity providers in
  Indonesia.

- Already receive real inquiries and bookings through social media,
  WhatsApp, referrals, or repeat customers.

- Sell scheduled trips, limited-seat departures, time slots, group
  activities, or bookable packages.

- Regularly update prices, dates, or capacity.

- Currently coordinate bookings through chat history, spreadsheets,
  notebooks, or several disconnected tools.

- Have enough booking activity to feel administrative pain but lack the
  resources or appetite for complex software.

## Product principles

- Make routine work easier, not more configurable.

- A seller should be able to publish a first trip without a training
  session.

- Price and capacity changes should take seconds, especially on mobile.

- The product must be useful even if Rakyt sends no customer traffic.

- Seller-direct payments by default; do not create a pooled balance or
  payout system in the MVP.

- Design for mobile use and intermittent connectivity where practical.

# 2. Is Laravel the Right Stack for a Modern Mobile-First Frontend?

Yes. Laravel is a strong choice for the application backend, but Laravel
itself does not determine whether the frontend feels modern or
responsive. That experience comes from the frontend framework, component
system, design decisions, performance, and testing.

| **Approach**                                  | **Strengths**                                                                                                                                               | **Trade-offs**                                                                                              | **Fit for Rakyt Booking**                                                     |
|-----------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------|
| Laravel + Inertia.js + Vue 3 + Tailwind CSS   | Single application; shared routing/auth; modern reactive UI; fast development; no separate API required for most screens.                                   | Frontend and backend are coupled more tightly; separate mobile app/API may need deliberate expansion later. | Recommended starting point.                                                   |
| Laravel API + separate React/Next.js frontend | Strong frontend separation; good for complex client-side experiences and independent deployment; useful if public pages need a distinct rendering strategy. | More moving parts, duplicated auth/API concerns, separate deployment and testing overhead.                  | Use if independent frontend scaling or SEO/rendering requirements justify it. |
| Laravel + Blade + Alpine.js                   | Simple, server-rendered, low complexity; good for straightforward forms and content.                                                                        | Can become awkward for richer interactive dashboards and multi-step booking experiences.                    | Viable for a very small MVP, but less flexible for an app-like dashboard.     |
| Laravel API + mobile app                      | Supports native mobile clients and other clients through a shared API.                                                                                      | More work than a responsive web app; app-store distribution and maintenance.                                | Defer until actual users demonstrate the need.                                |

## Recommended frontend setup

- Laravel for authentication, business rules, data access, tenancy,
  booking capacity, payment records, notifications, and subscription
  entitlements.

- Inertia.js to connect Laravel routes/controllers to a modern frontend
  without needing to build and maintain a separate API for every screen.

- Vue 3 for reusable components and interactive flows; React is also
  reasonable if it materially improves developer speed or team fit.

- Tailwind CSS for responsive layout and consistent design tokens.

- A mobile-first component system with accessible forms, large tap
  targets, clear validation, loading/error states, and low-friction
  editing.

## Important nuance

A responsive website is not automatically a good mobile experience. The
seller dashboard should be designed around the most common tasks—today’s
bookings, pending payments, departures, and quick edits—not simply
shrink a desktop dashboard to fit a phone.

For public booking pages, choose rendering and caching strategies based
on actual SEO and performance needs. Inertia is a strong default for the
authenticated application; if public landing pages later require more
advanced server rendering or independent delivery, that can be addressed
without prematurely splitting the whole system.

# 3. MVP Scope

## P0 — Required for initial launch

- Seller account, organization profile, public URL, contact details, and
  basic branding.

- Mobile-friendly public booking page with trip descriptions, photos,
  prices, and booking CTA.

- Trip creation with sensible defaults and minimal required fields.

- Scheduled departures with date, capacity, price override, booking
  deadline, and open/closed state.

- Request-a-date mode for operators who confirm availability manually.

- Booking form: customer name, phone, participant count, and optional
  notes.

- Booking reference and simple statuses: pending, confirmed, cancelled,
  completed.

- Payment status tracking, seller payment instructions, and optional
  evidence upload.

- Booking list with basic search and filters.

- WhatsApp contact and prefilled messages.

- Tenant isolation, server-side authorization, backups, logs, and
  account recovery.

- Concurrency-safe capacity handling to prevent overbooking.

## P1 — Add after pilot feedback

- Recurring departure templates and CSV exports.

- Automated notifications and reminders through compliant integrations.

- Deposit and remaining-balance tracking.

- Promo codes, add-ons, and package options.

- Staff accounts and basic permissions.

- Seller-owned payment-provider integrations where approved and
  practical.

- Custom domains and removal of Rakyt branding on paid plans.

## P2 — Explicitly defer

- Public plugin/extension marketplace.

- Marketplace discovery and customer acquisition promises.

- Complex reseller, agent, or affiliate hierarchies.

- Dynamic pricing engines and advanced margin rules.

- Full accounting, ERP, CRM, or inventory management.

- Native mobile apps, AI features, and multi-provider payment
  orchestration.

## Core domain model

A trip is a reusable offering; a departure is a specific bookable
occurrence. For example, “Karimunjawa 3D2N Open Trip” can have
departures on different dates, each with its own price and seat
capacity. This separation avoids forcing operators into generic
ecommerce product configuration.

| **Entity**           | **Purpose**                                                    |
|----------------------|----------------------------------------------------------------|
| users                | Authentication and account identity.                           |
| organizations        | Seller business profile and tenant boundary.                   |
| organization_members | Seller staff and roles; can be minimal in MVP.                 |
| trips                | Reusable description, media, base price, booking mode.         |
| trip_departures      | Date/time, capacity, price override, deadline, status.         |
| bookings             | Customer, participant count, booking reference, status.        |
| payment_records      | Payment instructions, amount due/paid, evidence, verification. |
| notifications        | Message status and notification history.                       |
| subscriptions        | Plan, status, entitlements, billing identifiers.               |
| audit_events         | Important changes to bookings, prices, and capacity.           |

# 4. Technical Architecture

## Recommended architecture: Laravel modular monolith

Start with one deployable Laravel application and clear internal
modules. Avoid microservices, Kubernetes, and event-driven
infrastructure until real product needs justify the operational cost.

| **Layer**       | **Recommendation**                                                                                                                   |
|-----------------|--------------------------------------------------------------------------------------------------------------------------------------|
| Frontend        | Inertia.js + Vue 3 + Tailwind CSS; responsive public booking pages and seller dashboard.                                             |
| Application     | Laravel modular monolith: authentication, organizations, trips, departures, bookings, payment records, notifications, subscriptions. |
| Database        | PostgreSQL or MySQL; both are adequate for the MVP.                                                                                  |
| Queue/scheduler | Laravel queue and scheduler; begin simply, add Redis if justified by workload.                                                       |
| Media           | S3-compatible object storage for photos and other media.                                                                             |
| Deployment      | Managed app hosting or a straightforward VPS/container deployment.                                                                   |
| Operations      | Automated backups, error reporting, structured logs, uptime checks, and documented restore procedure.                                |

## Multi-tenant security

- Every seller-owned record must be scoped to the correct organization.

- Authorization must be enforced server-side, not only by hiding UI
  controls.

- Add tests that attempt cross-organization access to trips, bookings,
  media, and payment records.

- Keep secrets out of source control and rotate credentials if exposed.

- Treat any cross-seller data exposure as a launch-blocking issue.

## Capacity and booking reliability

Prevent overbooking with a database transaction and an appropriate row
lock or atomic capacity update. If unpaid bookings temporarily hold
seats, make the expiry policy explicit and release expired holds
automatically. Do not hold capacity indefinitely because a customer
opened a page.

# 5. Payments: Seller-Direct by Default

Rakyt manages the booking workflow while the seller receives money
directly. This avoids building a pooled balance, payout workflow, or
platform-controlled split-payment system into the first release.

## Phase 1 — Payment instructions and records

- Seller configures bank-transfer instructions or a payment link from
  their own provider account.

- Rakyt creates a booking and records the amount due and payment status.

- Seller verifies payment manually and confirms the booking.

- Cash or pay-on-arrival can be supported where appropriate.

- Rakyt should not hold customer funds in this phase.

## Phase 2 — Optional integrations

Add direct-to-seller payment-provider integrations only after validating
demand and confirming the provider's supported account, settlement,
onboarding, and compliance model. Do not assume a platform-level account
can automatically route funds to many independent sellers.

The product promise should be “no mandatory Rakyt transaction fee,” not
“free payment processing.” Provider fees vary by payment method and
merchant arrangement and should be verified against current agreements
before launch.

# 6. Monetization

Start with freemium plus subscription. Avoid a mandatory Rakyt
commission on every booking at launch, since transaction charges were a
source of seller hesitation in the Liburdulu experience.

| **Plan** | **Illustrative price** | **Potential scope**                                                                                  |
|----------|------------------------|------------------------------------------------------------------------------------------------------|
| Free     | Rp0                    | One public page, basic trips/departures, basic booking list, manual payment records, Rakyt branding. |
| Pro      | Rp79k–149k/month       | More active trips/departures, notifications, deposit tracking, exports, reports, custom branding.    |
| Business | Rp199k–299k/month      | Staff accounts, roles, advanced reports, automation, integrations, priority support.                 |

These are hypotheses for testing, not validated willingness-to-pay
figures. Seasonal income and low margins may make subscriptions
difficult for some operators. Track activation, repeat usage, support
costs, and paid conversion—not just sign-ups.

## Illustrative monthly revenue scenarios

| **Paying customers** | **Average monthly plan** | **Gross monthly subscription revenue** |
|----------------------|--------------------------|----------------------------------------|
| 25                   | Rp99,000                 | Rp2,475,000                            |
| 50                   | Rp99,000                 | Rp4,950,000                            |
| 100                  | Rp99,000                 | Rp9,900,000                            |
| 100                  | Rp149,000                | Rp14,900,000                           |

These simple scenarios exclude taxes, hosting, support, acquisition, and
payment costs. A low-priced SaaS can become unprofitable if each seller
needs substantial manual onboarding or ongoing support.

# 7. Go-to-Market

The seller’s existing audience is the initial distribution channel.
Rakyt should not promise marketplace traffic.

- Start with a small number of operators you can onboard personally,
  including contacts from prior travel-industry experience.

- Observe sellers creating and updating trips without coaching; record
  where they hesitate.

- Help each pilot seller publish the booking link and use it with real
  customers.

- Use direct outreach, referrals, useful free resources, and later
  content/SEO to reach similar operators.

- Do not spend materially on paid acquisition until activation and
  retention are understood.

## Growth loop

Seller creates trip → shares booking link → customer books → seller
manages booking more easily → seller keeps using Rakyt and recommends it
to peers.

# 8. Rollout Plan

| **Period** | **Focus**               | **Deliverable**                                                                                                       |
|------------|-------------------------|-----------------------------------------------------------------------------------------------------------------------|
| Week 1     | Workflow and prototypes | Define seller onboarding, trip/departure, booking, payment, and status flows; test screens with target users.         |
| Weeks 2–3  | Core product            | Authentication, tenant isolation, trips/departures, public pages, booking creation, capacity controls, dashboard.     |
| Week 4     | Operational workflow    | Payment records, seller instructions, confirmations, cancellation handling, admin/support view.                       |
| Week 5     | Pilot                   | Onboard a small group; observe real usage without adding speculative features.                                        |
| Week 6     | Hardening and decision  | Security checks, backup restore test, mobile usability, booking reliability, support burden, continue/pivot decision. |

Six weeks is a planning target for a narrow working MVP, not a
guarantee. It depends on available part-time hours, implementation
choices, and how much existing code can be reused.

# 9. Success Metrics

| **Metric**                   | **Initial target or signal**                                        |
|------------------------------|---------------------------------------------------------------------|
| Time to first published trip | Under 10 minutes as a design target.                                |
| Independent onboarding       | Most pilot sellers complete setup without live assistance.          |
| Routine edit speed           | A simple price/capacity update takes under 30 seconds.              |
| First real booking           | Active pilot sellers receive one through the system within 14 days. |
| Repeat use                   | Sellers return to manage another departure or booking.              |
| Booking reliability          | No known overbooking or cross-seller data exposure.                 |
| Willingness to pay           | Some active sellers commit to a paid plan.                          |
| Support burden               | Support needs decline as the product and onboarding improve.        |

These are initial targets for learning, not industry benchmarks. The
most important qualitative test is whether a less technical operator can
use the product independently.

# 10. Key Risks and Mitigations

| **Risk**                                   | **Mitigation**                                                                                      |
|--------------------------------------------|-----------------------------------------------------------------------------------------------------|
| Free users never convert                   | Measure repeat usage and willingness to pay; put paid value around proven recurring needs.          |
| Manual support becomes expensive           | Instrument onboarding friction, simplify defaults, and track support time per seller.               |
| Product becomes a generic link-in-bio tool | Keep the initial differentiation around departures, capacity, booking status, and payment records.  |
| Payment flow remains confusing             | Make responsibilities and status clear; automate only where it reduces actual friction.             |
| Overbooking or data leakage                | Use transactional capacity control, tenant-scoped authorization, and security tests.                |
| Scope expands into ERP/marketplace         | Keep P2 features out until real pilot evidence supports them.                                       |
| Seasonality hurts subscription retention   | Test monthly and annual options later; consider seasonal pause options only if customers need them. |

# Final Recommendation

Build a hosted, mobile-first booking tool for small Indonesian tour and
activity operators, with seller-direct payments, an opinionated
workflow, and a free tier that delivers real value. Laravel +
Inertia.js + Vue 3 + Tailwind CSS is a practical starting stack because
it supports a modern responsive interface while keeping the initial
system simple to build and operate.

Do not build a marketplace, a Magento replacement, or a public plugin
marketplace in the first release. Keep the first version narrow,
minimize infrastructure and acquisition spending, and require evidence
of real bookings and repeat usage before expanding scope.

The longer-term opportunity is a broader extensible booking platform.
Earn that expansion by first proving that a small operator can publish a
trip, update availability, and manage real bookings with less effort
than their current workflow.

**RAKYT BOOKING**

**Commercial Strategy & 12-Month Revenue Forecast**

*Version 1.1 Addendum \| 15 October 2026*

Purpose: extend the initial product blueprint with a practical revenue
model, an initial-year forecast, and a path from freemium SaaS to
higher-value enterprise and booking-infrastructure contracts. This
addendum supplements—not replaces—the original MVP and technical
recommendations.

# 11. Commercial Strategy: Three Revenue Engines

Rakyt Booking should not rely on low-priced subscriptions alone. The
commercial model has three complementary engines: (1) freemium SaaS for
distribution and product validation, (2) Business and Enterprise plans
for higher contract value, and (3) booking infrastructure for
organizations that manage multiple operators, brands, agents, or
activity providers.

## 11.1 Freemium SaaS — Distribution Engine

The free tier lowers adoption friction and helps operators publish trips
and manage real bookings. Its purpose is to acquire relevant users,
establish repeat usage, and identify the workflows customers will pay to
improve. Free registrations alone are not a success metric; activation,
real booking activity, repeat use, conversion, and support burden matter
more.

Indicative pricing hypothesis: Free Rp0; Pro Rp79,000–149,000/month;
Business Rp199,000–299,000/month. These prices remain unvalidated
hypotheses and should be tested with actual operators.

## 11.2 Enterprise Booking Engine — Revenue Engine

Offer a standardized hosted booking engine to established tour
operators, DMCs, and travel groups that already have demand but need
more reliable booking operations. The platform remains shared; customers
pay for configuration and reusable capabilities rather than a separate
custom-built application.

Potential enterprise capabilities (only build when validated):

- Custom domain and white-label branding.

- Multiple staff accounts, roles, and permissions.

- Multi-brand or multi-branch management.

- Reporting across teams, brands, or branches.

- API access and selected third-party integrations.

- Agent/reseller workflows where multiple prospects validate the need.

- Priority support and a clearly defined service level.

Initial pricing hypotheses for validation: implementation fee Rp10–30
million, plus platform subscription Rp1.5–5 million/month depending on
scope, integrations, operational criticality, and support. These are
test ranges, not validated market prices. Avoid accepting bespoke work
that cannot be reused or priced to cover delivery and support.

## 11.3 Booking Infrastructure — Scale Engine

Longer term, position Rakyt Booking as infrastructure for companies that
coordinate multiple operators, agents, or activity providers. Potential
buyers include destination management companies, travel groups, agent
networks, and platforms that need a common booking layer without
requiring Rakyt to build a consumer marketplace or generate end-customer
traffic.

Potential commercial components to validate:

- Platform license: illustrative range Rp5–15 million/month.

- Initial implementation: illustrative range Rp25–100 million.

- API access or usage-based pricing, if supported by real customer
  demand.

- Paid integration work only when scope, margin, and reuse potential are
  clear.

These ranges are exploratory pricing hypotheses, not market benchmarks.
Do not build the full infrastructure layer before a qualified buyer
validates the problem and commercial model.

# 12. First-Year Revenue Forecast

The forecast below is a planning model, not a promise or a validated
market estimate. It assumes a new product with limited initial
distribution, direct outreach and referrals, gradual paid conversion,
and no dependence on marketplace traffic. Figures represent gross
revenue before hosting, acquisition, support, taxes, and other operating
costs.

## 12.1 Scenario assumptions

| Assumption                          | Conservative | Base case     | Upside        |
|-------------------------------------|--------------|---------------|---------------|
| Paid SaaS customers at month 12     | 18           | 45            | 75            |
| Average SaaS revenue/customer/month | Rp120,000    | Rp150,000     | Rp175,000     |
| Enterprise deals in year 1          | 0            | 1             | 2             |
| Implementation fee per deal         | —            | Rp10 million  | Rp15 million  |
| Enterprise subscription/month       | —            | Rp1.5 million | Rp2.5 million |
| Enterprise subscription starts      | —            | Month 9       | Month 8       |

## 12.2 Illustrative revenue outcomes

| Revenue metric                                | Conservative   | Base case      | Upside          |
|-----------------------------------------------|----------------|----------------|-----------------|
| SaaS subscription revenue during year 1       | Rp10.9 million | Rp30.9 million | Rp56.9 million  |
| Implementation fees                           | Rp0            | Rp10 million   | Rp30 million    |
| Enterprise subscription revenue during year 1 | Rp0            | Rp6 million    | Rp25 million    |
| Total year-1 revenue                          | Rp10.9 million | Rp46.9 million | Rp111.9 million |
| SaaS MRR at month 12                          | Rp2.16 million | Rp6.75 million | Rp13.13 million |
| Enterprise MRR at month 12                    | Rp0            | Rp1.5 million  | Rp5 million     |
| Total MRR at month 12                         | Rp2.16 million | Rp8.25 million | Rp18.13 million |

Important: MRR is the recurring monthly run rate at the end of the
period; it is not the cash or revenue earned during the full year. ARR
is an annualized run rate and should not be presented as first-year
revenue. The scenarios are directional and must be recalculated when
acquisition, churn, conversion, and contract timing data become
available.

# 13. Revenue Architecture and the Rp1 Billion ARR Direction

The strategic goal is not to force hundreds of small customers to carry
the entire business. Retain freemium as a scalable acquisition channel
while adding higher-value Business, Enterprise, and infrastructure
contracts. The following is an illustrative recurring-revenue mix, not a
forecast:

| Segment     | Customers                     | Average price/month | Annual recurring revenue |
|-------------|-------------------------------|---------------------|--------------------------|
| Pro         | 300                           | Rp99,000            | Rp356.4 million          |
| Business    | 100                           | Rp249,000           | Rp298.8 million          |
| Enterprise  | 5                             | Rp5 million         | Rp300 million            |
| Partner/API | 1                             | Rp4 million         | Rp48 million             |
| Total       | 406 SaaS + 6 larger contracts | —                   | Rp1.0032 billion ARR     |

The Rp1 billion ARR mix is a long-term model-design target, not a
first-year objective. It assumes recurring subscriptions remain active
and excludes implementation fees. The actual segment mix and prices
should follow evidence from customers, not be treated as commitments.

# 14. Enterprise Go-to-Market

Enter through a relatively focused Enterprise Booking Engine offer,
while designing the product so it can evolve into multi-operator booking
infrastructure later. This reduces the risk of building a broad platform
before a paying buyer validates the need.

## 14.1 Initial ideal enterprise customer

- Established tour operator with multiple departures and recurring
  bookings.

- Operator with several operational or sales staff.

- DMC or inbound-tour business with many packages or partners.

- Travel group operating multiple brands or branches.

## 14.2 Sales and validation milestones

| Period       | Focus                                                       | Evidence sought                               |
|--------------|-------------------------------------------------------------|-----------------------------------------------|
| Months 1–2   | MVP and pilot; interview potential enterprise buyers        | Repeated pain points and workflow fit         |
| Months 3–4   | Launch freemium; identify 20 qualified enterprise prospects | Active use and credible sales pipeline        |
| Months 5–6   | Offer a paid enterprise pilot                               | Willingness to pay and implementation scope   |
| Months 7–9   | Close first enterprise contract                             | Repeatable onboarding and recurring revenue   |
| Months 10–12 | Retain SaaS users and develop next enterprise opportunities | Retention, references, and qualified pipeline |

## 14.3 Guardrails against becoming a custom software agency

- Keep core trip, departure, capacity, booking, payment-status, and user
  workflows standardized.

- Prioritize enterprise features that can be reused across multiple
  customers.

- Scope, price, and contract custom integrations separately.

- Do not commit to bespoke work without accounting for implementation,
  maintenance, and support costs.

- Require evidence from qualified buyers before adding multi-operator,
  reseller, or advanced API capabilities.

# 15. Key Metrics and Decision Gates

- Activation: percentage of new sellers who publish a trip and booking
  page.

- Booking activity: percentage of active sellers receiving real bookings
  through Rakyt.

- Free-to-paid conversion and paid-customer retention.

- Customer acquisition cost and support time/cost per seller.

- Enterprise pipeline by stage: interviewed, qualified, proposal, paid
  pilot, negotiation, signed.

- Sales-cycle length and delivery margin for each enterprise contract.

# 16. Strategic Recommendation

Keep the original MVP narrow: hosted booking pages, trip/departure
management, booking records, capacity controls, and payment-status
tracking. Run freemium and enterprise discovery in parallel. Use
freemium as the distribution and validation engine; use standardized
Business/Enterprise plans as the early revenue engine; and pursue
multi-operator booking infrastructure only after a paying customer
validates the need.

The preferred path is to enter through Enterprise Booking Engine
contracts and architect toward booking infrastructure, without building
a marketplace or a large multi-provider platform prematurely. The
first-year base case is approximately Rp46.9 million gross revenue with
one enterprise deal; the upside case is approximately Rp111.9 million.
Treat these as planning scenarios and update them using actual funnel,
retention, sales-cycle, and support-cost data.

*END OF VERSION 1.1 COMMERCIAL STRATEGY ADDENDUM*
