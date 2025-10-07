#!/bin/bash

# Common utilities for all scripts
# Source this file to use shared functions

# Standard error handling
set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Project paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
API_DIR="$PROJECT_ROOT/api"
DIST_DIR="$PROJECT_ROOT/dist"

# Logging functions
log_info() {
    echo -e "${BLUE}ℹ️  INFO:${NC} $1"
}

log_success() {
    echo -e "${GREEN}✅ SUCCESS:${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠️  WARNING:${NC} $1"
}

log_error() {
    echo -e "${RED}❌ ERROR:${NC} $1"
}

log_debug() {
    if [[ "${DEBUG:-}" == "true" ]]; then
        echo -e "${PURPLE}🐛 DEBUG:${NC} $1"
    fi
}

# Progress indication
show_progress() {
    local message="$1"
    echo -e "${CYAN}🚀 ${message}...${NC}"
}

# Check if command exists
check_command() {
    local cmd="$1"
    local package="${2:-$cmd}"
    
    if ! command -v "$cmd" >/dev/null 2>&1; then
        log_error "'$cmd' is required but not installed. Please install $package"
        return 1
    fi
    log_debug "Command '$cmd' is available"
    return 0
}

# Check if file exists
check_file() {
    local file="$1"
    local description="${2:-$file}"
    
    if [[ ! -f "$file" ]]; then
        log_error "Required file not found: $description ($file)"
        return 1
    fi
    log_debug "File exists: $file"
    return 0
}

# Check if directory exists
check_directory() {
    local dir="$1"
    local description="${2:-$dir}"
    
    if [[ ! -d "$dir" ]]; then
        log_error "Required directory not found: $description ($dir)"
        return 1
    fi
    log_debug "Directory exists: $dir"
    return 0
}

# Wait for service to be ready
wait_for_service() {
    local url="$1"
    local max_attempts="${2:-30}"
    local sleep_time="${3:-2}"
    
    log_info "Waiting for service at $url to be ready..."
    
    for i in $(seq 1 "$max_attempts"); do
        if curl -s "$url" > /dev/null 2>&1; then
            log_success "Service is ready at $url"
            return 0
        fi
        
        if [[ $i -eq "$max_attempts" ]]; then
            log_error "Service at $url not ready after $max_attempts attempts"
            return 1
        fi
        
        log_debug "Attempt $i/$max_attempts failed, waiting ${sleep_time}s..."
        sleep "$sleep_time"
    done
}

# Execute command with logging
exec_with_log() {
    local cmd="$1"
    local description="${2:-$cmd}"
    
    log_info "Executing: $description"
    log_debug "Command: $cmd"
    
    if eval "$cmd"; then
        log_success "Command completed: $description"
        return 0
    else
        log_error "Command failed: $description"
        return 1
    fi
}

# Cleanup function
register_cleanup() {
    local cleanup_func="$1"
    trap "$cleanup_func" EXIT INT TERM
}

# Check PHP version
check_php_version() {
    local required_version="${1:-8.1}"
    
    if ! check_command "php"; then
        return 1
    fi
    
    local php_version
    php_version=$(php -r "echo PHP_VERSION;" | cut -d. -f1,2)
    
    if command -v bc >/dev/null 2>&1; then
        if [[ $(echo "$php_version >= $required_version" | bc) -eq 1 ]]; then
            log_success "PHP $php_version detected (required: $required_version+)"
            return 0
        fi
    else
        # Fallback comparison without bc
        if [[ "$php_version" == "$required_version" ]] || [[ "$php_version" > "$required_version" ]]; then
            log_success "PHP $php_version detected (required: $required_version+)"
            return 0
        fi
    fi
    
    log_error "PHP $required_version+ required, found: $php_version"
    return 1
}

# Check Node.js version
check_node_version() {
    local required_version="${1:-18}"
    
    if ! check_command "node" "Node.js"; then
        return 1
    fi
    
    local node_version
    node_version=$(node -v | sed 's/v//' | cut -d. -f1)
    
    if [[ "$node_version" -ge "$required_version" ]]; then
        log_success "Node.js v$node_version detected (required: v$required_version+)"
        return 0
    fi
    
    log_error "Node.js v$required_version+ required, found: v$node_version"
    return 1
}

# Generate timestamp
get_timestamp() {
    date "+%Y-%m-%d %H:%M:%S"
}

# Create backup
create_backup() {
    local source="$1"
    local backup_dir="${2:-$PROJECT_ROOT/backups}"
    local timestamp
    timestamp=$(date "+%Y%m%d_%H%M%S")
    
    mkdir -p "$backup_dir"
    local backup_name="$(basename "$source")_$timestamp"
    
    if [[ -d "$source" ]]; then
        cp -r "$source" "$backup_dir/$backup_name"
    else
        cp "$source" "$backup_dir/$backup_name"
    fi
    
    log_success "Backup created: $backup_dir/$backup_name"
    echo "$backup_dir/$backup_name"
}

# Show script header
show_header() {
    local title="$1"
    local description="${2:-}"
    
    echo
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  $title${NC}"
    if [[ -n "$description" ]]; then
        echo -e "${BLUE}  $description${NC}"
    fi
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  Started: $(get_timestamp)${NC}"
    echo -e "${BLUE}  Project: $(basename "$PROJECT_ROOT")${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    echo
}

# Show script footer
show_footer() {
    local status="$1"
    
    echo
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    if [[ "$status" == "success" ]]; then
        echo -e "${GREEN}  ✅ COMPLETED SUCCESSFULLY${NC}"
    else
        echo -e "${RED}  ❌ COMPLETED WITH ERRORS${NC}"
    fi
    echo -e "${BLUE}  Finished: $(get_timestamp)${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
    echo
}