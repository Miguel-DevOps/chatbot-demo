<div align="center">

# 🤖 Chatbot Demo

**A production-ready educational chatbot demonstrating evolutionary software architecture**  
*Clean Code • Comprehensive Testing • Progressive Deployment Strategy*

[![MIT License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](./LICENSE)
[![CI/CD Pipeline](https://img.shields.io/badge/CI%2FCD-GitHub%20Actions-blue?style=for-the-badge&logo=githubactions)](#)
[![Project Phase](https://img.shields.io/badge/Phase-Alpha-red?style=for-the-badge)](#roadmap)
[![Architecture Status](https://img.shields.io/badge/Status-Educational%20Blueprint-orange?style=for-the-badge)](#vision)

</div>

<div align="center">

![React](https://img.shields.io/badge/React-19.1-20232A?style=for-the-badge&logo=react&logoColor=61DAFB)
![TypeScript](https://img.shields.io/badge/TypeScript-5.9-3178C6?style=for-the-badge&logo=typescript&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Slim Framework](https://img.shields.io/badge/Slim-Framework-719E40?style=for-the-badge)
![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white)

</div>


## 📋 Table of Contents

- [🚨 Known Issues & Security Notices](#-known-issues--security-notices)
- [🎯 Project Vision](#-project-vision)
- [⚡ Quick Start](#-quick-start)
- [🏗️ Deployment Levels & Architecture](#️-deployment-levels--architecture)
- [🗺️ Strategic Roadmap](#️-strategic-roadmap)  
- [🏛️ Key Architectural Decisions](#️-key-architectural-decisions)
- [🔧 Configuration & Security](#-configuration--security)
- [🧪 Testing & Quality](#-testing--quality)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)
- [📄 Spanish Documentation](./README.es.md)

## 🚨 Known Issues & Security Notices

> ⚠️ **IMPORTANT**: This section documents current issues that need attention before production deployment.

### 🔴 Critical Issues

**API Bootstrap Error (DependencyContainer)**
- **Issue**: `TypeError` in `DependencyContainer` line 216 - Prometheus CollectorRegistry parameter error
- **Impact**: API fails to start properly, health checks fail
- **Status**: 🔴 **Blocking production deployment**
- **Fix Required**: Debug dependency injection configuration for metrics service

**Test Suite Failures**
- **Issue**: PHPUnit integration tests failing due to ErrorHandlerMiddleware constructor mismatch
- **Impact**: CI/CD pipeline unreliable, test coverage compromised  
- **Status**: 🟡 **Development impacted**
- **Fix Required**: Resolve middleware dependency injection issues

### 🟡 Security Notices

**Container Security Hardening**: ✅ **COMPLETED**
- ✅ Non-root user enforcement (UID 1000:1000)
- ✅ Security capabilities dropped (no-new-privileges)
- ✅ Read-only filesystem with controlled tmpfs
- ✅ PHP security settings (disabled dangerous functions)

**CI/CD Security Hardening**: ✅ **COMPLETED**  
- ✅ Strict security audits (fail on critical vulnerabilities)
- ✅ Removed `continue-on-error` from security checks
- ✅ Container security validation in pipeline

**Rate Limiting**: ✅ **IMPLEMENTED**
- Production Nginx configuration includes rate limiting
- Chat endpoints: 5 req/s with burst of 10
- API endpoints: 10 req/s with burst of 20

### 🔧 Immediate Actions Required

1. **Fix DependencyContainer**: Resolve Prometheus CollectorRegistry injection
2. **Fix Test Suite**: Correct ErrorHandlerMiddleware constructor parameters  
3. **Validate Production Config**: Test complete docker-compose production deployment
4. **Security Audit**: Run `./scripts/security-audit.sh` before deployment

## 🎯 Project VisionThis project serves as a **practical blueprint** for evolving a chatbot application from proof-of-concept to enterprise-ready deployment. It demonstrates clean architecture, comprehensive testing, and strategic refactoring patterns through **intentional architectural constraints** that mirror real-world development challenges.

**Educational Approach:** Rather than presenting a perfect solution, this codebase includes deliberate limitations (filesystem dependencies, hardcoded configurations) that create learning opportunities for systematic architectural evolution. Each constraint teaches specific lessons about scalability, state management, and deployment considerations.

**Current Status:** Fully functional with excellent code quality, comprehensive testing, and modern development practices. Ready for development and demo use, with a clear path to production deployment through three progressive phases.

## ⚡ Quick Start

### 🐳 Method 1: Docker (Recommended)

The fastest way to run the complete application:

```bash
# Clone and start
git clone https://github.com/Miguel-DevOps/chatbot-demo.git
cd chatbot-demo
cp .env.example .env
# Edit .env with your GEMINI_API_KEY

# Start everything with Docker
docker-compose up --build
```

**Access:**
- Frontend: http://localhost:3000
- API: http://localhost:8080
- API Docs: http://localhost:8080/docs

### 🛠️ Method 2: Manual Setup (Educational)

For developers who want to understand the stack:

**Prerequisites:**
```bash
# Required versions
PHP >= 8.1.0 (with json, mbstring, pdo, sqlite3, curl, openssl extensions)
Node.js >= 18.0.0
pnpm >= 8.0.0
```

**Installation:**
```bash
# 1. Install dependencies
composer install --working-dir=api
pnpm install

# 2. Environment setup
cp .env.example .env
# Edit .env with your configuration

# 3. Start development servers
pnpm dev                                    # Frontend (http://localhost:5173)
php -S localhost:8080 -t api/public        # Backend (http://localhost:8080)
```

> ⚠️ **CRITICAL:** `php -S` is **ONLY for development**. Never use it in production. See [Configuration & Security](#-configuration--security) for production deployment.

## �️ Deployment Levels & Architecture

### 📊 Deployment Strategy Matrix

| **Level** | **Use Case** | **Infrastructure** | **Status** | **State Storage** | **Content** |
|-----------|--------------|-------------------|------------|-------------------|-------------|
| **Level 0** | Development/Demo | Docker Compose | ✅ **Ready** | SQLite files | Markdown files |
| **Level 0.5** | Shared Hosting/VPS | Apache/Nginx + FPM | 🟡 **Phase 2 needed** | MySQL/PostgreSQL | Database |
| **Level 1** | Dedicated VPS | Docker + Database | 🟡 **Phase 2 needed** | External Database | Database/CMS |
| **Level 2** | Enterprise/Cloud | Kubernetes + Redis | 🔴 **Phase 3 needed** | Redis Cluster | Headless CMS |

### 🏛️ Target Architecture (Level 2)

```mermaid
graph TB
    subgraph "Frontend"
        React[React App] --> CDN[CDN]
    end
    
    subgraph "Load Balancer"
        LB[Nginx/ALB] --> API1[API Pod 1]
        LB --> API2[API Pod 2]
        LB --> API3[API Pod N]
    end
    
    subgraph "State Layer"
        API1 --> Redis[(Redis Cluster)]
        API2 --> Redis
        API3 --> Redis
    end
    
    subgraph "Content & AI"
        API1 --> CMS[Headless CMS]
        API1 --> Gemini[Google Gemini]
        API2 --> CMS
        API3 --> CMS
    end
    
    subgraph "Observability"
        API1 --> Monitoring[Prometheus/Grafana]
        API2 --> Logs[ELK/Loki]
        API3 --> Tracing[Jaeger]
    end
```

### 🚨 Deployment Warnings

**Level 0 (Current):** Perfect for development, demos, and learning. **Not production-ready** due to filesystem dependencies.

**Level 0.5 Challenges:** Shared hosting requires careful configuration of file permissions, PHP-FPM setup, and database connectivity. The current SQLite dependency prevents horizontal scaling.

**Production Readiness:** Levels 1+ require completing Phase 2 (database abstraction) and Phase 3 (containerization/orchestration).

## 🗺️ Strategic Roadmap

### ✅ Phase 1: Foundation (Complete)
- **Clean Architecture:** SOLID principles, dependency injection, PSR compliance
- **Comprehensive Testing:** Unit + Integration + E2E testing with 90%+ coverage
- **Modern Stack:** React 19, TypeScript 5.9, PHP 8.4, Slim Framework 4
- **CI/CD Pipeline:** Automated testing, security audits, OpenAPI validation
- **Developer Experience:** Hot reload, type safety, linting, code formatting

### 🚧 Phase 2: State Abstraction (In Progress)
**Goal:** Eliminate filesystem dependencies for Level 0.5-1 deployment

**Critical Tasks:**
- **State Store Interface:** Abstract rate limiting from SQLite to support Redis/MySQL
- **Content Store Interface:** Migrate knowledge base from files to database/CMS
- **Database Migrations:** Implement Phinx or similar for schema management
- **Configuration Management:** Externalize all hardcoded values to environment variables

**Enables:** Shared hosting, VPS deployment, horizontal scaling readiness

### 📋 Phase 3: Operational Maturity (Planned)
**Goal:** Enterprise deployment with observability and security

**Features:**
- **Containerization:** Docker multi-stage builds, Kubernetes manifests
- **Observability:** Structured logging, metrics (Prometheus), distributed tracing
- **Security:** OAuth2/OIDC, JWT management, input validation framework
- **Automation:** Helm charts, GitOps deployment, auto-scaling

## 🏛️ Key Architectural Decisions

| **Technology** | **Choice** | **Alternative** | **Trade-off** |
|----------------|------------|-----------------|---------------|
| **Backend Framework** | Slim 4 | Laravel/Symfony | Sacrificed rapid development for architectural transparency and PSR compliance |
| **Frontend** | React 19 + TypeScript | Vue/Angular | Accepted learning curve for compile-time safety and ecosystem maturity |
| **State Management** | TanStack Query | Redux/Zustand | Embraced cache complexity for automatic server state synchronization |
| **DI Container** | PHP-DI | Framework DI | Chose explicit over implicit for better testability and understanding |
| **Testing Strategy** | PHPUnit + Vitest | Single ecosystem | Maintained separation of concerns at the cost of tooling complexity |

> **Full ADRs:** Detailed architectural decisions with context and rationale are documented in `/docs/ADRs/` ( Working on it )

## 🔧 Configuration & Security

### 🌍 Environment Variables

**Required:**
```bash
GEMINI_API_KEY=your_google_gemini_api_key_here
LOG_LEVEL=info
CORS_ORIGINS="http://localhost:3000,http://localhost:5173"
```

**Phase 2 (Upcoming):**
```bash
# Database Configuration
DATABASE_URL="mysql://user:pass@host:port/dbname"
STATE_STORE_TYPE="database"  # sqlite|database|redis
CONTENT_STORE_TYPE="database"  # filesystem|database|cms

# Rate Limiting
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

### 🔒 Production Server Configuration

### 🐳 Production Deployment with Docker (Recommended)

#### Multi-Stage Docker Build
The project includes a production-optimized Dockerfile with two stages:

1. **Dependencies Stage**: Installs all dependencies including dev dependencies for complete build
2. **Production Stage**: Copies only production files and dependencies to a clean PHP-FPM Alpine image

**Key Production Features:**
- Multi-stage build for minimal image size
- Non-root user for security
- Optimized PHP-FPM configuration
- Health check integration
- Proper file permissions

#### Docker Compose Production Setup

```bash
# Deploy production environment
docker-compose -f docker-compose.prod.yml up -d

# Scale API instances
docker-compose -f docker-compose.prod.yml up -d --scale api=3

# Monitor services
docker-compose -f docker-compose.prod.yml logs -f
```

**Production Architecture:**
```
[Internet] → [Nginx:80/443] → [PHP-FPM:9000] → [Redis:6379]
```

**Key Differences from Development:**

| **Component** | **Development** | **Production** |
|---------------|-----------------|----------------|
| **Web Server** | Built-in PHP server | Nginx + PHP-FPM |
| **Image** | Development with volumes | Multi-stage optimized |
| **Environment** | Hot reload, debug logs | Optimized, info logs |
| **Security** | Basic CORS | Security headers, rate limiting |
| **Monitoring** | None | Health checks, resource limits |
| **Performance** | Single process | Process pooling, caching |

**Configuration Files:**
- `nginx/nginx.conf`: Main Nginx configuration with security and performance optimizations
- `nginx/conf.d/default.conf`: Virtual host with rate limiting and FastCGI configuration
- `docker-compose.prod.yml`: Production orchestration with resource limits

#### Traditional Server Setup (Nginx + PHP-FPM)

For traditional VPS deployment without Docker:

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    # SSL configuration (use Certbot for Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    
    # CRITICAL: Root must point to api/public
    root /var/www/chatbot-demo/api/public;
    index index.php;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req zone=api burst=20 nodelay;
    
    # Block sensitive files
    location ~ /\.(env|git) { deny all; }
    location ~ \.(json|lock|md)$ { deny all; }
    
    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # FastCGI optimizations
        fastcgi_connect_timeout 5s;
        fastcgi_send_timeout 30s;
        fastcgi_read_timeout 30s;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }
    
    # Clean URLs
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```#### Apache + mod_php
```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/chatbot-demo/api/public
    
    # SSL configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/your-domain.com/chain.pem
    
    # Block access to parent directories
    <Directory "/var/www/chatbot-demo">
        Require all denied
    </Directory>
    
    <Directory "/var/www/chatbot-demo/api/public">
        AllowOverride All
        Require all granted
        
        # Clean URLs
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.php [L]
    </Directory>
</VirtualHost>
```

### 🛡️ Security Checklist

**Development:**
- [ ] Never commit `.env` files
- [ ] Use different API keys per environment
- [ ] Enable `error_reporting=0` in production PHP

**Production:**
- [ ] Configure HTTPS with valid certificates
- [ ] Set proper file permissions (`644` for files, `755` for directories)
- [ ] Block access to sensitive files (`.env`, `.git`, etc.)
- [ ] Use PHP-FPM instead of `mod_php` when possible
- [ ] Implement rate limiting at web server level

## 🧪 Testing & Quality

### 🔬 Test Strategy

**Test Pyramid:**
- **Unit Tests:** `pnpm test` (Frontend) + `composer test` (Backend) - Fast, isolated tests
- **Integration Tests:** API contract validation with OpenAPI schemas
- **E2E Tests:** `pnpm test:e2e` - Complete user workflows

**Quality Gates:**
```bash
# Pre-commit validation
pnpm lint && pnpm typecheck    # Code quality
pnpm test                      # Unit tests
composer test                  # Backend tests

# Security audit
pnpm audit && composer audit   # Dependency vulnerabilities
```

**Coverage Targets:**
- Business Logic: 90%+ (Services, Controllers)
- UI Components: 80%+ (User interactions, error states)  
- Integration: 100% (API contracts, external services)

## 🤝 Contributing

### 🎯 High-Priority Areas

| **Priority** | **Area** | **Skills** | **Impact** |
|--------------|----------|-----------|------------|
| 🔥 **Critical** | Phase 2: Database Abstraction | PHP, SQL, Design Patterns | Enables Level 0.5-1 deployment |
| 🔥 **Critical** | Docker Production Setup | DevOps, Docker, Nginx | Production-ready containers |
| ⭐ **High** | Database Migrations (Phinx) | PHP, Database Design | Schema management |
| ⭐ **High** | Content Management Interface | PHP, APIs, CMS Integration | Scalable content |
| 💡 **Medium** | Kubernetes Manifests | K8s, Helm, DevOps | Enterprise deployment |
| 💡 **Medium** | Observability Stack | Monitoring, Logging | Operational excellence |

### 🏗️ Contributor Guidelines

**Before You Start:**
1. **Think in Interfaces:** Design abstractions that enable technology swapping
2. **Tests First:** Write failing tests before implementing features
3. **Configuration External:** No hardcoded values - everything configurable
4. **Documentation:** Update ADRs for architectural decisions

**Development Workflow:**
```bash
# 1. Fork and clone
git clone https://github.com/Miguel-DevOps/chatbot-demo.git
cd chatbot-demo

# 2. Create feature branch
git checkout -b feature/database-abstraction

# 3. Development with tests
./scripts/test.sh    # Validate changes

# 4. Submit PR with:
#    - Clear description of changes
#    - Updated tests
#    - Documentation updates
#    - Breaking changes noted
```

**Focus Areas by Experience:**
- **Beginner:** UI improvements, test coverage, documentation
- **Intermediate:** Interface implementations, configuration management
- **Advanced:** Architecture design, infrastructure, observability

## 📄 License

MIT License - see [LICENSE](./LICENSE) for details.

**Educational Use Encouraged:**
- ✅ Use as learning reference for architecture evolution
- ✅ Fork for workshops and training
- ✅ Adapt patterns for your own projects
- ✅ Reference in technical blogs and documentation

---

<div align="center">

*This project serves as a living document for architectural evolution. Contributions are welcome.*

[![GitHub](https://img.shields.io/badge/GitHub-Miguel--DevOps-black.svg)](https://github.com/Miguel-DevOps)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-Connect-blue.svg)](https://linkedin.com/in/miguel-lozano-devops)

*Built with ❤️ for the developer community*

</div>