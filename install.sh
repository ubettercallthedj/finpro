# ...existing code...
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

spinner() {
    local pid=$1
    local delay=0.1
    local spinstr="|/-\\"
    while ps -p "$pid" > /dev/null 2>&1; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

check_system() {
    log "Verificando sistema operativo..."
    
    if [ ! -f /etc/os-release ]; then
        error "No se puede determinar el sistema operativo"
    fi
    
    . /etc/os-release
    
    if [[ "$ID" != "ubuntu" ]] && [[ "$ID" != "debian" ]]; then
        error "Este script solo soporta Ubuntu/Debian"
    fi
    
    info "Sistema operativo: $PRETTY_NAME"
}

check_root() {
    if [ "$EUID" -ne 0 ]; then 
        error "Este script debe ejecutarse como root. Use: sudo bash install.sh"
    fi
}

check_internet() {
    log "Verificando conexión a Internet..."
    
    if ! ping -c 1 google.com &> /dev/null; then
        error "No hay conexión a Internet"
    fi
    
    info "Conexión a Internet: OK"
}

check_ports() {
    log "Verificando puertos disponibles..."
    
    local ports=(80 443 3306 6379)
    for port in "${ports[@]}"; do
        if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null 2>&1; then
            warn "Puerto $port ya está en uso"
        else
            info "Puerto $port disponible"
        fi
    done
}

show_banner() {
    clear
    echo -e "${BLUE}"
    cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║        DATAPOLIS PRO v2.5 - Instalación Completa             ║
║     Sistema de Gestión Integral para Condominios             ║
║                                                               ║
║                    © 2025 DATAPOLIS SpA                       ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
}

get_configuration() {
    echo -e "${YELLOW}Configuración de la instalación:${NC}\n"
    
    read -p "$(echo -e ${CYAN}¿Tipo de instalación? ${NC}[1=Producción, 2=Desarrollo]: )" INSTALL_TYPE
    INSTALL_TYPE=${INSTALL_TYPE:-1}
    
    if [ "$INSTALL_TYPE" = "1" ]; then
        read -p "$(echo -e ${CYAN}Dominio del servidor: ${NC})" SERVER_NAME
        if [ -z "$SERVER_NAME" ]; then
            error "El dominio no puede estar vacío para instalación de producción"
        fi
    else
        SERVER_NAME="localhost"
    fi
    
    read -p "$(echo -e ${CYAN}Nombre de la base de datos ${NC}[datapolis]: )" DB_NAME
    DB_NAME=${DB_NAME:-datapolis}
    
    read -p "$(echo -e ${CYAN}Usuario de la base de datos ${NC}[datapolis]: )" DB_USER
    DB_USER=${DB_USER:-datapolis}
    
    while true; do
        read -sp "$(echo -e ${CYAN}Contraseña de la base de datos: ${NC})" DB_PASS
        echo ""
        
        if [ -z "$DB_PASS" ]; then
            warn "La contraseña no puede estar vacía"
            continue
        fi
        
        read -sp "$(echo -e ${CYAN}Confirmar contraseña: ${NC})" DB_PASS_CONFIRM
        echo ""
        
        if [ "$DB_PASS" = "$DB_PASS_CONFIRM" ]; then
            break
        else
            warn "Las contraseñas no coinciden"
        fi
    done
    
    INSTALL_SSL="n"
    SSL_EMAIL=""
    if [ "$INSTALL_TYPE" = "1" ]; then
        read -p "$(echo -e ${CYAN}¿Instalar SSL con Let's Encrypt? ${NC}[s/n]: )" INSTALL_SSL
        INSTALL_SSL=${INSTALL_SSL:-n}
        
        if [ "$INSTALL_SSL" = "s" ] || [ "$INSTALL_SSL" = "S" ]; then
            read -p "$(echo -e ${CYAN}Email para Let's Encrypt: ${NC})" SSL_EMAIL
            if [ -z "$SSL_EMAIL" ]; then
                warn "Email vacío. SSL no será configurado."
                INSTALL_SSL="n"
            fi
        fi
    fi
    
    echo ""
    echo -e "${YELLOW}Resumen de la configuración:${NC}"
    echo "  Tipo: $([ "$INSTALL_TYPE" = "1" ] && echo "Producción" || echo "Desarrollo")"
    echo "  Servidor: $SERVER_NAME"
    echo "  Base de datos: $DB_NAME"
    echo "  Usuario BD: $DB_USER"
    echo "  Directorio: $INSTALL_DIR"
    [ "$INSTALL_TYPE" = "1" ] && [ "$INSTALL_SSL" = "s" ] && echo "  SSL: Si ($SSL_EMAIL)"
    echo ""
    
    read -p "$(echo -e ${YELLOW}¿Continuar con la instalación? ${NC}[s/n]: )" CONFIRM
    if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
        info "Instalación cancelada por el usuario"
        exit 0
    fi
}

update_system() {
    log "Actualizando sistema..."
    
    apt update > /dev/null 2>&1 &
    spinner $!
    
    DEBIAN_FRONTEND=noninteractive apt upgrade -y > /dev/null 2>&1 &
    spinner $!
    
    info "Sistema actualizado"
}

install_php() {
    log "Instalando PHP $PHP_VERSION..."
    
    if command -v php &> /dev/null; then
        local current_version=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
        if [ "$current_version" = "$PHP_VERSION" ]; then
            info "PHP $PHP_VERSION ya está instalado"
            return
        fi
    fi
    
    apt install -y software-properties-common > /dev/null 2>&1
    add-apt-repository ppa:ondrej/php -y > /dev/null 2>&1 || true
    apt update > /dev/null 2>&1
    
    apt install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-imagick > /dev/null 2>&1 &
    spinner $!
    
    # Configurar PHP
    PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
    if [ -f "$PHP_INI" ]; then
        sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' "$PHP_INI"
        sed -i 's/post_max_size = .*/post_max_size = 100M/' "$PHP_INI"
        sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"
        sed -i 's/memory_limit = .*/memory_limit = 512M/' "$PHP_INI"
    fi
    
    systemctl restart php${PHP_VERSION}-fpm || true
    
    info "PHP $PHP_VERSION instalado y configurado"
}

install_mysql() {
    log "Instalando MySQL..."
    
    if command -v mysql &> /dev/null; then
        info "MySQL ya está instalado"
    else
        DEBIAN_FRONTEND=noninteractive apt install -y mysql-server > /dev/null 2>&1 &
        spinner $!
        info "MySQL instalado"
    fi
    
    log "Configurando base de datos..."
    
    mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    info "Base de datos configurada"
}

install_redis() {
    log "Instalando Redis..."
    
    if command -v redis-cli &> /dev/null; then
        info "Redis ya está instalado"
    else
        apt install -y redis-server > /dev/null 2>&1 &
        spinner $!
        
        systemctl enable redis-server
        systemctl start redis-server
        
        info "Redis instalado"
    fi
}

install_nginx() {
    log "Instalando Nginx..."
    
    if command -v nginx &> /dev/null; then
        info "Nginx ya está instalado"
    else
        apt install -y nginx > /dev/null 2>&1 &
        spinner $!
        info "Nginx instalado"
    fi
}

install_composer() {
    log "Instalando Composer..."
    
    if command -v composer &> /dev/null; then
        info "Composer ya está instalado"
        return
    fi
    
    curl -sS https://getcomposer.org/installer | php > /dev/null 2>&1
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    
    info "Composer instalado"
}

install_nodejs() {
    log "Instalando Node.js $NODE_VERSION..."
    
    if command -v node &> /dev/null; then
        local current_version=$(node -v | cut -d "v" -f 2 | cut -d "." -f 1)
        if [ "$current_version" = "$NODE_VERSION" ]; then
            info "Node.js $NODE_VERSION ya está instalado"
            return
        fi
    fi
    
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - > /dev/null 2>&1
    apt install -y nodejs > /dev/null 2>&1 &
    spinner $!
    
    info "Node.js $NODE_VERSION instalado"
}

install_application() {
    log "Instalando aplicación DATAPOLIS PRO..."
    
    # Crear directorio
    mkdir -p $INSTALL_DIR
    
    # Obtener directorio del script
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
    
    # Copiar archivos
    if [ -d "$SCRIPT_DIR/backend" ]; then
        log "Copiando backend..."
        cp -r $SCRIPT_DIR/backend $INSTALL_DIR/
    fi
    
    if [ -d "$SCRIPT_DIR/frontend" ]; then
        log "Copiando frontend..."
        cp -r $SCRIPT_DIR/frontend $INSTALL_DIR/
    fi
    
    # Configurar Backend
    log "Configurando Laravel backend..."
    cd $INSTALL_DIR/backend
    
    composer install --no-dev --optimize-autoloader > /dev/null 2>&1 &
    spinner $!
    
    # Crear .env
    if [ -f .env.example ]; then
        cp .env.example .env
        sed -i "s|APP_URL=.*|APP_URL=$([ "$INSTALL_TYPE" = "1" ] && echo "https://${SERVER_NAME}" || echo "http://${SERVER_NAME}")|g" .env
        sed -i "s|APP_ENV=.*|APP_ENV=$([ "$INSTALL_TYPE" = "1" ] && echo "production" || echo "local")|g" .env
        sed -i "s|APP_DEBUG=.*|APP_DEBUG=$([ "$INSTALL_TYPE" = "1" ] && echo "false" || echo "true")|g" .env
        sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|g" .env
        sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USER}|g" .env
        sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|g" .env
    fi
    
    # Generar key y migrar
    php artisan key:generate --force
    php artisan migrate --seed --force > /dev/null 2>&1 &
    spinner $!
    
    php artisan storage:link || true
    
    if [ "$INSTALL_TYPE" = "1" ]; then
        php artisan config:cache || true
        php artisan route:cache || true
        php artisan view:cache || true
    fi
    
    info "Backend configurado"
    
    # Configurar Frontend
    log "Configurando React frontend..."
    cd $INSTALL_DIR/frontend || return
    
    # Crear .env
    if [ -f .env.example ]; then
        cp .env.example .env
        sed -i "s|VITE_API_URL=.*|VITE_API_URL=$([ "$INSTALL_TYPE" = "1" ] && echo "https://${SERVER_NAME}/api" || echo "http://${SERVER_NAME}:8000/api")|g" .env
        sed -i "s|VITE_ENV=.*|VITE_ENV=$([ "$INSTALL_TYPE" = "1" ] && echo "production" || echo "development")|g" .env
    fi
    
    npm install > /dev/null 2>&1 &
    spinner $!
    
    if [ "$INSTALL_TYPE" = "1" ]; then
        npm run build > /dev/null 2>&1 &
        spinner $!
        info "Frontend compilado para producción"
    else
        info "Frontend configurado para desarrollo"
    fi
}

configure_nginx() {
    log "Configurando Nginx..."
    
    cat > /etc/nginx/sites-available/datapolis <<EOF
server {
    listen 80;
    server_name ${SERVER_NAME};
    root ${INSTALL_DIR}/backend/public;
    index index.php index.html;

    client_max_body_size 100M;
    client_body_timeout 300s;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Frontend estático (React build)
    location /app {
        alias ${INSTALL_DIR}/frontend/dist;
        try_files \$uri \$uri/ /app/index.html;
        
        # Cache para assets
        location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }

    # API Laravel
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    # Activar sitio
    ln -sf /etc/nginx/sites-available/datapolis /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default || true
    
    nginx -t > /dev/null 2>&1
    systemctl restart nginx || true
    
    info "Nginx configurado"
}

configure_ssl() {
    if [ "$INSTALL_TYPE" != "1" ] || [ "$INSTALL_SSL" != "s" ]; then
        return
    fi
    
    log "Configurando SSL con Let's Encrypt..."
    
    apt install -y certbot python3-certbot-nginx > /dev/null 2>&1 &
    spinner $!
    
    certbot --nginx -d ${SERVER_NAME} --non-interactive --agree-tos -m ${SSL_EMAIL} --redirect || warn "Certbot falló"
    
    info "SSL configurado"
}

configure_permissions() {
    log "Configurando permisos..."
    
    chown -R www-data:www-data $INSTALL_DIR
    chmod -R 755 $INSTALL_DIR
    chmod -R 775 $INSTALL_DIR/backend/storage || true
    chmod -R 775 $INSTALL_DIR/backend/bootstrap/cache || true
    
    info "Permisos configurados"
}

configure_cron() {
    log "Configurando tareas programadas..."
    
    (crontab -u www-data -l 2>/dev/null | grep -v "artisan schedule:run"; echo "* * * * * cd ${INSTALL_DIR}/backend && php artisan schedule:run >> /dev/null 2>&1") | crontab -u www-data -
    
    info "Cron configurado"
}

configure_firewall() {
    if [ "$INSTALL_TYPE" != "1" ]; then
        return
    fi
    
    log "Configurando firewall..."
    
    if command -v ufw &> /dev/null; then
        ufw --force enable
        ufw allow 22/tcp
        ufw allow 80/tcp
        ufw allow 443/tcp
        ufw reload
        
        info "Firewall configurado"
    else
        warn "UFW no está disponible, omitiendo configuración de firewall"
    fi
}

create_systemd_service() {
    if [ "$INSTALL_TYPE" != "1" ]; then
        return
    fi
    
    log "Creando servicio systemd para Laravel Queue..."
    
    cat > /etc/systemd/system/datapolis-queue.service <<EOF
[Unit]
Description=DATAPOLIS Queue Worker
After=network.target mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=3
ExecStart=/usr/bin/php ${INSTALL_DIR}/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable datapolis-queue
    systemctl start datapolis-queue || true
    
    info "Servicio queue configurado"
}

show_completion() {
    echo ""
    echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║           ✅ INSTALACIÓN COMPLETADA EXITOSAMENTE              ║${NC}"
    echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
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

# =====================================================
# MAIN
# =====================================================

main() {
    show_banner
    check_root
    check_system
    check_internet
    check_ports
    get_configuration
    
    echo ""
    log "Iniciando instalación..."
    echo ""
    
    update_system
    install_php
    install_mysql
    install_redis
    install_nginx
    install_composer
    install_nodejs
    install_application
    configure_nginx
    configure_ssl
    configure_permissions
    configure_cron
    configure_firewall
    create_systemd_service
    
    show_completion
}

# Ejecutar
main "$@"
# ...existing code...
