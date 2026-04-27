# Contributing to Chatbot Demo

Thank you for your interest in contributing. This project follows the Developmi engineering standard.

## Development setup

```bash
# Clone and install
git clone https://github.com/Miguel-DevOps/chatbot-demo.git
cd chatbot-demo
pnpm install --frozen-lockfile
```

**Prerequisites:**

- Node.js 20+ and pnpm 8+
- PHP 8.4+ and Composer 2+
- Docker and Docker Compose

**Backend dependencies:**

```bash
cd api
composer install
cd ..
```

## Commit standard

This project uses [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

```
<type>(optional: scope): short and meaningful description
```

### Allowed commit types

| Type | When to use it |
|------|---------------|
| **feat** | New feature |
| **fix** | Bug fix |
| **docs** | Documentation-only changes |
| **style** | Code style (formatting), no logic changes |
| **refactor** | Code refactoring that doesn't add features or fix bugs |
| **perf** | Performance improvements |
| **test** | Adding or updating tests |
| **build** | Build process or dependency changes |
| **ci** | CI/CD configuration changes |
| **chore** | Minor tasks that don't affect source or docs |
| **revert** | Revert a previous commit |
| **hotfix** | Critical fix needing immediate deployment |

### Examples

```bash
feat(auth): add Google login integration
fix(api): fix user endpoint error
docs: update installation steps in README
refactor(user): simplify validation logic
test: add unit tests for LoginForm
build: update Node version in Dockerfile
ci: add GitHub Actions workflow for testing
chore: remove unused comments
```

### Best practices

- Use **infinitive verbs** in the description (add, fix, refactor).
- Keep the title line **under 72 characters**.
- If the change is complex, add a body section under the title with more context.
- Reference issues/tickets when needed, e.g. `#123`.

## Branch naming

```
feat/short-description
fix/issue-number-description
docs/update-readme
chore/bump-dependencies
refactor/component-name
test/add-coverage
```

## Pull request process

1. Fork the repository and create your branch from `main`.
2. Ensure all tests pass.
3. Ensure linting passes: `pnpm lint`.
4. Run the security audit: `pnpm security:js`.
5. Update documentation if your change affects public behavior.
6. Open a PR with a clear title following the commit standard.
7. A maintainer will review within 5 business days.

## Reporting issues

Use [GitHub Issues](https://github.com/Miguel-DevOps/chatbot-demo/issues). Include:

- Steps to reproduce
- Expected vs. actual behavior
- Environment (OS, runtime version, package manager version)
- Screenshots or logs if applicable

## Code of conduct

This project adheres to the [Contributor Covenant](https://www.contributor-covenant.org/version/2/1/code_of_conduct/).
