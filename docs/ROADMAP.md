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

## Stage 3 — pages, localization and public API

- page/config/navigation repositories;
- localized route index;
- SEO resolver;
- public page/navigation/config endpoints;
- ETag and Last-Modified.

## Stage 4 — block schema and validation

- block discovery and registry;
- field-type registry;
- normalization, localization and validation.

## Stage 5 — server-side rendering

- block, page and layout renderers;
- safe render context and Markdown;
- partial registry;
- asset collection, publication and fingerprinting.

## Stages 6–12

Collections; users/auth; page CRUD; dynamic page builder; media; navigation/config editors; CLI, hardening, complete documentation and production release.
