#!/bin/bash

# Test script for local development - simulates CI/CD pipeline locally

set -e

echo "🧪 Running local CI/CD simulation for ChatBot Demo"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Cleanup function
cleanup() {
    echo -e "\n🧹 Cleaning up..."
    docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml down -v || true
    docker image rm chatbot-api-test:latest || true
}

# Set trap to cleanup on exit
trap cleanup EXIT

echo "📋 Step 1: Verifying project structure..."
if [ ! -f "api/composer.json" ]; then
    print_error "composer.json not found in api directory"
    exit 1
fi

if [ ! -f "api/composer.lock" ]; then
    print_warning "composer.lock not found - will be generated during build"
fi

print_status "Project structure verified"

echo -e "\n🔨 Step 2: Building Docker image..."
cd api
docker build --target dependencies -t chatbot-api-test:latest .
cd ..
print_status "Docker image built successfully"

echo -e "\n🚀 Step 3: Starting test containers..."
docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml up -d redis

echo "Waiting for Redis to be ready..."
timeout 60 bash -c 'until docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml exec -T redis redis-cli ping; do sleep 2; done'

# Tag the image for docker-compose
docker tag chatbot-api-test:latest chatbot-demo-api:latest

docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml up -d api

echo "Waiting for API container to be ready..."
timeout 60 bash -c 'until docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml exec -T api php --version; do sleep 2; done'

print_status "Containers are ready"

echo -e "\n📦 Step 4: Installing dependencies..."
docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml exec -T api composer install --prefer-dist --no-progress --no-interaction
print_status "Dependencies installed"

echo -e "\n🔍 Step 5: Validating Composer configuration..."
docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml exec -T api composer validate --strict
print_status "Composer validation passed"

echo -e "\n🧪 Step 6: Running PHP Unit Tests..."
docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml exec -T api ./vendor/bin/phpunit tests/Unit/ --coverage-clover=coverage.xml --log-junit=tests/results/junit.xml || print_warning "Unit tests had issues (check output above)"

echo -e "\n🧪 Step 7: Running Integration Tests..."
docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml exec -T api ./vendor/bin/phpunit tests/Integration/ --log-junit=tests/results/integration-junit.xml || print_warning "Integration tests had issues (check output above)"

echo -e "\n📋 Step 8: Copying test results..."
docker cp $(docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml ps -q api):/var/www/html/tests/results/ ./api/tests/results/ || print_warning "Test results directory not found"
docker cp $(docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml ps -q api):/var/www/html/coverage.xml ./api/coverage.xml || print_warning "Coverage file not found"

print_status "Test results copied"

echo -e "\n🎉 Local CI/CD simulation completed!"
echo "📊 Check the following files for results:"
echo "   - api/tests/results/junit.xml"
echo "   - api/tests/results/integration-junit.xml" 
echo "   - api/coverage.xml"

# Don't cleanup automatically in case user wants to inspect
trap - EXIT
echo -e "\n💡 Run 'docker compose -f docker-compose.test.yml -f docker-compose.test.local.yml down -v' to cleanup when done"