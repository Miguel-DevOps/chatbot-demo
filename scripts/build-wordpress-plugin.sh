#!/bin/bash
# Empaqueta el chatbot para integración en WordPress
npm run build
cp -r ./api ./wordpress-plugin/api
cp -r ./dist ./wordpress-plugin/frontend
# Puedes añadir más pasos según la estructura del plugin
