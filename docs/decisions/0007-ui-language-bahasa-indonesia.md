---
id: 0007
title: UI copy in Bahasa Indonesia; code & docs in English
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: []
---

# 0007. UI copy in Bahasa Indonesia; code & docs in English

## Context

Users are Indonesian operators and their customers. The mockup is written in Bahasa Indonesia. The team and agents work in English.

## Decision

- All user-facing strings in Bahasa Indonesia, via Laravel translation files (`lang/id/…`) so wording can be edited in one place. Default locale `id`, timezone per organization (default `Asia/Jakarta`).
- Code identifiers, comments, commits, docs: English. Use the [glossary](../product/glossary.md) mapping.
- Formats: `Rp1.250.000`, `24 Okt 2026`, `23.59`.

## Consequences

- English UI can be added later as another locale without rework.
