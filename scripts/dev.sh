#!/bin/bash

# Development environment management script
# Handles development setup, server management, and common dev tasks

# Load common utilities
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# Configuration
SERVER_HOST="localhost"
SERVER_PORT="8080"
SERVER_PID_FILE="/tmp/chatbot_dev_server.pid"
LOG_FILE="/tmp/chatbot_dev_server.log"

# Show help information
show_help() {
    show_header "Development Script Help" "Development environment management"
    
    echo "USAGE:"
    echo "  ./dev.sh [COMMAND]"
    echo ""
    echo "COMMANDS:"
    echo "  setup        Setup development environment"
    echo "  start        Start development servers"
    echo "  stop         Stop development servers"
    echo "  restart      Restart development servers"
    echo "  status       Show server status"
    echo "  logs         Show server logs"
    echo "  clean        Clean development artifacts"
    echo "  --help, -h   Show this help"
    echo ""
    echo "EXAMPLES:"
    echo "  ./dev.sh setup      # Initial development setup"
    echo "  ./dev.sh start      # Start all servers"
    echo "  ./dev.sh logs       # View server logs"
    echo ""
}

# Cleanup function
cleanup_dev_environment() {
    if [[ -f "$SERVER_PID_FILE" ]]; then
        local server_pid
        server_pid=$(cat "$SERVER_PID_FILE")
        if kill -0 "$server_pid" 2>/dev/null; then
            log_info "Stopping development server (PID: $server_pid)"
            kill "$server_pid" 2>/dev/null || true
            wait "$server_pid" 2>/dev/null || true
        fi
        rm -f "$SERVER_PID_FILE"
    fi
    rm -f "$LOG_FILE"
}

# Register cleanup
register_cleanup cleanup_dev_environment

# Setup development environment
setup_development() {
    show_progress "Setting up development environment"
    
    # Check system requirements
    log_info "Checking system requirements..."
    check_command "node" "Node.js" || return 1
    check_command "pnpm" || return 1
    check_command "php" || return 1
    check_command "composer" || return 1
    
    check_node_version 18 || return 1
    check_php_version 8.1 || return 1
    
    # Setup frontend
    log_info "Setting up frontend..."
    cd "$PROJECT_ROOT"
    if [[ ! -d "node_modules" ]]; then
        exec_with_log "pnpm install" "Install frontend dependencies"
    else
        log_success "Frontend dependencies already installed"
    fi
    
    # Setup backend
    log_info "Setting up backend..."
    cd "$API_DIR"
    if [[ ! -d "vendor" ]]; then
        exec_with_log "composer install" "Install backend dependencies"
    else
        log_success "Backend dependencies already installed"
    fi
    
    # Create development directories
    log_info "Creating development directories..."
    for dir in "storage/logs" "logs" "data"; do
        if [[ ! -d "$API_DIR/$dir" ]]; then
            mkdir -p "$API_DIR/$dir"
            chmod 775 "$API_DIR/$dir"
            log_debug "Created directory: $dir"
        fi
    done
    
    # Set development permissions
    log_info "Setting development permissions..."
    find "$API_DIR/scripts" -name "*.sh" -exec chmod +x {} \; 2>/dev/null || true
    
    # Check environment configuration
    if [[ ! -f "$PROJECT_ROOT/.env" ]]; then
        log_warning "No .env file found"
        if [[ -f "$PROJECT_ROOT/.env.example" ]]; then
            log_info "Copy .env.example to .env and configure it:"
            echo "  cp .env.example .env"
        else
            log_info "Create .env file with required variables:"
            echo "  GEMINI_API_KEY=your_api_key_here"
            echo "  LOG_LEVEL=debug"
            echo "  APP_ENV=development"
        fi
    fi
    
    # Run basic tests to verify setup
    log_info "Verifying setup with basic tests..."
    cd "$API_DIR"
    if [[ -f "vendor/bin/phpunit" ]]; then
        if exec_with_log "./vendor/bin/phpunit --version" "Check PHPUnit installation" >/dev/null 2>&1; then
            log_success "PHPUnit is ready"
        fi
    fi
    
    cd "$PROJECT_ROOT"
    if exec_with_log "pnpm test --version" "Check Vitest installation" >/dev/null 2>&1; then
        log_success "Vitest is ready"
    fi
    
    log_success "Development environment setup completed!"
    echo
    log_info "Quick start:"
    echo "1. Configure .env file with your API keys"
    echo "2. Start development server: ./dev.sh start"
    echo "3. Test API: curl http://localhost:8080/health"
    
    return 0
}

# Start development servers
start_development_servers() {
    show_progress "Starting development servers"
    
    cd "$API_DIR"
    
    # Check if server is already running
    if [[ -f "$SERVER_PID_FILE" ]]; then
        local existing_pid
        existing_pid=$(cat "$SERVER_PID_FILE")
        if kill -0 "$existing_pid" 2>/dev/null; then
            log_warning "Development server already running (PID: $existing_pid)"
            return 0
        else
            rm -f "$SERVER_PID_FILE"
        fi
    fi
    
    # Verify setup
    check_directory "vendor" "PHP dependencies" || {
        log_info "Backend not set up, setting up now..."
        setup_development || return 1
    }
    
    check_directory "public" "API public directory" || return 1
    
    # Start PHP development server
    log_info "Starting PHP development server on ${SERVER_HOST}:${SERVER_PORT}..."
    cd public
    php -S "${SERVER_HOST}:${SERVER_PORT}" > "$LOG_FILE" 2>&1 &
    local server_pid=$!
    echo $server_pid > "$SERVER_PID_FILE"
    
    # Wait for server to be ready
    wait_for_service "http://${SERVER_HOST}:${SERVER_PORT}/health" 10 1 || {
        log_error "Server failed to start. Log output:"
        cat "$LOG_FILE" 2>/dev/null || true
        return 1
    }
    
    log_success "Development server started (PID: $server_pid)"
    log_info "Server accessible at: http://${SERVER_HOST}:${SERVER_PORT}"
    log_info "Health check: http://${SERVER_HOST}:${SERVER_PORT}/health"
    
    return 0
}

# Stop development servers
stop_development_servers() {
    show_progress "Stopping development servers"
    
    cleanup_dev_environment
    log_success "Development servers stopped"
}

# Show server status
show_server_status() {
    log_info "Development Server Status"
    echo "═══════════════════════════════════════"
    
    if [[ -f "$SERVER_PID_FILE" ]]; then
        local server_pid
        server_pid=$(cat "$SERVER_PID_FILE")
        if kill -0 "$server_pid" 2>/dev/null; then
            log_success "Server is running (PID: $server_pid)"
            echo "URL: http://${SERVER_HOST}:${SERVER_PORT}"
            
            # Test if server is responding
            if curl -s "http://${SERVER_HOST}:${SERVER_PORT}/health" > /dev/null 2>&1; then
                log_success "Server is responding to requests"
            else
                log_warning "Server process exists but not responding"
            fi
        else
            log_warning "Server PID file exists but process is not running"
            rm -f "$SERVER_PID_FILE"
        fi
    else
        log_info "Server is not running"
    fi
}

# Show server logs
show_server_logs() {
    log_info "Development Server Logs"
    echo "═══════════════════════════════════════"
    
    if [[ -f "$LOG_FILE" ]]; then
        tail -f "$LOG_FILE"
    else
        log_info "No log file found. Server may not be running."
    fi
}

# Clean development artifacts
clean_development() {
    show_progress "Cleaning development artifacts"
    
    # Stop servers first
    cleanup_dev_environment
    
    # Clean logs
    rm -f "$LOG_FILE"
    if [[ -d "$API_DIR/logs" ]]; then
        rm -f "$API_DIR/logs"/*.log 2>/dev/null || true
        log_success "Cleaned API logs"
    fi
    
    if [[ -d "$API_DIR/storage/logs" ]]; then
        rm -f "$API_DIR/storage/logs"/*.log 2>/dev/null || true
        log_success "Cleaned storage logs"
    fi
    
    # Clean cache files
    cd "$PROJECT_ROOT"
    if check_command "pnpm" >/dev/null 2>&1; then
        exec_with_log "pnpm store prune" "Clean pnpm cache" >/dev/null 2>&1 || true
    fi
    
    cd "$API_DIR"
    if check_command "composer" >/dev/null 2>&1; then
        exec_with_log "composer clear-cache" "Clean composer cache" >/dev/null 2>&1 || true
    fi
    
    log_success "Development cleanup completed"
}

# Main function
main() {
    local command="${1:-status}"
    
    case "$command" in
        --help|-h)
            show_help
            exit 0
            ;;
        setup)
            show_header "Development Setup"
            setup_development
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        start)
            show_header "Start Development Servers"
            start_development_servers
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        stop)
            show_header "Stop Development Servers"
            stop_development_servers
            show_footer "success"
            exit 0
            ;;
        restart)
            show_header "Restart Development Servers"
            stop_development_servers
            echo
            start_development_servers
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        status)
            show_header "Development Server Status"
            show_server_status
            show_footer "success"
            exit 0
            ;;
        logs)
            show_header "Development Server Logs"
            show_server_logs
            ;;
        clean)
            show_header "Clean Development Environment"
            clean_development
            show_footer "success"
            exit 0
            ;;
        *)
            log_error "Invalid command: $command"
            echo "Use --help to see available commands"
            exit 1
            ;;
    esac
}

# Execute main function
main "$@"