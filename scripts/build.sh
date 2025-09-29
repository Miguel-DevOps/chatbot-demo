#!/bin/bash

# Script para build de producción del proyecto
# Genera los archivos finales listos para despliegue

set -e

# Configuración
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$PROJECT_ROOT/dist"
API_DIR="$PROJECT_ROOT/api"

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar ayuda
show_help() {
    echo "Uso: ./build.sh [OPCION]"
    echo ""
    echo "Opciones:"
    echo "  frontend     Construir solo el frontend"
    echo "  backend      Preparar solo el backend"
    echo "  wordpress    Generar plugin de WordPress"
    echo "  all          Construir todo (default)"
    echo "  clean        Limpiar archivos de build"
    echo "  --help       Mostrar esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  ./build.sh              # Build completo"
    echo "  ./build.sh frontend     # Solo frontend"
    echo "  ./build.sh clean        # Limpiar builds"
}

# Función para limpiar builds anteriores
clean_builds() {
    echo -e "${YELLOW}🧹 Limpiando builds anteriores...${NC}"
    
    # Limpiar frontend
    if [ -d "$DIST_DIR" ]; then
        rm -rf "$DIST_DIR"
        echo "  ✓ Directorio dist/ eliminado"
    fi
    
    # Limpiar cache de composer
    if [ -d "$API_DIR/vendor" ]; then
        cd "$API_DIR"
        composer clear-cache
        echo "  ✓ Cache de Composer limpiado"
    fi
    
    # Limpiar node_modules cache
    cd "$PROJECT_ROOT"
    if command -v pnpm >/dev/null 2>&1; then
        pnpm store prune
        echo "  ✓ Cache de pnpm limpiado"
    fi
    
    echo -e "${GREEN}✅ Limpieza completada${NC}"
}

# Función para construir el frontend
build_frontend() {
    echo -e "${BLUE}🚀 Construyendo frontend para producción...${NC}"
    echo "=================================="
    
    cd "$PROJECT_ROOT"
    
    # Verificar dependencias
    if [ ! -d "node_modules" ]; then
        echo "Instalando dependencias de Node.js..."
        pnpm install
    fi
    
    # Verificar variables de entorno
    if [ ! -f ".env" ]; then
        echo -e "${YELLOW}⚠️  Archivo .env no encontrado, usando configuración por defecto${NC}"
    fi
    
    # Build del frontend
    echo "Ejecutando build de Vite..."
    pnpm build || {
        echo -e "${RED}❌ Error en build del frontend${NC}"
        return 1
    }
    
    # Verificar que el build se creó correctamente
    if [ ! -f "$DIST_DIR/index.html" ]; then
        echo -e "${RED}❌ Error: No se generó dist/index.html${NC}"
        return 1
    fi
    
    # Mostrar estadísticas del build
    echo ""
    echo "📊 Estadísticas del build:"
    echo "  └─ Archivos generados:"
    find "$DIST_DIR" -type f -name "*.html" -o -name "*.js" -o -name "*.css" | while read file; do
        size=$(du -h "$file" | cut -f1)
        echo "     └─ $(basename "$file"): $size"
    done
    
    echo -e "${GREEN}✅ Frontend construido exitosamente${NC}"
    return 0
}

# Función para preparar el backend
build_backend() {
    echo -e "${BLUE}🔧 Preparando backend para producción...${NC}"
    echo "=================================="
    
    cd "$API_DIR"
    
    # Instalar dependencias sin dev
    echo "Instalando dependencias de producción..."
    composer install --no-dev --optimize-autoloader || {
        echo -e "${RED}❌ Error instalando dependencias del backend${NC}"
        return 1
    }
    
    # Verificar archivos esenciales
    essential_files=(
        "public/index.php"
        "src/Controllers/ChatController.php"
        "src/Controllers/HealthController.php"
        "src/Services/ChatService.php"
        "composer.json"
    )
    
    echo "Verificando archivos esenciales..."
    for file in "${essential_files[@]}"; do
        if [ ! -f "$file" ]; then
            echo -e "${RED}❌ Archivo esencial faltante: $file${NC}"
            return 1
        else
            echo "  ✓ $file"
        fi
    done
    
    # Verificar permisos
    if [ -d "src/data" ]; then
        chmod 755 src/data
        echo "  ✓ Permisos de directorio data configurados"
    fi
    
    if [ -d "logs" ]; then
        chmod 755 logs
        echo "  ✓ Permisos de directorio logs configurados"
    fi
    
    echo -e "${GREEN}✅ Backend preparado para producción${NC}"
    return 0
}

# Función para generar plugin de WordPress
build_wordpress_plugin() {
    echo -e "${BLUE}📦 Generando plugin de WordPress...${NC}"
    echo "=================================="
    
    local plugin_dir="$PROJECT_ROOT/wordpress-plugin"
    
    # Limpiar directorio anterior
    if [ -d "$plugin_dir" ]; then
        rm -rf "$plugin_dir"
    fi
    
    mkdir -p "$plugin_dir"
    
    # Construir frontend si no existe
    if [ ! -d "$DIST_DIR" ]; then
        build_frontend || return 1
    fi
    
    # Preparar backend si no está listo
    cd "$API_DIR"
    if [ ! -d "vendor" ] || [ -d "vendor/phpunit" ]; then
        echo "Preparando backend para plugin..."
        composer install --no-dev --optimize-autoloader
    fi
    
    # Copiar archivos
    echo "Copiando archivos al plugin..."
    cp -r "$API_DIR" "$plugin_dir/api"
    cp -r "$DIST_DIR" "$plugin_dir/frontend"
    
    # Crear archivo principal del plugin
    cat > "$plugin_dir/chatbot-demo.php" << 'EOF'
<?php
/**
 * Plugin Name: Chatbot Demo
 * Description: Chatbot inteligente con IA
 * Version: 1.0.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar autoloader
require_once plugin_dir_path(__FILE__) . 'api/vendor/autoload.php';

// Inicializar plugin
add_action('init', 'chatbot_demo_init');

function chatbot_demo_init() {
    // Registrar endpoints de la API
    add_action('wp_ajax_chatbot_message', 'chatbot_handle_message');
    add_action('wp_ajax_nopriv_chatbot_message', 'chatbot_handle_message');
    
    // Enqueue scripts
    add_action('wp_enqueue_scripts', 'chatbot_enqueue_scripts');
}

function chatbot_handle_message() {
    // Manejar mensajes del chatbot
    $message = sanitize_text_field($_POST['message']);
    
    // Aquí integrar con tu API
    wp_send_json_success(['response' => 'Respuesta del chatbot']);
}

function chatbot_enqueue_scripts() {
    wp_enqueue_script(
        'chatbot-demo',
        plugin_dir_url(__FILE__) . 'frontend/assets/index.js',
        [],
        '1.0.0',
        true
    );
    
    wp_enqueue_style(
        'chatbot-demo',
        plugin_dir_url(__FILE__) . 'frontend/assets/index.css',
        [],
        '1.0.0'
    );
}
EOF
    
    # Crear archivo README del plugin
    cat > "$plugin_dir/README.txt" << 'EOF'
=== Chatbot Demo ===
Contributors: tu-usuario
Tags: chatbot, ai, customer-service
Requires at least: 5.0
Tested up to: 6.3
Stable tag: 1.0.0
License: MIT

Chatbot inteligente con IA para tu sitio web.

== Description ==

Un chatbot moderno con inteligencia artificial que puede responder preguntas de tus usuarios.

== Installation ==

1. Sube el plugin a tu directorio `/wp-content/plugins/`
2. Activa el plugin desde el panel de WordPress
3. Configura las opciones en Ajustes > Chatbot Demo

== Changelog ==

= 1.0.0 =
* Versión inicial
EOF
    
    echo -e "${GREEN}✅ Plugin de WordPress generado en: $plugin_dir${NC}"
    return 0
}

# Función para mostrar resumen del build
show_build_summary() {
    echo ""
    echo -e "${BLUE}📊 Resumen del Build${NC}"
    echo "=================================="
    
    if [ -d "$DIST_DIR" ]; then
        echo -e "${GREEN}✅ Frontend:${NC} Listo en ./dist/"
        echo "  └─ index.html, CSS y JS generados"
    fi
    
    if [ -d "$API_DIR/vendor" ]; then
        echo -e "${GREEN}✅ Backend:${NC} Listo en ./api/"
        echo "  └─ Dependencias de producción instaladas"
    fi
    
    if [ -d "$PROJECT_ROOT/wordpress-plugin" ]; then
        echo -e "${GREEN}✅ WordPress Plugin:${NC} Listo en ./wordpress-plugin/"
        echo "  └─ Plugin completo para WordPress"
    fi
    
    echo ""
    echo -e "${GREEN}🚀 Proyecto listo para despliegue!${NC}"
}

# Función principal
main() {
    local command=${1:-all}
    
    case $command in
        --help|-h)
            show_help
            exit 0
            ;;
        clean)
            clean_builds
            exit 0
            ;;
        frontend)
            build_frontend
            exit $?
            ;;
        backend)
            build_backend
            exit $?
            ;;
        wordpress)
            build_wordpress_plugin
            exit $?
            ;;
        all)
            echo -e "${BLUE}🚀 Build completo del proyecto...${NC}"
            echo "=================================================="
            
            # Build frontend
            build_frontend || exit 1
            echo ""
            
            # Preparar backend
            build_backend || exit 1
            echo ""
            
            # Mostrar resumen
            show_build_summary
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Opción no válida: $command${NC}"
            echo "Usa --help para ver las opciones disponibles"
            exit 1
            ;;
    esac
}

# Ejecutar función principal
main "$@"