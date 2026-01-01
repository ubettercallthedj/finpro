#!/bin/bash
#===============================================================================
# DATAPOLIS PRO - Quick Start Script
# Desarrollo Local con Docker
#===============================================================================

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker no está instalado${NC}"
    echo "Instale Docker desde: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check for docker-compose plugin or legacy binary.
if command -v docker-compose &> /dev/null; then
    COMPOSE_CMD="docker-compose"
elif docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD="docker compose"
else
    echo -e "${RED}Error: Docker Compose no está instalado (ni docker-compose ni docker compose disponibles)${NC}"
    exit 1
fi

# Crear archivos .env si no existen
echo -e "${GREEN}[1/6] Configurando archivos de entorno...${NC}"

if [ ! -f "backend/.env" ]; then
    cp backend/.env.example backend/.env
    echo "✓ Creado backend/.env"
fi

if [ ! -f "frontend/.env" ]; then
    cp frontend/.env.example frontend/.env
    echo "✓ Creado frontend/.env"
fi

# Construir contenedores
echo -e "${GREEN}[2/6] Construyendo contenedores Docker...${NC}"
$COMPOSE_CMD build

# Iniciar servicios
echo -e "${GREEN}[3/6] Iniciando servicios...${NC}"
$COMPOSE_CMD up -d mysql redis

# Esperar a que MySQL esté listo
echo -e "${GREEN}[4/6] Esperando a que MySQL esté listo...${NC}"
sleep 10

# Configurar backend
echo -e "${GREEN}[5/6] Configurando Laravel backend...${NC}"
$COMPOSE_CMD run --rm backend bash -c "
    composer install &&
    php artisan key:generate &&
    php artisan migrate:fresh --seed &&
    php artisan storage:link &&
    php artisan config:cache
"

# Iniciar todos los servicios
echo -e "${GREEN}[6/6] Iniciando aplicación...${NC}"
$COMPOSE_CMD up -d

echo ""
echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           ✅ DATAPOLIS PRO INICIADO CORRECTAMENTE             ║${NC}"
echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Servicios disponibles:${NC}"
