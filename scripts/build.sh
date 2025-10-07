#!/bin/bash

# Main build script for the chatbot project
# Generates production-ready files for deployment

# Load common utilities
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# Show help information
show_help() {
    show_header "Build Script Help" "Build options for the chatbot project"
    
    echo "USAGE:"
    echo "  ./build.sh [OPTION]"
    echo ""
    echo "OPTIONS:"
    echo "  frontend     Build only the React frontend"
    echo "  backend      Prepare only the PHP backend"
    echo "  wordpress    Generate WordPress plugin"
    echo "  all          Build everything (default)"
    echo "  clean        Clean build artifacts"
    echo "  --help, -h   Show this help"
    echo ""
    echo "EXAMPLES:"
    echo "  ./build.sh              # Complete build"
    echo "  ./build.sh frontend     # Frontend only"
    echo "  ./build.sh clean        # Clean builds"
    echo ""
}

# Clean previous builds
clean_builds() {
    show_progress "Cleaning previous builds"
    
    # Clean frontend
    if [[ -d "$DIST_DIR" ]]; then
        rm -rf "$DIST_DIR"
        log_success "Removed dist/ directory"
    fi
    
    # Clean composer cache
    if [[ -d "$API_DIR/vendor" ]]; then
        cd "$API_DIR"
        exec_with_log "composer clear-cache" "Clear Composer cache"
    fi
    
    # Clean pnpm cache
    cd "$PROJECT_ROOT"
    if check_command "pnpm" >/dev/null 2>&1; then
        exec_with_log "pnpm store prune" "Clean pnpm cache"
    fi
    
    log_success "Cleanup completed"
}

# Build frontend for production
build_frontend() {
    show_progress "Building frontend for production"
    
    cd "$PROJECT_ROOT"
    
    # Check dependencies
    check_command "pnpm" || return 1
    check_node_version 18 || return 1
    
    # Install dependencies if needed
    if [[ ! -d "node_modules" ]]; then
        exec_with_log "pnpm install" "Install Node.js dependencies"
    fi
    
    # Check environment file
    if [[ ! -f ".env" ]]; then
        log_warning ".env file not found, using default configuration"
    fi
    
    # Build the frontend
    exec_with_log "pnpm build" "Build React frontend" || return 1
    
    # Verify build output
    check_file "$DIST_DIR/index.html" "Build output index.html" || return 1
    
    # Show build statistics
    log_info "Build statistics:"
    find "$DIST_DIR" -type f \( -name "*.html" -o -name "*.js" -o -name "*.css" \) | while read -r file; do
        size=$(du -h "$file" | cut -f1)
        log_info "  $(basename "$file"): $size"
    done
    
    log_success "Frontend build completed"
    return 0
}

# Prepare backend for production
build_backend() {
    show_progress "Preparing backend for production"
    
    cd "$API_DIR"
    
    # Check dependencies
    check_command "composer" || return 1
    check_php_version 8.1 || return 1
    
    # Install production dependencies
    exec_with_log "composer install --no-dev --optimize-autoloader --no-scripts" "Install production dependencies" || return 1
    
    # Verify essential files
    local essential_files=(
        "public/index.php"
        "src/Controllers/ChatController.php"
        "src/Controllers/HealthController.php"
        "src/Services/ChatService.php"
        "composer.json"
    )
    
    log_info "Verifying essential files..."
    for file in "${essential_files[@]}"; do
        check_file "$file" || return 1
    done
    
    # Set proper permissions
    log_info "Setting production permissions..."
    find . -type f -exec chmod 644 {} \;
    find . -type d -exec chmod 755 {} \;
    
    # Create and set permissions for data directories
    for dir in "storage/logs" "logs" "data"; do
        if [[ ! -d "$dir" ]]; then
            mkdir -p "$dir"
        fi
        chmod 775 "$dir"
        log_debug "Directory $dir ready with 775 permissions"
    done
    
    log_success "Backend preparation completed"
    return 0
}

# Generate WordPress plugin
build_wordpress_plugin() {
    show_progress "Generating WordPress plugin"
    
    local plugin_dir="$PROJECT_ROOT/wordpress-plugin"
    
    # Clean previous plugin
    if [[ -d "$plugin_dir" ]]; then
        rm -rf "$plugin_dir"
    fi
    mkdir -p "$plugin_dir"
    
    # Ensure frontend is built
    if [[ ! -d "$DIST_DIR" ]]; then
        log_info "Frontend not built, building now..."
        build_frontend || return 1
    fi
    
    # Ensure backend is ready
    cd "$API_DIR"
    if [[ ! -d "vendor" ]] || [[ -d "vendor/phpunit" ]]; then
        log_info "Backend not ready for production, preparing now..."
        exec_with_log "composer install --no-dev --optimize-autoloader" "Prepare backend for plugin"
    fi
    
    # Copy files
    log_info "Copying files to plugin..."
    cp -r "$API_DIR" "$plugin_dir/api"
    cp -r "$DIST_DIR" "$plugin_dir/frontend"
    
    # Generate main plugin file
    cat > "$plugin_dir/chatbot-demo.php" << 'EOF'
<?php
/**
 * Plugin Name: Chatbot Demo
 * Description: Intelligent AI-powered chatbot for your website
 * Version: 1.0.0
 * Author: Miguel-DevOps
 * License: MIT
 * Text Domain: chatbot-demo
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CHATBOT_DEMO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHATBOT_DEMO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load autoloader
require_once CHATBOT_DEMO_PLUGIN_DIR . 'api/vendor/autoload.php';

// Initialize plugin
add_action('init', 'chatbot_demo_init');

function chatbot_demo_init() {
    // Register AJAX endpoints
    add_action('wp_ajax_chatbot_message', 'chatbot_handle_message');
    add_action('wp_ajax_nopriv_chatbot_message', 'chatbot_handle_message');
    
    // Enqueue assets
    add_action('wp_enqueue_scripts', 'chatbot_enqueue_scripts');
    
    // Add admin menu
    add_action('admin_menu', 'chatbot_admin_menu');
}

function chatbot_handle_message() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'chatbot_nonce')) {
        wp_die('Security check failed');
    }
    
    $message = sanitize_text_field($_POST['message']);
    
    // Here you would integrate with your ChatService
    // For now, return a simple response
    wp_send_json_success([
        'response' => 'Hello! This is a WordPress chatbot response to: ' . $message
    ]);
}

function chatbot_enqueue_scripts() {
    wp_enqueue_script(
        'chatbot-demo',
        CHATBOT_DEMO_PLUGIN_URL . 'frontend/assets/index.js',
        [],
        '1.0.0',
        true
    );
    
    wp_enqueue_style(
        'chatbot-demo',
        CHATBOT_DEMO_PLUGIN_URL . 'frontend/assets/index.css',
        [],
        '1.0.0'
    );
    
    // Localize script for AJAX
    wp_localize_script('chatbot-demo', 'chatbot_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('chatbot_nonce')
    ]);
}

function chatbot_admin_menu() {
    add_options_page(
        'Chatbot Demo Settings',
        'Chatbot Demo',
        'manage_options',
        'chatbot-demo',
        'chatbot_admin_page'
    );
}

function chatbot_admin_page() {
    echo '<div class="wrap">';
    echo '<h1>Chatbot Demo Settings</h1>';
    echo '<p>Configure your AI chatbot settings here.</p>';
    echo '</div>';
}
EOF
    
    # Generate plugin README
    cat > "$plugin_dir/README.txt" << 'EOF'
=== Chatbot Demo ===
Contributors: miguel-devops
Tags: chatbot, ai, customer-service, automation
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Modern AI-powered chatbot for your WordPress website.

== Description ==

Chatbot Demo is a modern, AI-powered chatbot that can be easily integrated into any WordPress website. It provides intelligent responses to user queries and can be customized to match your brand.

Features:
* AI-powered responses
* Easy integration
* Customizable appearance
* Secure and performant
* Mobile-friendly

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/chatbot-demo/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin in Settings > Chatbot Demo
4. Add your API keys and customize settings

== Frequently Asked Questions ==

= How do I configure the chatbot? =

Go to Settings > Chatbot Demo in your WordPress admin to configure the plugin.

= Is this plugin secure? =

Yes, the plugin follows WordPress security best practices and includes proper sanitization and nonce verification.

== Changelog ==

= 1.0.0 =
* Initial release
* AI-powered chatbot functionality
* WordPress integration
EOF
    
    log_success "WordPress plugin generated: $plugin_dir"
    return 0
}

# Show build summary
show_build_summary() {
    log_info "Build Summary"
    echo "═══════════════════════════════════════"
    
    if [[ -d "$DIST_DIR" ]]; then
        log_success "Frontend: Ready in ./dist/"
        echo "  └─ index.html, CSS and JS generated"
    fi
    
    if [[ -d "$API_DIR/vendor" ]]; then
        log_success "Backend: Ready in ./api/"
        echo "  └─ Production dependencies installed"
    fi
    
    if [[ -d "$PROJECT_ROOT/wordpress-plugin" ]]; then
        log_success "WordPress Plugin: Ready in ./wordpress-plugin/"
        echo "  └─ Complete plugin package created"
    fi
    
    echo
    log_success "Project ready for deployment!"
}

# Main function
main() {
    local command="${1:-all}"
    
    case "$command" in
        --help|-h)
            show_help
            exit 0
            ;;
        clean)
            show_header "Clean Build Artifacts"
            clean_builds
            show_footer "success"
            exit 0
            ;;
        frontend)
            show_header "Frontend Build"
            build_frontend
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        backend)
            show_header "Backend Build"
            build_backend
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        wordpress)
            show_header "WordPress Plugin Build"
            build_wordpress_plugin
            local result=$?
            show_footer "$([ $result -eq 0 ] && echo "success" || echo "error")"
            exit $result
            ;;
        all)
            show_header "Complete Project Build" "Building frontend, backend, and generating artifacts"
            
            local has_errors=0
            
            # Build frontend
            if ! build_frontend; then
                has_errors=1
            fi
            echo
            
            # Build backend
            if ! build_backend; then
                has_errors=1
            fi
            echo
            
            # Show summary
            if [[ $has_errors -eq 0 ]]; then
                show_build_summary
                show_footer "success"
                exit 0
            else
                log_error "Build completed with errors"
                show_footer "error"
                exit 1
            fi
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