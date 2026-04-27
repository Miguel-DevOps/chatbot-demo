# Changelog

All notable changes to this project will be documented in this file.
Format: [Keep a Changelog](https://keepachangelog.com/) · Versioning: [SemVer](https://semver.org/)

## [Unreleased]

## [1.0.0] — 2025-06-01

### Added
- Initial release of the Chatbot Demo educational blueprint.
- React 19.2 frontend with TypeScript 5.9, TailwindCSS 4.1, Radix UI, and TanStack Query.
- PHP 8.4 backend with Slim Framework 4, PHP-DI, and OpenTelemetry tracing.
- Comprehensive test suite: 99 tests (54 integration + 39 unit + 6 frontend) with 95%+ coverage.
- Docker Compose configurations for development, testing, production, and monitoring environments.
- Observability stack: Prometheus, Grafana, Jaeger, and OpenTelemetry Collector.
- Nginx reverse proxy with rate limiting and security headers.
- CI/CD pipeline with GitHub Actions (quality gate, frontend/backend tests, build verification).
- Pre-commit hooks with Husky and lint-staged.
- i18n support via i18next with English and Spanish translations.
- API contract specification (OpenAPI 3.0).
