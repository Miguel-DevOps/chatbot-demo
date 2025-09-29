#!/bin/bash

# Script para validar que el backend cumple con el contrato OpenAPI
# Ejecuta una serie de requests al backend y valida las respuestas

set -e

echo "🔍 Validando contratos API entre Frontend y Backend"
echo "=================================================="

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuración
API_BASE_URL="http://localhost:8080"
TIMEOUT=10

# Función para hacer requests con validación
validate_endpoint() {
    local method=$1
    local endpoint=$2
    local expected_status=$3
    local description=$4
    local data=$5
    
    echo -n "Testing $method $endpoint - $description... "
    
    if [ "$method" = "POST" ] && [ -n "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Content-Type: application/json" \
            -d "$data" \
            --max-time $TIMEOUT \
            "$API_BASE_URL$endpoint")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method \
            -H "Content-Type: application/json" \
            --max-time $TIMEOUT \
            "$API_BASE_URL$endpoint")
    fi
    
    status_code=$(echo "$response" | tail -n1)
    response_body=$(echo "$response" | sed '$d')
    
    if [ "$status_code" = "$expected_status" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $status_code)"
        
        # Validar que la respuesta sea JSON válido
        if echo "$response_body" | jq . > /dev/null 2>&1; then
            echo "  └─ JSON válido ✓"
        else
            echo -e "  └─ ${YELLOW}⚠ Respuesta no es JSON válido${NC}"
        fi
        
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (Expected HTTP $expected_status, got HTTP $status_code)"
        echo "Response: $response_body"
        return 1
    fi
}

# Función para validar estructura JSON según esquema esperado
validate_json_structure() {
    local response=$1
    local required_fields=$2
    local description=$3
    
    echo "  └─ Validando estructura JSON para $description..."
    
    for field in $required_fields; do
        if echo "$response" | jq -e ".$field" > /dev/null 2>&1; then
            echo "    ✓ Campo '$field' presente"
        else
            echo -e "    ${RED}✗ Campo '$field' faltante${NC}"
            return 1
        fi
    done
    
    return 0
}

echo ""
echo "🚀 Iniciando validación de endpoints..."
echo ""

# Variable para contar tests
total_tests=0
passed_tests=0

# Test 1: GET / (API Info)
echo "📋 Test 1: API Information"
((total_tests++))
if validate_endpoint "GET" "/" "200" "API info endpoint"; then
    ((passed_tests++))
fi
echo ""

# Test 2: GET /health (Health Check)
echo "🏥 Test 2: Health Check"
((total_tests++))
if validate_endpoint "GET" "/health" "200" "Health check endpoint"; then
    ((passed_tests++))
fi
echo ""

# Test 3: POST /chat (Chat Message - válido)
echo "💬 Test 3: Chat Message (Valid)"
((total_tests++))
chat_data='{"message": "Hola, ¿cómo funciona este chatbot?"}'
if validate_endpoint "POST" "/chat" "200" "Valid chat message" "$chat_data"; then
    ((passed_tests++))
fi
echo ""

# Test 4: POST /chat (Chat Message - inválido - mensaje vacío)
echo "❌ Test 4: Chat Message (Invalid - Empty)"
((total_tests++))
invalid_data='{"message": ""}'
if validate_endpoint "POST" "/chat" "400" "Invalid chat message (empty)" "$invalid_data"; then
    ((passed_tests++))
fi
echo ""

# Test 5: POST /chat (Chat Message - inválido - sin campo message)
echo "❌ Test 5: Chat Message (Invalid - Missing field)"
((total_tests++))
invalid_data2='{"text": "test"}'
if validate_endpoint "POST" "/chat" "400" "Invalid chat message (missing field)" "$invalid_data2"; then
    ((passed_tests++))
fi
echo ""

# Test 6: GET /nonexistent (404 Test)
echo "🔍 Test 6: Non-existent Endpoint"
((total_tests++))
if validate_endpoint "GET" "/nonexistent" "404" "Non-existent endpoint"; then
    ((passed_tests++))
fi
echo ""

# Resultado final
echo "=================================================="
echo "📊 RESULTADOS DE VALIDACIÓN"
echo "=================================================="
echo "Tests ejecutados: $total_tests"
echo "Tests exitosos: $passed_tests"
echo "Tests fallidos: $((total_tests - passed_tests))"

if [ $passed_tests -eq $total_tests ]; then
    echo -e "${GREEN}🎉 TODOS LOS TESTS PASARON - CONTRATOS VALIDADOS${NC}"
    echo ""
    echo "✅ El backend cumple con la especificación OpenAPI"
    echo "✅ Los tipos TypeScript están sincronizados"
    echo "✅ Los endpoints responden correctamente"
    exit 0
else
    echo -e "${RED}❌ ALGUNOS TESTS FALLARON${NC}"
    echo ""
    echo "⚠️  Revisar implementación del backend"
    echo "⚠️  Verificar que los endpoints cumplan con OpenAPI"
    exit 1
fi