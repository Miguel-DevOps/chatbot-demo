# Security policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | ✅ Yes    |
| < 1.0   | ❌ No     |

## Reporting a vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Report vulnerabilities privately via one of these channels:

- **GitHub Security Advisories:** [Report a vulnerability](https://github.com/Miguel-DevOps/chatbot-demo/security/advisories/new)
- **Email:** miguel@developmi.com — encrypt with PGP if the finding is critical.

### What to include

- Type of issue (e.g. buffer overflow, SQL injection, cross-site scripting)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

## Response timeline

| Stage | Target time |
|---|---|
| Acknowledgment | 48 hours |
| Initial assessment | 5 business days |
| Fix or mitigation | 30 days (critical: 7 days) |
| Public disclosure | After fix is available |

## Disclosure policy

This project follows coordinated disclosure. We ask that you give us reasonable time to address the vulnerability before public disclosure. We will credit reporters in the release notes unless anonymity is requested.

## Security features

### Current implementation

- ✅ Environment variable protection
- ✅ Rate limiting (100 requests/hour per IP)
- ✅ Input validation and sanitization
- ✅ Secure headers implementation
- ✅ HTTPS enforcement
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ CSRF protection
- ✅ Non-root container enforcement (UID 1000:1000)
- ✅ Container security capabilities (no-new-privileges, read-only filesystem)
- ✅ PHP security hardening (disabled dangerous functions)

### Security scanning

Our CI/CD pipeline includes:

- **Trivy**: Vulnerability scanning
- **Composer Audit**: PHP dependency security
- **pnpm audit**: Node.js dependency security
- **Static Analysis**: ESLint security rules

### Dependencies

We regularly update dependencies and monitor for security advisories through:

- GitHub Dependabot
- Automated security updates
- Regular audit reviews

## Best practices

When contributing to this project:

1. **Never commit secrets** — Use environment variables
2. **Validate all inputs** — Sanitize user data
3. **Use prepared statements** — Prevent SQL injection
4. **Implement rate limiting** — Prevent abuse
5. **Follow secure coding standards** — OWASP guidelines
6. **Keep dependencies updated** — Regular security patches
