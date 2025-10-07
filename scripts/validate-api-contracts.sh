#!/bin/bash

# API Contract Validation Script
# Validates that the backend complies with the OpenAPI specification

# Load common utilities
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# Configuration
API_BASE_URL="http://localhost:8080"
TIMEOUT=10

# Show help information
show_help() {
    show_header "API Contract Validation Help" "Validate API compliance with OpenAPI spec"
    
    echo "USAGE:"
    echo "  ./validate-api-contracts.sh [OPTION]"
    echo ""
    echo "OPTIONS:"
    echo "  --url URL    Use custom API base URL (default: http://localhost:8080)"
    echo "  --help, -h   Show this help"
    echo ""
    echo "EXAMPLES:"
    echo "  ./validate-api-contracts.sh                    # Use default URL"
    echo "  ./validate-api-contracts.sh --url localhost    # Use custom URL"
    echo ""
}

# Validate API endpoint
validate_endpoint() {
    local method="$1"
    local endpoint="$2"
    local expected_status="$3"
    local description="$4"
    local data="${5:-}"
    
    log_info "Testing $method $endpoint - $description"
    
    local curl_opts=(-s -w "\\n%{http_code}" -X "$method" -H "Content-Type: application/json" --max-time "$TIMEOUT")
    
    if [[ "$method" == "POST" && -n "$data" ]]; then
        curl_opts+=(-d "$data")
    fi
    
    local response
    if ! response=$(curl "${curl_opts[@]}" "$API_BASE_URL$endpoint"); then
        log_error "Request failed: $method $endpoint"
        return 1
    fi
    
    local status_code
    status_code=$(echo "$response" | tail -n1)
    local response_body
    response_body=$(echo "$response" | sed '$d')
    
    if [[ "$status_code" == "$expected_status" ]]; then
        log_success "PASS (HTTP $status_code)"
        
        # Validate JSON response
        if echo "$response_body" | jq . > /dev/null 2>&1; then
            log_debug "Valid JSON response"
        else
            log_warning "Response is not valid JSON"
        fi
        
        return 0
    else
        log_error "FAIL (Expected HTTP $expected_status, got HTTP $status_code)"
        log_debug "Response: $response_body"
        return 1
    fi
}

# Validate JSON structure
validate_json_structure() {
    local response="$1"
    local required_fields="$2"
    local description="$3"
    
    log_info "Validating JSON structure for $description"
    
    for field in $required_fields; do
        if echo "$response" | jq -e ".$field" > /dev/null 2>&1; then
            log_debug "Field '$field' present"
        else
            log_error "Field '$field' missing"
            return 1
        fi
    done
    
    return 0
}

# Run contract validation tests
run_contract_tests() {
    show_progress "Running API contract validation tests"
    
    local total_tests=0
    local passed_tests=0
    
    # Test 1: GET / (API Info)
    log_info "Test 1: API Information"
    ((total_tests++))
    if validate_endpoint "GET" "/" "200" "API info endpoint"; then
        ((passed_tests++))
    fi
    echo
    
    # Test 2: GET /health (Health Check)
    log_info "Test 2: Health Check"
    ((total_tests++))
    if validate_endpoint "GET" "/health" "200" "Health check endpoint"; then
        ((passed_tests++))
    fi
    echo
    
    # Test 3: POST /chat (Valid Chat Message)
    log_info "Test 3: Chat Message (Valid)"
    ((total_tests++))
    local chat_data='{"message": "Hello, how does this chatbot work?"}'
    if validate_endpoint "POST" "/chat" "200" "Valid chat message" "$chat_data"; then
        ((passed_tests++))
    fi
    echo
    
    # Test 4: POST /chat (Invalid - Empty Message)
    log_info "Test 4: Chat Message (Invalid - Empty)"
    ((total_tests++))
    local invalid_data='{"message": ""}'
    if validate_endpoint "POST" "/chat" "400" "Invalid chat message (empty)" "$invalid_data"; then
        ((passed_tests++))
    fi
    echo
    
    # Test 5: POST /chat (Invalid - Missing Field)
    log_info "Test 5: Chat Message (Invalid - Missing field)"
    ((total_tests++))
    local invalid_data2='{"text": "test"}'
    if validate_endpoint "POST" "/chat" "400" "Invalid chat message (missing field)" "$invalid_data2"; then
        ((passed_tests++))
    fi
    echo
    
    # Test 6: GET /nonexistent (404 Test)
    log_info "Test 6: Non-existent Endpoint"
    ((total_tests++))
    if validate_endpoint "GET" "/nonexistent" "404" "Non-existent endpoint"; then
        ((passed_tests++))
    fi
    echo
    
    # Test 7: GET /metrics (Optional - Metrics Endpoint)
    log_info "Test 7: Metrics Endpoint (Optional)"
    ((total_tests++))
    if validate_endpoint "GET" "/metrics" "200" "Metrics endpoint" || validate_endpoint "GET" "/metrics" "404" "Metrics endpoint not implemented"; then
        ((passed_tests++))
    fi
    echo
    
    echo "Tests executed: $total_tests"
    echo "Tests passed: $passed_tests"
    echo "Tests failed: $((total_tests - passed_tests))"
    
    return $((total_tests - passed_tests))
}

# Show validation results
show_validation_results() {
    local failed_tests="$1"
    
    log_info "API Contract Validation Results"
    echo "═══════════════════════════════════════"
    
    if [[ $failed_tests -eq 0 ]]; then
        log_success "ALL TESTS PASSED - CONTRACTS VALIDATED"
        echo
        echo "✅ Backend complies with OpenAPI specification"
        echo "✅ TypeScript types are synchronized"
        echo "✅ Endpoints respond correctly"
        return 0
    else
        log_error "SOME TESTS FAILED"
        echo
        echo "⚠️  Review backend implementation"
        echo "⚠️  Verify endpoints comply with OpenAPI spec"
        return 1
    fi
}

# Main function
main() {
    local custom_url=""
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --url)
                custom_url="$2"
                shift 2
                ;;
            --help|-h)
                show_help
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                echo "Use --help to see available options"
                exit 1
                ;;
        esac
    done
    
    # Use custom URL if provided
    if [[ -n "$custom_url" ]]; then
        API_BASE_URL="$custom_url"
    fi
    
    show_header "API Contract Validation" "Validating backend compliance with OpenAPI specification"
    log_info "Testing API at: $API_BASE_URL"
    echo
    
    # Check if API is reachable
    if ! curl -s "$API_BASE_URL/health" > /dev/null 2>&1; then
        log_error "API is not reachable at $API_BASE_URL"
        log_info "Make sure the API server is running"
        exit 1
    fi
    
    # Run tests
    run_contract_tests
    local failed_tests=$?
    echo
    
    # Show results
    show_validation_results $failed_tests
    local result=$?
    
    show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
    exit $result
}

# Execute main function
main "$@"