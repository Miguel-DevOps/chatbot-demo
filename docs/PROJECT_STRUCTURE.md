# Project Structure Guide

## 📁 Current Structure Overview

```
chatbot-demo/
├── api/                    # PHP Backend (Slim Framework)
│   ├── src/               # Source code
│   ├── tests/             # PHPUnit tests
│   ├── public/            # Public entry point
│   └── knowledge/         # Markdown knowledge base
├── src/                   # React Frontend (TypeScript)
│   ├── components/        # UI Components
│   ├── hooks/             # Custom React hooks
│   ├── services/          # API services
│   └── __tests__/         # Vitest tests
├── nginx/                 # Nginx configuration
├── monitoring/            # Prometheus, Grafana configs
├── observability/         # OpenTelemetry config
├── .github/workflows/     # CI/CD pipelines
└── docs/                  # Documentation (this folder)
```

## 🏗️ Recommended Structure for New Files

To maintain organization as the project grows, follow these guidelines:

### Documentation (`docs/`)

- `docs/evolution/` - Architectural evolution plans (Spanish/English)
- `docs/architecture/` - Architectural Decision Records (ADRs)
- `docs/api/` - API documentation, OpenAPI specs
- `docs/deployment/` - Deployment guides for different environments
- `docs/guides/` - Tutorials and how-to guides
- `docs/contributing/` - Contribution guidelines

### Configuration (`config/` - Future)

- `config/docker/` - Docker Compose configurations
- `config/nginx/` - Nginx configurations
- `config/monitoring/` - Monitoring setups
- `config/environments/` - Environment-specific configs

### Development (`scripts/` - Enhanced)

- `scripts/dev/` - Development environment scripts
- `scripts/build/` - Build and packaging scripts
- `scripts/deploy/` - Deployment scripts
- `scripts/test/` - Testing automation scripts

## 📋 File Organization Principles

1. **Keep root directory clean** - Only essential project files
2. **Group by concern** - Documentation, config, source code separated
3. **Language separation** - Backend (PHP) vs Frontend (TypeScript) clearly divided
4. **Environment awareness** - Dev, test, prod configurations separated
5. **Documentation proximity** - Docs near relevant code when possible

## 🔄 Migration Considerations

**Current files remain in place** to avoid breaking existing references. New files should follow the recommended structure.

**Critical root files:**

- `docker-compose.*.yml` - Required for Docker tooling
- `package.json`, `composer.json` - Package manager expectations
- `*.config.*` - Build tool configuration files
- `.env*` - Environment files (security sensitive)

## 🎯 Benefits of Organized Structure

- **Better onboarding** - Clear organization helps new contributors
- **Scalability** - Easy to add new features without clutter
- **Maintainability** - Related files grouped together
- **Professional appearance** - Shows attention to software engineering best practices
- **Portfolio value** - Demonstrates systematic approach to project organization

## 📚 Related Documentation

- [Architectural Evolution Plan (English)](./evolution/ARCHITECTURE_EVOLUTION_EN.md)
- [Plan de Evolución Arquitectónica (Español)](./evolution/ARCHITECTURE_EVOLUTION_ES.md)
- [README.md](../README.md) - Project overview
- [README.es.md](../README.es.md) - Spanish documentation

---

**Maintained by:** Developmi (Miguel Lozano - @Miguel-DevOps)  
**Last updated:** February 2026  
**Status:** Living document - evolves with the project
