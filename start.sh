#!/bin/bash
#===============================================================================
# DATAPOLIS PRO - Quick Start Script
# Desarrollo Local con Docker
# © 2026 DATAPOLIS SpA
#===============================================================================

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${BLUE}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║          DATAPOLIS PRO v2.5 - Quick Start                     ║
║       Entorno de Desarrollo con Docker                        ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Verificar Docker
echo -e "${CYAN}[1/8] Verificando Docker...${NC}"
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Error: Docker no está instalado${NC}"
    echo "Instale Docker desde: https://docs.docker.com/get-docker/"
    exit 1
fi
echo -e "${GREEN}✓ Docker instalado${NC}"

# Check for docker compose plugin or legacy binary
echo -e "${CYAN}[2/8] Verificando Docker Compose...${NC}"
if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
    echo -e "${GREEN}✓ Docker Compose plugin disponible${NC}"
elif command -v docker-compose &> /dev/null; then
    COMPOSE_CMD="docker-compose"
    echo -e "${GREEN}✓ Docker Compose (legacy) disponible${NC}"
else
    echo -e "${RED}❌ Error: Docker Compose no está disponible${NC}"
    echo "Instale Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

# Crear archivos .env si no existen
echo -e "${CYAN}[3/8] Configurando archivos de entorno...${NC}"

if [ ! -f "backend/.env" ]; then
    if [ -f "backend/.env.example" ]; then
        cp backend/.env.example backend/.env
        echo -e "${GREEN}✓ Creado backend/.env${NC}"
    else
        echo -e "${YELLOW}⚠️  Advertencia: backend/.env.example no encontrado${NC}"
    fi
else
    echo -e "${GREEN}✓ backend/.env ya existe${NC}"
fi

if [ ! -f "frontend/.env" ]; then
    if [ -f "frontend/.env.example" ]; then
        cp frontend/.env.example frontend/.env
        echo -e "${GREEN}✓ Creado frontend/.env${NC}"
    else
        # Crear .env básico para frontend
        cat > frontend/.env << 'ENVFILE'
VITE_API_URL=http://localhost:8000/api
ENVFILE
        echo -e "${GREEN}✓ Creado frontend/.env con configuración básica${NC}"
    fi
else
    echo -e "${GREEN}✓ frontend/.env ya existe${NC}"
fi

# Detener contenedores previos si existen
echo -e "${CYAN}[4/8] Limpiando contenedores previos...${NC}"
$COMPOSE_CMD down 2>/dev/null || true
echo -e "${GREEN}✓ Limpieza completada${NC}"

# Construir contenedores
echo -e "${CYAN}[5/8] Construyendo contenedores Docker...${NC}"
echo -e "${YELLOW}Esto puede tomar varios minutos la primera vez...${NC}"
$COMPOSE_CMD build --no-cache

# Iniciar servicios de base de datos primero
echo -e "${CYAN}[6/8] Iniciando servicios de base de datos...${NC}"
$COMPOSE_CMD up -d mysql redis

# Esperar a que MySQL esté listo
echo -e "${CYAN}[7/8] Esperando a que MySQL esté listo...${NC}"
echo -n "Esperando"
for i in {1..30}; do
    if $COMPOSE_CMD exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
        echo ""
        echo -e "${GREEN}✓ MySQL está listo${NC}"
        break
    fi
    echo -n "."
    sleep 2
    if [ $i -eq 30 ]; then
        echo ""
        echo -e "${RED}❌ Error: MySQL no respondió después de 60 segundos${NC}"
        echo "Ejecute: $COMPOSE_CMD logs mysql"
        exit 1
    fi
done

# Configurar backend
echo -e "${CYAN}[8/8] Configurando Laravel backend...${NC}"
$COMPOSE_CMD run --rm backend bash -c "
    echo '→ Instalando dependencias de Composer...'
    composer install --no-interaction --prefer-dist --optimize-autoloader
    
    echo '→ Generando application key...'
    php artisan key:generate --force
    
    echo '→ Ejecutando migraciones...'
    php artisan migrate:fresh --seed --force
    
    echo '→ Creando enlace simbólico de storage...'
    php artisan storage:link
    
    echo '→ Cacheando configuraciones...'
    php artisan config:cache
    php artisan route:cache
    
    echo '✓ Backend configurado exitosamente'
"

# Iniciar todos los servicios
echo -e "${GREEN}Iniciando todos los servicios...${NC}"
$COMPOSE_CMD up -d

# Esperar un momento para que los servicios inicien
sleep 3

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           ✅ DATAPOLIS PRO INICIADO CORRECTAMENTE             ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}🌐 Servicios disponibles:${NC}"
echo ""
echo -e "  Frontend:  ${GREEN}http://localhost:5173${NC}"
echo -e "  Backend:   ${GREEN}http://localhost:8000${NC}"
echo -e "  API Docs:  ${GREEN}http://localhost:8000/api/documentation${NC}"
echo -e "  MySQL:     ${CYAN}localhost:3306${NC}"
echo -e "  Redis:     ${CYAN}localhost:6379${NC}"
echo -e "  MailHog:   ${GREEN}http://localhost:8025${NC}"
echo ""
echo -e "${YELLOW}🔑 Credenciales por defecto:${NC}"
echo ""
echo -e "  ${CYAN}MySQL:${NC}"
echo "    Database: datapolis"
echo "    User:     datapolis"
echo "    Password: datapolis123"
echo ""
echo -e "  ${CYAN}Aplicación:${NC}"
echo "    Email:    admin@datapolis.local"
echo "    Password: admin123"
echo ""
echo -e "${YELLOW}📝 Comandos útiles:${NC}"
echo ""
echo "  Ver logs:              $COMPOSE_CMD logs -f"
echo "  Ver logs (servicio):   $COMPOSE_CMD logs -f backend"
echo "  Reiniciar:             $COMPOSE_CMD restart"
echo "  Detener:               $COMPOSE_CMD down"
echo "  Detener + limpiar:     $COMPOSE_CMD down -v"
echo "  Acceder a contenedor:  $COMPOSE_CMD exec backend bash"
echo "  Ejecutar comando:      $COMPOSE_CMD exec backend php artisan [comando]"
echo ""
echo -e "${YELLOW}🔧 Desarrollo:${NC}"
echo ""
echo "  Migrations:  $COMPOSE_CMD exec backend php artisan migrate"
echo "  Seeders:     $COMPOSE_CMD exec backend php artisan db:seed"
echo "  Tinker:      $COMPOSE_CMD exec backend php artisan tinker"
echo "  Tests:       $COMPOSE_CMD exec backend php artisan test"
echo ""
echo -e "${GREEN}✨ ¡Listo para desarrollar! ✨${NC}"
echo ""
echo -e "${CYAN}📚 Documentación disponible en: ./docs/${NC}"
echo ""
