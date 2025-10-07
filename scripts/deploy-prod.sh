#!/bin/bash

# Production deployment script for chatbot-demo
# Usage: ./scripts/deploy-prod.sh [environment]

# Load common utilities
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# Configuration
ENVIRONMENT=${1:-production}

# Show help information
show_help() {
    show_header "Deploy Script Help" "Production deployment options"
    
    echo "USAGE:"
    echo "  ./deploy-prod.sh [ENVIRONMENT]"
    echo ""
    echo "ENVIRONMENTS:"
    echo "  production   Deploy to production (default)"
    echo "  staging      Deploy to staging environment"
    echo ""
    echo "EXAMPLES:"
    echo "  ./deploy-prod.sh              # Deploy to production"
    echo "  ./deploy-prod.sh staging      # Deploy to staging"
    echo ""
}

# Pre-deployment checks
run_pre_deployment_checks() {
    show_progress "Running pre-deployment checks"
    
    # Check required commands
    check_command "docker-compose" || return 1
    
    # Check configuration files
    check_file "$PROJECT_ROOT/docker-compose.prod.yml" "Production Docker Compose configuration" || return 1
    check_file "$PROJECT_ROOT/.env" "Environment configuration" || return 1
    
    # Load and validate environment variables
    source "$PROJECT_ROOT/.env"
    if [[ -z "${GEMINI_API_KEY:-}" ]]; then
        log_error "GEMINI_API_KEY is required in .env file"
        return 1
    fi
    
    log_success "Pre-deployment checks passed"
    return 0
}

# Stop development containers
stop_development_containers() {
    show_progress "Stopping development containers"
    
    cd "$PROJECT_ROOT"
    if docker-compose -f "docker-compose.yml" down 2>/dev/null; then
        log_success "Development containers stopped"
    else
        log_info "No development containers were running"
    fi
}

# Build and start production services
deploy_production_services() {
    show_progress "Building and starting production services"
    
    cd "$PROJECT_ROOT"
    
    # Build production images
    exec_with_log "docker-compose -f docker-compose.prod.yml build --no-cache" "Build production images" || return 1
    
    # Start production services
    exec_with_log "docker-compose -f docker-compose.prod.yml up -d" "Start production services" || return 1
    
    log_success "Production services deployed"
    return 0
}

# Perform health checks
perform_health_checks() {
    show_progress "Performing health checks"
    
    local api_url="http://localhost"
    
    # Wait for services to be ready
    log_info "Waiting for services to be ready..."
    sleep 10
    
    # Health check with retries
    wait_for_service "$api_url/health" 30 2 || {
        log_error "Health check failed. Showing service logs:"
        docker-compose -f "$PROJECT_ROOT/docker-compose.prod.yml" logs
        return 1
    }
    
    log_success "Health checks passed"
    return 0
}

# Show deployment information
show_deployment_info() {
    log_info "Deployment Information"
    echo "═══════════════════════════════════════"
    echo "Environment: $ENVIRONMENT"
    echo "API: http://localhost"
    echo "Health Check: http://localhost/health"
    echo "Metrics: http://localhost/metrics"
    echo ""
    echo "Useful Commands:"
    echo "- View logs: docker-compose -f docker-compose.prod.yml logs -f"
    echo "- Scale API: docker-compose -f docker-compose.prod.yml up -d --scale api=3"
    echo "- Stop services: docker-compose -f docker-compose.prod.yml down"
    echo "- Monitor: docker-compose -f docker-compose.prod.yml ps"
    echo ""
    echo "Resource Usage:"
    docker-compose -f "$PROJECT_ROOT/docker-compose.prod.yml" ps --format "table {{.Service}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || true
}

# Main deployment function
main() {
    local command="${1:-deploy}"
    
    case "$command" in
        --help|-h)
            show_help
            exit 0
            ;;
        deploy|"")
            show_header "Production Deployment" "Deploying to $ENVIRONMENT environment"
            
            # Run deployment steps
            run_pre_deployment_checks || exit 1
            echo
            
            stop_development_containers
            echo
            
            deploy_production_services || exit 1
            echo
            
            perform_health_checks || exit 1
            echo
            
            show_deployment_info
            log_success "Production deployment completed successfully!"
            show_footer "success"
            ;;
        *)
            log_error "Invalid command: $command"
            echo "Use --help to see available options"
            exit 1
            ;;
    esac
}

# Execute main function
main "$ENVIRONMENT"