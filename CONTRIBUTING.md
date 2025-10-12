# 📝 Commit Message Convention

This project follows a commit message convention based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).

Each commit message must follow this format:

```
<type>(optional: scope): short and meaningful description
```

---

### 📦 Allowed Commit Types

| Type         | When to use it                                                         |
| ------------ | ---------------------------------------------------------------------- |
| **feat**     | To add a new feature.                                                  |
| **fix**      | To fix a bug or error.                                                 |
| **docs**     | Documentation-only changes (README, comments, etc.).                   |
| **style**    | Code style changes (indentation, formatting), no logic changes.        |
| **refactor** | Code refactoring that doesn't add features or fix bugs.                |
| **perf**     | Performance improvements.                                              |
| **test**     | Adding or updating tests.                                              |
| **build**    | Changes related to build process or dependencies.                      |
| **ci**       | Changes to CI/CD configuration files and scripts.                      |
| **chore**    | Minor tasks that don’t affect source or documentation (e.g., cleanup). |
| **revert**   | Revert a previous commit.                                              |
| **enforce**  | Apply strict rules (e.g., linting, validation, type checks).           |
| **wip**      | Work in progress. Should not be merged into main. *(Use with caution)* |
| **hotfix**   | Critical fix that needs immediate deployment.                          |

---

### 📌 Examples

```bash
feat(auth): add Google login integration
fix(api): fix user endpoint error
docs: update installation steps in README
style: fix indentation in routes file
refactor(user): simplify validation logic
perf: improve image loading with lazy loading
test: add unit tests for LoginForm
build: update Node version in Dockerfile
ci: add GitHub Actions workflow for testing
chore: remove unused comments
revert: revert commit 8f3c2a1 due to logic issue
enforce: enforce lint rules before commit
wip: initial admin dashboard layout
hotfix: fix production login bug
```

---

### ✅ Best Practices

* Use **infinitive verbs** in the description (add, fix, refactor...).
* Keep the title line **under 72 characters**.
* If the change is complex, add a body section under the title with more context.
* Reference issues/tickets when needed, e.g. `#123`.

---