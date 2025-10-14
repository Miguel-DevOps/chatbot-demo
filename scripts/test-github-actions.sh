#!/bin/bash

# Script para probar localmente el pipeline de GitHub Actions
# Este script simula exactamente lo que va a ejecutar GitHub Actions

set -e  # Exit on any error

PROJECT_ROOT=$(pwd)
FRONTEND_SUCCESS=0
BACKEND_SUCCESS=0
SECURITY_SUCCESS=0

echo "🚀 SIMULACIÓN COMPLETA DEL PIPELINE DE GITHUB ACTIONS"
echo "============================================================="
echo "Directorio: $PROJECT_ROOT"
echo "Fecha: $(date)"
echo ""

# ===========================================
# Frontend Tests & Quality
# ===========================================
echo "📦 [FRONTEND] Iniciando tests y validación de calidad..."
echo "--------------------------------------------------------"

echo "📥 Instalando dependencias con pnpm..."
if pnpm install --frozen-lockfile; then
    echo "✅ Dependencias instaladas correctamente"
else
    echo "❌ ERROR: Fallo en instalación de dependencias"
    exit 1
fi

echo "🔍 Ejecutando type check..."
if pnpm typecheck; then
    echo "✅ Type check pasado"
else
    echo "❌ ERROR: Type check falló"
    exit 1
fi

echo "🔍 Ejecutando lint..."
if pnpm lint; then
    echo "✅ Lint pasado (warnings son aceptables)"
else
    echo "❌ ERROR: Lint falló"
    exit 1
fi

echo "🧪 Ejecutando tests del frontend..."
if pnpm test --run; then
    echo "✅ Tests del frontend pasados"
else
    echo "❌ ERROR: Tests del frontend fallaron"
    exit 1
fi

echo "🏗️  Construyendo proyecto..."
if pnpm build; then
    echo "✅ Build completado exitosamente"
    FRONTEND_SUCCESS=1
else
    echo "❌ ERROR: Build falló"
    exit 1
fi

echo ""
echo "✅ [FRONTEND] Pipeline completado exitosamente!"
echo ""

# ===========================================
# Backend Tests & Quality (In Containers)
# ===========================================
echo "🐳 [BACKEND] Iniciando tests containerizados..."
echo "-----------------------------------------------"

# Limpiar containers anteriores
echo "🧹 Limpiando containers anteriores..."
docker-compose -f docker-compose.test.yml down -v 2>/dev/null || true

# Crear directorios necesarios
echo "📁 Preparando directorios..."
mkdir -p api/storage/logs api/tests/results
chmod -R 777 api/storage api/tests/results

# Crear archivo de entorno de prueba
echo "⚙️  Creando archivo de entorno..."
cp api/.env.example api/.env
echo "APP_ENV=testing" >> api/.env
echo "LOG_LEVEL=debug" >> api/.env

echo "🐳 Iniciando containers..."
if docker-compose -f docker-compose.test.yml up -d api redis; then
    echo "✅ Containers iniciados"
else
    echo "❌ ERROR: Fallo al iniciar containers"
    exit 1
fi

echo "⏳ Esperando a que los containers estén listos..."
sleep 5  # Espera inicial
for i in {1..30}; do
    if docker-compose -f docker-compose.test.yml exec -T api php --version >/dev/null 2>&1; then
        echo "✅ API container listo"
        break
    fi
    echo "  Esperando API container... ($i/30)"
    sleep 2
done

for i in {1..30}; do
    if docker-compose -f docker-compose.test.yml exec -T redis redis-cli ping >/dev/null 2>&1; then
        echo "✅ Redis container listo"
        break
    fi
    echo "  Esperando Redis container... ($i/30)"
    sleep 2
done
echo "✅ Todos los containers listos"

echo "� Verificando environment del container..."
docker-compose -f docker-compose.test.yml exec -T api php --version
docker-compose -f docker-compose.test.yml exec -T api composer --version
echo "✅ Container environment verificado"

echo "�📦 Instalando dependencias en container..."
if docker-compose -f docker-compose.test.yml exec -T api composer install --dev --prefer-dist --no-progress --no-interaction; then
    echo "✅ Dependencias instaladas"
else
    echo "❌ ERROR: Fallo en instalación de dependencias backend"
    docker-compose -f docker-compose.test.yml down
    exit 1
fi

echo "🔍 Validando composer.json..."
if docker-compose -f docker-compose.test.yml exec -T api composer validate --strict; then
    echo "✅ Composer validado"
else
    echo "❌ ERROR: composer.json inválido"
    docker-compose -f docker-compose.test.yml down
    exit 1
fi

echo "🔧 Configurando entorno de test en container..."
docker-compose -f docker-compose.test.yml exec -T api mkdir -p tests/results
docker-compose -f docker-compose.test.yml exec -T api mkdir -p storage/logs 2>/dev/null || echo "  ⚠️  storage/logs ya existe o se usará el volume"
docker-compose -f docker-compose.test.yml exec -T api chmod -R 777 storage/logs 2>/dev/null || echo "  ⚠️  chmod falló, usando permisos por defecto"
echo "✅ Entorno de test configurado"

echo "🧪 Ejecutando Unit Tests..."
if docker-compose -f docker-compose.test.yml exec -T api ./vendor/bin/phpunit tests/Unit/ --coverage-clover=coverage.xml --log-junit=tests/results/junit.xml; then
    echo "✅ Unit tests pasados"
else
    echo "❌ ERROR: Unit tests fallaron"
    docker-compose -f docker-compose.test.yml logs api
    docker-compose -f docker-compose.test.yml down
    exit 1
fi

echo "🧪 Ejecutando Integration Tests..."
if docker-compose -f docker-compose.test.yml exec -T api ./vendor/bin/phpunit tests/Integration/ --log-junit=tests/results/integration-junit.xml; then
    echo "✅ Integration tests pasados"
    BACKEND_SUCCESS=1
else
    echo "❌ ERROR: Integration tests fallaron"
    docker-compose -f docker-compose.test.yml logs api
    docker-compose -f docker-compose.test.yml down
    exit 1
fi

echo "📋 Copiando resultados de tests desde el container..."
docker cp $(docker-compose -f docker-compose.test.yml ps -q api):/var/www/html/tests/results/ ./api/tests/results/ 2>/dev/null || echo "⚠️  No se pudieron copiar algunos archivos de resultados"
docker cp $(docker-compose -f docker-compose.test.yml ps -q api):/var/www/html/coverage.xml ./api/coverage.xml 2>/dev/null || echo "⚠️  Coverage file no encontrado"
echo "✅ Resultados copiados"

echo "🧹 Limpiando containers..."
docker-compose -f docker-compose.test.yml down

echo ""
echo "✅ [BACKEND] Pipeline completado exitosamente!"
echo ""

# ===========================================
# Security Scanning
# ===========================================
echo "🔒 [SECURITY] Iniciando escaneo de seguridad..."
echo "----------------------------------------------"

echo "🔍 Ejecutando pnpm security audit..."
if pnpm audit --audit-level critical; then
    echo "✅ pnpm audit pasado - sin vulnerabilidades críticas"
else
    echo "❌ ERROR: pnpm audit encontró vulnerabilidades críticas"
    exit 1
fi

echo "🔍 Ejecutando Composer security audit..."
cd api
echo "🔍 Running Composer security audit..."

# Run composer audit and check the JSON output (matching GitHub Actions logic)
if composer audit --format=json > composer-audit.json; then
    # Check if there are actual advisories in the JSON
    if grep -q '"advisories":\s*\[.*[^][]\]' composer-audit.json; then
        echo "❌ CRITICAL: Composer audit found security vulnerabilities!"
        cat composer-audit.json
        cd ..
        exit 1
    else
        echo "✅ Composer audit passed - no security vulnerabilities found"
        SECURITY_SUCCESS=1
    fi
else
    echo "❌ ERROR: Composer audit command failed"
    cd ..
    exit 1
fi
cd ..

echo ""
echo "✅ [SECURITY] Escaneo completado exitosamente!"
echo ""

# ===========================================
# Resumen Final
# ===========================================
echo "🎯 RESUMEN FINAL DEL PIPELINE"
echo "============================="
echo "Frontend Pipeline: $([ $FRONTEND_SUCCESS -eq 1 ] && echo "✅ SUCCESS" || echo "❌ FAILED")"
echo "Backend Pipeline:  $([ $BACKEND_SUCCESS -eq 1 ] && echo "✅ SUCCESS" || echo "❌ FAILED")"
echo "Security Scan:     $([ $SECURITY_SUCCESS -eq 1 ] && echo "✅ SUCCESS" || echo "❌ FAILED")"
echo ""

if [ $FRONTEND_SUCCESS -eq 1 ] && [ $BACKEND_SUCCESS -eq 1 ] && [ $SECURITY_SUCCESS -eq 1 ]; then
    echo "🎉 ¡PIPELINE COMPLETAMENTE EXITOSO!"
    echo "✅ Tu código está listo para GitHub Actions"
    echo "🚀 Puedes hacer commit y push con confianza"
    exit 0
else
    echo "❌ PIPELINE FALLÓ"
    echo "🔧 Revisa los errores antes de hacer commit"
    exit 1
fi