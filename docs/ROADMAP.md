# Delivery roadmap

## Stage 0 — architecture and contracts

- accepted architecture record;
- page identity and localized URL rules;
- initial YAML contracts;
- security and trust boundaries;
- dependency decisions.

## Stage 1 — application foundation

- PSR-4 Composer project;
- environment bootstrap and small dependency container;
- HTTP request, response and kernel;
- exact and parameterized routing;
- separate site, API and admin route spaces;
- central production-safe exception rendering;
- health endpoint and regression tests;
- Apache and Nginx routing examples.

## Stage 2 — safe filesystem and YAML (complete)

- safe root-bound path resolver;
- slug and page-identity value objects;
- restrictive YAML parser;
- locks, atomic writes and revision conflicts;
- parsed-file cache.

## Stage 3 — pages, localization and public API (complete)

- page/config/navigation repositories;
- localized route index;
- SEO resolver;
- public page/navigation/config endpoints;
- ETag and Last-Modified.

## Stage 4 — block schema and validation (complete)

- block discovery and registry;
- field-type registry;
- normalization, localization and validation.

## Stage 5 — server-side rendering (complete)

- block, page and layout renderers;
- safe render context and Markdown;
- partial registry;
- asset collection, publication and fingerprinting.

## Stage 6 — collections (complete)

- `pagination.yml` repository and validation;
- collection-aware localized route index;
- direct-child source, sorting, equality filters and pagination;
- REST API and server-side collection layouts.

## Stage 7 — users and authentication (complete)

- SQLite schema limited to users and authentication mechanisms;
- password policy and Argon2id-aware hashing;
- role-aware user visibility with hidden superadmins;
- secure administrator sessions, CSRF and login throttling;
- optional, account-level YubiKey/WebAuthn second factor;
- installation and recovery commands in CLI.

## Stage 8 — filesystem page CRUD (complete)

- authenticated page tree and page-creation form;
- localized title, slug, layout, enabled state and core SEO editing;
- revision-safe YAML updates preserving blocks and custom attributes;
- atomic page-subtree moves with rollback on invalid resulting routes;
- permanent recursive deletion of page directories and their media;
- localized route, block, layout and collection validation before publication.

## Stage 9 — dynamic page builder

- block picker generated from `block.yml` definitions;
- schema-driven block forms and language switching;
- add, edit, duplicate, enable, reorder and delete block operations;
- revision-safe page-builder persistence.

## Stages 10–12

Password reset and mail; audit log; media; navigation/config editors; hardening
and production release.
