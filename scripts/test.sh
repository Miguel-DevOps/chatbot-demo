#!/bin/bash

# Script principal de testing para el proyecto
# Ejecuta todos los tests: frontend, backend unitarios, backend integración

set -e

# Configuración
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$PROJECT_ROOT/api"
SERVER_HOST="localhost"
SERVER_PORT="8080"
SERVER_PID_FILE="/tmp/chatbot_test_server.pid"
LOG_FILE="/tmp/chatbot_test_server.log"

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar ayuda
show_help() {
    echo "Uso: ./test.sh [OPCION]"
    echo ""
    echo "Opciones:"
    echo "  frontend     Ejecutar solo tests del frontend (Vitest)"
    echo "  backend      Ejecutar solo tests del backend (PHPUnit)"
    echo "  unit         Ejecutar solo tests unitarios del backend"
    echo "  integration  Ejecutar solo tests de integración del backend"
    echo "  all          Ejecutar todos los tests (default)"
    echo "  --help       Mostrar esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  ./test.sh              # Ejecutar todos los tests"
    echo "  ./test.sh frontend     # Solo tests de React"
    echo "  ./test.sh backend      # Solo tests de PHP"
    echo "  ./test.sh unit         # Solo tests unitarios PHP"
}

# Función para limpiar el servidor al salir
cleanup() {
    if [ -f "$SERVER_PID_FILE" ]; then
        SERVER_PID=$(cat "$SERVER_PID_FILE")
        if kill -0 "$SERVER_PID" 2>/dev/null; then
            echo -e "\n${YELLOW}🧹 Deteniendo servidor PHP...${NC}"
            kill "$SERVER_PID" 2>/dev/null || true
            wait "$SERVER_PID" 2>/dev/null || true
        fi
        rm -f "$SERVER_PID_FILE"
    fi
    rm -f "$LOG_FILE"
}

# Registrar cleanup
trap cleanup EXIT

# Función para ejecutar tests del frontend
run_frontend_tests() {
    echo -e "${BLUE}🚀 Ejecutando tests del frontend (Vitest)...${NC}"
    echo "=================================="
    cd "$PROJECT_ROOT"
    
    # Verificar dependencias
    if [ ! -d "node_modules" ]; then
        echo "Instalando dependencias de Node.js..."
        pnpm install
    fi
    
    # Ejecutar tests
    pnpm test || {
        echo -e "${RED}❌ Tests del frontend fallaron${NC}"
        return 1
    }
    
    echo -e "${GREEN}✅ Tests del frontend completados${NC}"
    return 0
}

# Función para iniciar servidor PHP
start_php_server() {
    echo -e "${BLUE}🌐 Iniciando servidor PHP en ${SERVER_HOST}:${SERVER_PORT}...${NC}"
    
    cd "$API_DIR"
    
    # Verificar dependencias
    if [ ! -d "vendor" ]; then
        echo "Instalando dependencias de Composer..."
        composer install
    fi
    
    # Verificar directorio public
    if [ ! -d "public" ]; then
        echo -e "${RED}❌ Error: Directorio 'public' no encontrado${NC}"
        return 1
    fi
    
    # Iniciar servidor
    cd public
    php -S "${SERVER_HOST}:${SERVER_PORT}" > "$LOG_FILE" 2>&1 &
    SERVER_PID=$!
    echo $SERVER_PID > "$SERVER_PID_FILE"
    
    # Esperar a que esté listo
    echo "Esperando a que el servidor esté listo..."
    for i in {1..10}; do
        if curl -s "http://${SERVER_HOST}:${SERVER_PORT}/health.php?plain=1" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ Servidor PHP iniciado correctamente${NC}"
            return 0
        fi
        if [ $i -eq 10 ]; then
            echo -e "${RED}❌ Error: El servidor no respondió después de 10 intentos${NC}"
            echo "Log del servidor:"
            cat "$LOG_FILE" || true
            return 1
        fi
        sleep 1
    done
}

# Función para ejecutar tests unitarios del backend
run_backend_unit_tests() {
    echo -e "${BLUE}🧪 Ejecutando tests unitarios del backend...${NC}"
    echo "=================================="
    
    cd "$API_DIR"
    
    # Verificar dependencias
    if [ ! -d "vendor" ]; then
        echo "Instalando dependencias de Composer..."
        composer install
    fi
    
    # Ejecutar tests unitarios
    ./vendor/bin/phpunit tests/Unit/ --verbose || {
        echo -e "${RED}❌ Tests unitarios del backend fallaron${NC}"
        return 1
    }
    
    echo -e "${GREEN}✅ Tests unitarios del backend completados${NC}"
    return 0
}

# Función para ejecutar tests de integración del backend
run_backend_integration_tests() {
    echo -e "${BLUE}🔗 Ejecutando tests de integración del backend...${NC}"
    echo "=================================="
    
    cd "$API_DIR"
    
    # Configurar variables de entorno
    export TEST_SERVER_URL="http://${SERVER_HOST}:${SERVER_PORT}"
    export CHATBOT_DEMO_ENV="testing"
    
    # Ejecutar tests de integración
    ./vendor/bin/phpunit tests/Integration/ --verbose || {
        echo -e "${RED}❌ Tests de integración del backend fallaron${NC}"
        return 1
    }
    
    echo -e "${GREEN}✅ Tests de integración del backend completados${NC}"
    return 0
}

# Función para ejecutar todos los tests del backend
run_backend_tests() {
    # Iniciar servidor para tests de integración
    start_php_server || return 1
    
    # Ejecutar tests unitarios
    run_backend_unit_tests || return 1
    
    # Ejecutar tests de integración
    run_backend_integration_tests || return 1
    
    return 0
}

# Función principal
main() {
    local command=${1:-all}
    
    case $command in
        --help|-h)
            show_help
            exit 0
            ;;
        frontend)
            run_frontend_tests
            exit $?
            ;;
        backend)
            run_backend_tests
            exit $?
            ;;
        unit)
            run_backend_unit_tests
            exit $?
            ;;
        integration)
            start_php_server && run_backend_integration_tests
            exit $?
            ;;
        all)
            echo -e "${BLUE}🚀 Ejecutando TODOS los tests del proyecto...${NC}"
            echo "=================================================="
            
            local frontend_result=0
            local backend_result=0
            
            # Tests del frontend
            run_frontend_tests || frontend_result=1
            echo ""
            
            # Tests del backend
            run_backend_tests || backend_result=1
            echo ""
            
            # Resumen final
            echo -e "${BLUE}📊 Resumen Final${NC}"
            echo "=================================="
            
            if [ $frontend_result -eq 0 ]; then
                echo -e "${GREEN}✅ Frontend: PASADO${NC}"
            else
                echo -e "${RED}❌ Frontend: FALLIDO${NC}"
            fi
            
            if [ $backend_result -eq 0 ]; then
                echo -e "${GREEN}✅ Backend: PASADO${NC}"
            else
                echo -e "${RED}❌ Backend: FALLIDO${NC}"
            fi
            
            if [ $frontend_result -eq 0 ] && [ $backend_result -eq 0 ]; then
                echo -e "\n${GREEN}🎉 ¡TODOS LOS TESTS PASARON!${NC}"
                exit 0
            else
                echo -e "\n${RED}💥 ALGUNOS TESTS FALLARON${NC}"
                exit 1
            fi
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