#!/bin/bash
#===============================================================================
# DATAPOLIS PRO v2.5 - Script de Instalación Mejorado
# Sistema de Gestión Integral para Condominios
# © 2025 DATAPOLIS SpA
#===============================================================================

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Variables globales
INSTALL_DIR="/var/www/datapolis"
LOG_FILE="/tmp/datapolis_install.log"
PHP_VERSION="8.3"
NODE_VERSION="20"

# Funciones de utilidad
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${CYAN}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

# ... rest of script unchanged ...

show_completion() {
    echo ""
    echo -e "${BLUE}==============================================================${NC}"
    echo -e "${GREEN}           ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE              ${NC}"
    echo -e "${BLUE}==============================================================${NC}"
    echo ""
    echo -e "${YELLOW}Acceso al sistema:${NC}"

    if [ "$INSTALL_TYPE" = "1" ] && [ "$INSTALL_SSL" = "s" ]; then
        echo -e "  URL: ${GREEN}https://${SERVER_NAME}${NC}"
    else
        echo -e "  URL: ${GREEN}http://${SERVER_NAME}${NC}"
    fi

    echo ""
    echo -e "${YELLOW}Credenciales por defecto:${NC}"
    echo -e "  Email:    ${GREEN}admin@datapolis.cl${NC}"
    echo -e "  Password: ${GREEN}DataPolis2025!${NC}"
    echo ""
    echo -e "${RED}⚠️  IMPORTANTE: Cambie la contraseña después del primer login${NC}"
    echo ""

    if [ "$INSTALL_TYPE" = "1" ]; then
        echo -e "${YELLOW}Servicios:${NC}"
        echo "  Backend: systemctl status php${PHP_VERSION}-fpm"
        echo "  Queue: systemctl status datapolis-queue"
        echo "  Nginx: systemctl status nginx"
        echo "  MySQL: systemctl status mysql"
        echo "  Redis: systemctl status redis-server"
        echo ""
    fi

    echo -e "${YELLOW}Logs:${NC}"
    echo "  Laravel: tail -f ${INSTALL_DIR}/backend/storage/logs/laravel.log"
    echo "  Nginx: tail -f /var/log/nginx/error.log"
    echo "  Instalación: cat $LOG_FILE"
    echo ""
    echo -e "${BLUE}Documentación: ${INSTALL_DIR}/docs/${NC}"
    echo ""

    if [ "$INSTALL_TYPE" != "1" ] && [ "$INSTALL_SSL" != "s" ]; then
        echo -e "${YELLOW}Para instalar SSL más tarde:${NC}"
        echo "  apt install -y certbot python3-certbot-nginx"
        echo "  certbot --nginx -d ${SERVER_NAME}"
        echo ""
    fi
}

# NOTE: The rest of the original script remains unchanged.
