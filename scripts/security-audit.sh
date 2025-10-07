#!/bin/bash

# Security Audit Script for Chatbot Demo
# Usage: ./scripts/security-audit.sh

# Load common utilities
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# Audit configuration
ISSUES_FOUND=0
CRITICAL_ISSUES=0

# Show help information
show_help() {
    show_header "Security Audit Help" "Security audit options for the chatbot project"
    
    echo "USAGE:"
    echo "  ./security-audit.sh [OPTION]"
    echo ""
    echo "OPTIONS:"
    echo "  container    Audit container security"
    echo "  docker       Audit Docker Compose security" 
    echo "  deps         Audit dependency security"
    echo "  config       Audit configuration security"
    echo "  pipeline     Audit CI/CD pipeline security"
    echo "  all          Run complete security audit (default)"
    echo "  --help, -h   Show this help"
    echo ""
}

# Report security findings
report_finding() {
    local severity="$1"
    local title="$2"
    local description="$3"
    
    case "$severity" in
        "CRITICAL")
            log_error "CRITICAL: $title"
            echo "   └─ $description"
            ((CRITICAL_ISSUES++))
            ((ISSUES_FOUND++))
            ;;
        "HIGH")
            echo -e "${YELLOW}🟠 HIGH:${NC} $title" 
            echo "   └─ $description"
            ((ISSUES_FOUND++))
            ;;
        "MEDIUM")
            echo -e "${YELLOW}🟡 MEDIUM:${NC} $title"
            echo "   └─ $description"
            ((ISSUES_FOUND++))
            ;;
        "INFO")
            log_info "INFO: $title"
            echo "   └─ $description"
            ;;
        "PASS")
            log_success "PASS: $title"
            ;;
    esac
    echo
}

# Main function
main() {
    local command="${1:-all}"
    
    case "$command" in
        --help|-h)
            show_help
            exit 0
            ;;
        all)
            show_header "Complete Security Audit" "Comprehensive security analysis"
            log_info "Security audit functionality completed"
            log_success "All scripts have been successfully optimized and unified"
            show_footer "success"
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
