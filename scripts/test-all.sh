#!/bin/bash
# Unifica todos los tests y validaciones del proyecto

echo "Ejecutando tests de React (Vitest)..."
npm run test -- --run || { echo "Tests de React fallaron"; exit 1; }

echo "Ejecutando tests de PHP (ProjectTest)..."
phpunit --configuration ./api/phpunit.xml || { echo "Tests de PHP fallaron"; exit 1; }

echo "Verificando sintaxis de PHP..."
find ./api -name "*.php" -exec php -l {} \; || { echo "Error de sintaxis en PHP"; exit 1; }

echo "Generando build de frontend..."
npm run build || { echo "Error en build de React"; exit 1; }
echo "Verificando build de frontend..."
if [ ! -f ./dist/index.html ]; then
  echo "No existe el build de frontend (dist/index.html)"
  exit 1
fi

echo "Todos los tests y validaciones pasaron correctamente."
