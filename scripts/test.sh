#!/bin/bash

# Unified testing script for the chatbot project
# Runs all types of tests: frontend, backend unit, backend integration

# Load common utilities
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# Configuration
SERVER_HOST="localhost"
SERVER_PORT="8080"
SERVER_PID_FILE="/tmp/chatbot_test_server.pid"
LOG_FILE="/tmp/chatbot_test_server.log"

# Show help information
show_help() {
    show_header "Test Script Help" "Testing options for the chatbot project"
    
    echo "USAGE:"
    echo "  ./test.sh [OPTION]"
    echo ""
    echo "OPTIONS:"
    echo "  frontend     Run only frontend tests (Vitest)"
    echo "  backend      Run only backend tests (PHPUnit)"
    echo "  unit         Run only backend unit tests"
    echo "  integration  Run only backend integration tests"
    echo "  contracts    Validate API contracts"
    echo "  all          Run all tests (default)"
    echo "  --help, -h   Show this help"
    echo ""
    echo "EXAMPLES:"
    echo "  ./test.sh              # Run all tests"
    echo "  ./test.sh frontend     # Frontend tests only"
    echo "  ./test.sh contracts    # API contract validation"
    echo ""
}

# Cleanup function for servers and processes
cleanup_test_environment() {
    if [[ -f "$SERVER_PID_FILE" ]]; then
        local server_pid
        server_pid=$(cat "$SERVER_PID_FILE")
        if kill -0 "$server_pid" 2>/dev/null; then
            log_info "Stopping test server (PID: $server_pid)"
            kill "$server_pid" 2>/dev/null || true
            wait "$server_pid" 2>/dev/null || true
        fi
        rm -f "$SERVER_PID_FILE"
    fi
    rm -f "$LOG_FILE"
}

# Register cleanup
register_cleanup cleanup_test_environment

# Run frontend tests
run_frontend_tests() {
    show_progress "Running frontend tests (Vitest)"
    
    cd "$PROJECT_ROOT"
    
    # Check dependencies
    check_command "pnpm" || return 1
    check_node_version 18 || return 1
    
    # Install dependencies if needed
    if [[ ! -d "node_modules" ]]; then
        exec_with_log "pnpm install" "Install frontend dependencies"
    fi
    
    # Run tests
    exec_with_log "pnpm test" "Execute Vitest tests" || return 1
    
    log_success "Frontend tests completed"
    return 0
}

# Start PHP development server
start_php_server() {
    show_progress "Starting PHP development server on ${SERVER_HOST}:${SERVER_PORT}"
    
    cd "$API_DIR"
    
    # Check dependencies
    check_command "php" || return 1
    check_php_version 8.1 || return 1
    
    # Install dependencies if needed
    if [[ ! -d "vendor" ]]; then
        exec_with_log "composer install" "Install PHP dependencies"
    fi
    
    # Verify public directory
    check_directory "public" "API public directory" || return 1
    
    # Start server
    cd public
    php -S "${SERVER_HOST}:${SERVER_PORT}" > "$LOG_FILE" 2>&1 &
    local server_pid=$!
    echo $server_pid > "$SERVER_PID_FILE"
    
    # Wait for server to be ready
    wait_for_service "http://${SERVER_HOST}:${SERVER_PORT}/health" 15 1 || {
        log_error "Server failed to start. Log output:"
        cat "$LOG_FILE" 2>/dev/null || true
        return 1
    }
    
    log_success "PHP server started (PID: $server_pid)"
    return 0
}

# Run backend unit tests
run_backend_unit_tests() {
    show_progress "Running backend unit tests"
    
    cd "$API_DIR"
    
    # Check dependencies
    check_command "composer" || return 1
    check_php_version 8.1 || return 1
    
    # Install dependencies if needed
    if [[ ! -d "vendor" ]]; then
        exec_with_log "composer install" "Install PHP dependencies"
    fi
    
    # Check if PHPUnit is available
    if [[ ! -f "vendor/bin/phpunit" ]]; then
        log_error "PHPUnit not found. Run 'composer install' with dev dependencies"
        return 1
    fi
    
    # Run unit tests
    exec_with_log "./vendor/bin/phpunit tests/Unit/ --verbose" "Execute PHPUnit unit tests" || return 1
    
    log_success "Backend unit tests completed"
    return 0
}

# Run backend integration tests
run_backend_integration_tests() {
    show_progress "Running backend integration tests"
    
    cd "$API_DIR"
    
    # Set environment variables for testing
    export TEST_SERVER_URL="http://${SERVER_HOST}:${SERVER_PORT}"
    export CHATBOT_DEMO_ENV="testing"
    
    # Run integration tests
    exec_with_log "./vendor/bin/phpunit tests/Integration/ --verbose" "Execute PHPUnit integration tests" || return 1
    
    log_success "Backend integration tests completed"
    return 0
}

# Run all backend tests
run_backend_tests() {
    show_progress "Running all backend tests"
    
    # Start server for integration tests
    start_php_server || return 1
    
    # Run unit tests
    run_backend_unit_tests || return 1
    
    # Run integration tests
    run_backend_integration_tests || return 1
    
    log_success "All backend tests completed"
    return 0
}

# Validate API contracts
validate_api_contracts() {
    show_progress "Validating API contracts"
    
    # Check if validate script exists
    local validate_script="$SCRIPT_DIR/validate-api-contracts.sh"
    check_file "$validate_script" "API validation script" || return 1
    
    # Make sure server is running
    if ! curl -s "http://${SERVER_HOST}:${SERVER_PORT}/health" > /dev/null 2>&1; then
        log_info "Starting server for contract validation..."
        start_php_server || return 1
    fi
    
    # Run validation
    exec_with_log "bash \"$validate_script\"" "Execute API contract validation" || return 1
    
    log_success "API contract validation completed"
    return 0
}

# Generate test summary
show_test_summary() {
    local frontend_result="$1"
    local backend_result="$2"
    local contracts_result="${3:-0}"
    
    log_info "Test Results Summary"
    echo "═══════════════════════════════════════"
    
    if [[ $frontend_result -eq 0 ]]; then
        log_success "Frontend: PASSED"
    else
        log_error "Frontend: FAILED"
    fi
    
    if [[ $backend_result -eq 0 ]]; then
        log_success "Backend: PASSED"
    else
        log_error "Backend: FAILED"
    fi
    
    if [[ $contracts_result -eq 0 ]]; then
        log_success "API Contracts: PASSED"
    else
        log_error "API Contracts: FAILED"
    fi
    
    echo
    local total_failures=$((frontend_result + backend_result + contracts_result))
    if [[ $total_failures -eq 0 ]]; then
        log_success "ALL TESTS PASSED!"
        return 0
    else
        log_error "SOME TESTS FAILED ($total_failures failed)"
        return 1
    fi
}

# Main function
main() {
    local command="${1:-all}"
    
    case "$command" in
        --help|-h)
            show_help
            exit 0
            ;;
        frontend)
            show_header "Frontend Tests"
            run_frontend_tests
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        backend)
            show_header "Backend Tests"
            run_backend_tests
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        unit)
            show_header "Backend Unit Tests"
            run_backend_unit_tests
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        integration)
            show_header "Backend Integration Tests"
            start_php_server && run_backend_integration_tests
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        contracts)
            show_header "API Contract Validation"
            validate_api_contracts
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        all)
            show_header "Complete Test Suite" "Running all tests for the project"
            
            local frontend_result=0
            local backend_result=0
            local contracts_result=0
            
            # Frontend tests
            if ! run_frontend_tests; then
                frontend_result=1
            fi
            echo
            
            # Backend tests
            if ! run_backend_tests; then
                backend_result=1
            fi
            echo
            
            # Contract validation
            if ! validate_api_contracts; then
                contracts_result=1
            fi
            echo
            
            # Show summary
            show_test_summary $frontend_result $backend_result $contracts_result
            local summary_result=$?
            show_footer "$([ $summary_result -eq 0 ] && echo "success" || echo "error")"
            exit $summary_result
            ;;
        *)
            log_error "Invalid option: $command"
            echo "Use --help to see available options"
            exit 1
            ;;
    esac
}

# Execute main function
main "$@"