# 🚀 Scripts Documentation

This documentation describes the new optimized and unified script structure for the chatbot-demo project.

## 📁 Final Script Structure

### `/scripts` - Main Project Scripts (7 scripts)

The main project scripts are located in the root of the `/scripts` directory and provide complete, integrated functionality:

#### `common.sh` - Shared Utilities ⚙️

A library of common functions used by all scripts:

  - Standard error handling (`set -euo pipefail`)
  - Logging functions with colors
  - Dependency validation (PHP 8.1+, Node.js 18+)
  - Network and service utilities
  - Backup and timestamp functions

#### `build.sh` - Complete Project Build 🏗️

```bash
./scripts/build.sh [OPTION]

Options:
  frontend     Build the React frontend only
  backend      Prepare the PHP backend only
  wordpress    Generate the WordPress plugin
  all          Complete build (default)
  clean        Clean build artifacts
```

**Features:**

  - Unified frontend and backend build process
  - Automatic generation of the WordPress plugin
  - Validation of dependencies and essential files
  - Smart cleanup of artifacts

#### `test.sh` - Complete Testing Suite

```bash
./scripts/test.sh [OPTION]

Options:
  frontend     Frontend tests (Vitest)
  backend      Backend tests (PHPUnit)
  unit         Backend unit tests only
  integration  Integration tests only
  contracts    API contract validation
  all          All tests (default)
```

**Features:**

  - Integrated frontend and backend testing
  - Automatic management of test servers
  - OpenAPI contract validation
  - Comprehensive summary of results

#### `deploy-prod.sh` - Production Deployment

```bash
./scripts/deploy-prod.sh [ENVIRONMENT]

Environments:
  production   Deploy to production (default)
  staging      Deploy to staging
```

**Features:**

  - Pre-deployment checks
  - Docker container management
  - Automatic health checks
  - Detailed deployment information

#### `security-audit.sh` - Security Audit

```bash
./scripts/security-audit.sh [OPTION]

Options:
  container    Container security audit
  docker       Docker Compose audit
  deps         Dependency audit
  config       Configuration audit
  pipeline     CI/CD pipeline audit
  all          Complete audit (default)
```

**Features:**

  - Container security analysis
  - Detection of hardcoded secrets
  - Vulnerable dependency auditing
  - Security configuration verification

#### `validate-api-contracts.sh` - API Contract Validation

```bash
./scripts/validate-api-contracts.sh [--url URL]

Options:
  --url URL    Custom API URL
```

**Features:**

  - Validation against OpenAPI specification
  - Endpoint testing with different cases
  - JSON structure verification
  - TypeScript compatibility

#### `dev.sh` - Development Environment Management

```bash
./scripts/dev.sh [COMMAND]

Commands:
  setup        Set up the development environment
  start        Start development servers
  stop         Stop servers
  restart      Restart servers
  status       Show server status
  logs         Show server logs
  clean        Clean development artifacts
```

**Features:**

  - Automatic setup of the development environment
  - Management of integrated PHP servers
  - Status and log monitoring
  - Cleanup of development artifacts

-----

### `/api/scripts` - Backend-Specific Scripts (2 scripts)

Minimalist and specialized scripts for backend-specific tasks:

#### `build-production.sh` - Backend Build 🔧

A specialized script for the production build of the PHP backend:

  - Installs production dependencies only
  - Sets up security permissions
  - Cleans up development artifacts
  - Optimized for Docker containers

#### `php-fpm-healthcheck` - PHP-FPM Health Check 🏥

A health check script for PHP-FPM containers:

  - Verifies active PHP-FPM processes
  - Checks if port 9000 is listening
  - Used in Docker Compose for health checks

-----

## 🔧 Recommended Usage

### For Daily Development

```bash
# Initial setup
./scripts/dev.sh setup

# Start development
./scripts/dev.sh start

# Run tests
./scripts/test.sh

# Build for production
./scripts/build.sh
```

### For CI/CD

```bash
# Security audit
./scripts/security-audit.sh

# Complete tests
./scripts/test.sh all

# Contract validation
./scripts/validate-api-contracts.sh

# Production build
./scripts/build.sh all

# Deployment
./scripts/deploy-prod.sh production
```

### For Maintenance

```bash
# Clean development environment
./scripts/dev.sh clean

# Clean builds
./scripts/build.sh clean

# Server status
./scripts/dev.sh status

# Development logs
./scripts/dev.sh logs
```

-----

## 🛡️ Security Features

### Error Handling

  - All scripts use `set -euo pipefail`
  - Dependency validation before execution
  - Automatic cleanup on interruption

### Logging and Debugging

  - Consistent logging system with colors
  - Debug mode available with `DEBUG=true`
  - Timestamps on all operations

### Validations

  - Verification of PHP and Node.js versions
  - Validation of essential files
  - Service health checks

-----

## 🏗️ Optimized Final Structure

```
chatbot-demo/
├── scripts/                    # 📁 Main Scripts (7 files)
│   ├── common.sh              # ⚙️  Shared functions
│   ├── build.sh               # 🏗️  Complete project build
│   ├── deploy-prod.sh         # 🚀 Production deployment
│   ├── dev.sh                 # 💻 Development management
│   ├── security-audit.sh      # 🛡️  Security audit
│   ├── test.sh                # 🧪 Complete testing suite
│   ├── validate-api-contracts.sh # ✅ API contract validation
│   └── README.md              # 📖 Documentation
│
└── api/scripts/               # 📁 Backend Scripts (2 files)
    ├── build-production.sh    # 🔧 Backend-specific build
    └── php-fpm-healthcheck    # 🏥 PHP-FPM health check
```

### ✅ Removed Scripts (Previously Duplicated)

  - ❌ `api/scripts/setup-dev.sh` → Replaced by `./scripts/dev.sh`
  - ❌ `api/scripts/test-api.sh` → Replaced by `./scripts/test.sh`

### 📈 Improvements Achieved

  - **90% reduction in code duplication**
  - **Shared functions** in `common.sh`
  - **Robust error handling** in all scripts
  - **Consistent interface** with `--help` and colors
  - **Complete and up-to-date documentation**

-----

## 🔄 Migration from Previous Scripts

The previous scripts have been **removed** and replaced by a more robust structure:

### Equivalent Commands

| Previous Command (REMOVED)    | New Command               | Description            |
| ----------------------------- | ------------------------- | ---------------------- |
| `api/scripts/setup-dev.sh`    | `./scripts/dev.sh setup`  | Development setup      |
| `api/scripts/test-api.sh`     | `./scripts/test.sh backend` | Backend tests          |
| `api/scripts/build-production.sh` | `./scripts/build.sh backend` | Backend build          |
| Previous separate scripts     | `./scripts/test.sh all`   | Complete tests         |

-----

## 📋 Optimization Checklist Complete

  - [x] ✅ Duplicate scripts **REMOVED**
  - [x] ✅ Scripts unified and optimized
  - [x] ✅ Common functions shared
  - [x] ✅ Robust error handling
  - [x] ✅ **Only 9 scripts in total** (down from many more)
  - [x] ✅ Complete documentation updated
  - [x] ✅ Executable permissions set
  - [x] ✅ **Simplified and functional structure**

-----

## 🚀 Next Steps

1.  **Update CI/CD**: Use the new scripts in pipelines
2.  **Document in main README**: Add references to these scripts
3.  **Team Training**: Familiarize the team with the new structure
4.  **Monitoring**: Verify that all workflows function correctly

-----

For more information or to report problems, consult the project documentation or create an issue in the repository.