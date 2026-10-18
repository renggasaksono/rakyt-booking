# Rakyt Booking

> Your trips. Your customers. Booking made simple.

A hosted, mobile-first booking platform for small Indonesian tour operators and activity providers.
One shareable booking link, simple trip and departure management, a booking dashboard, and payment-status
tracking — with customers paying the seller directly.

## Status

MVP build. The Laravel app is scaffolded and the tenancy & security baseline (F-009 slice A) is in place.
Setup and commands: [`AGENTS.md`](AGENTS.md#commands).
See the [roadmap](docs/roadmaps/README.md) for what is next.

## Where things live

| Path | What |
|---|---|
| [`AGENTS.md`](AGENTS.md) | Rules for AI agents (and humans) working in this repo |
| [`docs/product/`](docs/product/) | Blueprint (strategy, MVP scope, architecture) and glossary |
| [`docs/design/`](docs/design/README.md) | Design system notes and the hi-fi mockup |
| [`docs/features/`](docs/features/README.md) | One spec per feature, with status and acceptance criteria |
| [`docs/roadmaps/`](docs/roadmaps/README.md) | Now / next / later, and dated plans |
| [`docs/decisions/`](docs/decisions/README.md) | Architecture & product decision records (ADRs) |
| [`docs/agents/`](docs/agents/README.md) | How agents work here: workflow, roles, prompts |

## Stack

Laravel · Inertia.js · Vue 3 · Tailwind CSS, as a modular monolith. Local dev on Laravel Herd.
Rationale: [ADR-0002](docs/decisions/0002-stack-laravel-inertia-vue-tailwind.md).
