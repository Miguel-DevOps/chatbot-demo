#!/bin/bash

# Backend-specific production build script
# This script is called by the main build script and focuses only on backend tasks

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_DIR="$(dirname "$SCRIPT_DIR")"

echo "🏗️ Building API for production..."

cd "$API_DIR"

# Check PHP availability
if ! command -v php >/dev/null 2>&1; then
    echo "❌ PHP is required but not installed"
    exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "❌ Composer is required but not installed"
    exit 1
fi

# Install production dependencies
echo "📦 Installing production dependencies..."
if ! composer install --no-dev --optimize-autoloader --no-scripts; then
    echo "❌ Failed to install production dependencies"
    exit 1
fi

# Clear development artifacts
echo "🧹 Clearing development artifacts..."
rm -rf storage/logs/*.log 2>/dev/null || true
rm -rf logs/*.log 2>/dev/null || true

# Set production permissions
echo "🔒 Setting production permissions..."
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod +x scripts/*.sh 2>/dev/null || true

# Create required directories with proper permissions
echo "📁 Creating required directories..."
mkdir -p storage/logs logs data
chmod 775 storage/logs logs data

echo "✅ Backend production build completed!"
echo ""
echo "📋 Backend is ready for:"
echo "1. Docker production build: docker build -t chatbot-api:prod ."
echo "2. Docker Compose deployment: docker-compose -f ../docker-compose.prod.yml up -d"