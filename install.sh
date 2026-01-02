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
INSTALL_TYPE=""
INSTALL_SSL=""
SERVER_NAME=""

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

# Verificar privilegios de root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "Este script debe ejecutarse con privilegios de root (sudo)"
    fi
}

# Mostrar banner
show_banner() {
    clear
    echo -e "${BLUE}"
    echo "╔════════════════════════════════════════════════════════════════╗"
    echo "║                                                                ║"
    echo "║         DATAPOLIS PRO v2.5 - Sistema de Instalación           ║"
    echo "║       Sistema de Gestión Integral para Condominios            ║"
    echo "║                  © 2025 DATAPOLIS SpA                         ║"
    echo "║                                                                ║"
    echo "╚════════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo ""
}

# Solicitar tipo de instalación
prompt_install_type() {
    echo -e "${YELLOW}Seleccione el tipo de instalación:${NC}"
    echo "  1) Producción (Servidor Linux completo)"
    echo "  2) Desarrollo (Configuración local)"
    echo ""

    while true; do
        read -p "Opción (1 o 2): " INSTALL_TYPE
        if [ "$INSTALL_TYPE" = "1" ] || [ "$INSTALL_TYPE" = "2" ]; then
            break
        fi
        echo -e "${RED}Opción inválida. Ingrese 1 o 2${NC}"
    done
}

# Solicitar configuración SSL
prompt_ssl_config() {
    echo ""
    echo -e "${YELLOW}¿Desea configurar SSL/HTTPS?${NC}"
    echo "  s) Sí, instalar Let's Encrypt"
    echo "  n) No, usar HTTP"
    echo ""

    while true; do
        read -p "Opción (s o n): " INSTALL_SSL
        if [ "$INSTALL_SSL" = "s" ] || [ "$INSTALL_SSL" = "n" ]; then
            break
        fi
        echo -e "${RED}Opción inválida. Ingrese s o n${NC}"
    done
}

# Solicitar nombre del servidor
prompt_server_name() {
    echo ""
    echo -e "${YELLOW}Nombre del servidor / Dominio:${NC}"
    echo "Ejemplos: datapolis.cl, datapolis.com, localhost"
    echo ""

    while true; do
        read -p "Nombre de servidor: " SERVER_NAME
        if [ ! -z "$SERVER_NAME" ]; then
            break
        fi
        echo -e "${RED}El nombre del servidor no puede estar vacío${NC}"
    done
}

# Instalar dependencias del sistema
install_dependencies() {
    info "Actualizando repositorios del sistema..."
    apt-get update -qq || error "Error al actualizar repositorios"

    if [ "$INSTALL_TYPE" = "1" ]; then
        info "Instalando dependencias de producción..."
        apt-get install -y \
            curl wget git vim htop \
            software-properties-common \
            apt-transport-https ca-certificates \
            > /dev/null 2>&1 || error "Error instalando dependencias básicas"

        info "Instalando PHP ${PHP_VERSION}..."
        add-apt-repository ppa:ondrej/php -y > /dev/null 2>&1
        apt-get update -qq
        apt-get install -y \
            php${PHP_VERSION}-fpm \
            php${PHP_VERSION}-cli \
            php${PHP_VERSION}-mysql \
            php${PHP_VERSION}-redis \
            php${PHP_VERSION}-xml \
            php${PHP_VERSION}-curl \
            php${PHP_VERSION}-mbstring \
            php${PHP_VERSION}-zip \
            > /dev/null 2>&1 || error "Error instalando PHP"

        info "Instalando Node.js ${NODE_VERSION}..."
        curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - > /dev/null 2>&1
        apt-get install -y nodejs > /dev/null 2>&1 || error "Error instalando Node.js"

        info "Instalando Nginx..."
        apt-get install -y nginx > /dev/null 2>&1 || error "Error instalando Nginx"

        info "Instalando MySQL Server..."
        apt-get install -y mysql-server > /dev/null 2>&1 || error "Error instalando MySQL"

        info "Instalando Redis..."
        apt-get install -y redis-server > /dev/null 2>&1 || error "Error instalando Redis"

        if [ "$INSTALL_SSL" = "s" ]; then
            info "Instalando Certbot para SSL..."
            apt-get install -y certbot python3-certbot-nginx > /dev/null 2>&1 || error "Error instalando Certbot"
        fi
    else
        info "Instalando dependencias de desarrollo..."
        apt-get install -y \
            curl wget git vim \
            build-essential \
            > /dev/null 2>&1 || error "Error instalando dependencias básicas"
    fi

    log "Dependencias instaladas correctamente"
}

# Crear estructura de directorios
setup_directories() {
    info "Creando estructura de directorios..."

    mkdir -p "$INSTALL_DIR/backend"
    mkdir -p "$INSTALL_DIR/frontend"
    mkdir -p "$INSTALL_DIR/docs"
    mkdir -p "$INSTALL_DIR/logs"
    mkdir -p "$INSTALL_DIR/storage"

    chmod -R 755 "$INSTALL_DIR"
    log "Directorios creados en ${INSTALL_DIR}"
}

# Configurar servicios (solo producción)
setup_services() {
    if [ "$INSTALL_TYPE" != "1" ]; then
        return
    fi

    info "Configurando servicios del sistema..."

    # Iniciar servicios
    systemctl enable php${PHP_VERSION}-fpm
    systemctl start php${PHP_VERSION}-fpm

    systemctl enable nginx
    systemctl start nginx

    systemctl enable mysql
    systemctl start mysql

    systemctl enable redis-server
    systemctl start redis-server

    log "Servicios configurados y activados"
}

# Configurar SSL
setup_ssl() {
    if [ "$INSTALL_TYPE" = "1" ] && [ "$INSTALL_SSL" = "s" ]; then
        info "Configurando SSL para ${SERVER_NAME}..."

        certbot certonly --nginx -d "${SERVER_NAME}" --non-interactive --agree-tos -m admin@${SERVER_NAME} 2>/dev/null || \
            warn "No se pudo configurar SSL automáticamente. Ejecute: certbot --nginx -d ${SERVER_NAME}"

        log "Configuración SSL iniciada"
    fi
}

# Mostrar mensaje de finalización
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

# Función principal
main() {
    check_root
    show_banner

    prompt_install_type
    prompt_ssl_config
    prompt_server_name

    echo ""
    echo -e "${YELLOW}Iniciando instalación con las siguientes opciones:${NC}"
    echo "  Tipo: $([ "$INSTALL_TYPE" = "1" ] && echo "Producción" || echo "Desarrollo")"
    echo "  SSL: $([ "$INSTALL_SSL" = "s" ] && echo "Habilitado" || echo "Deshabilitado")"
    echo "  Servidor: $SERVER_NAME"
    echo ""

    read -p "¿Desea continuar? (s/n): " CONFIRM
    if [ "$CONFIRM" != "s" ]; then
        info "Instalación cancelada"
        exit 0
    fi

    echo ""
    install_dependencies
    setup_directories
    setup_services
    setup_ssl

    show_completion
}

main "$@"
