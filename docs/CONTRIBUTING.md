# Guía de Contribución - DATAPOLIS PRO

¡Gracias por tu interés en contribuir a DATAPOLIS PRO! 🎉

## 📋 Tabla de Contenidos

- [Código de Conducta](#código-de-conducta)
- [¿Cómo Contribuir?](#cómo-contribuir)
- [Configuración del Entorno](#configuración-del-entorno)
- [Flujo de Trabajo Git](#flujo-de-trabajo-git)
- [Estándares de Código](#estándares-de-código)
- [Proceso de Pull Request](#proceso-de-pull-request)
- [Reportar Bugs](#reportar-bugs)
- [Sugerir Mejoras](#sugerir-mejoras)

## 📜 Código de Conducta

Este proyecto se adhiere a un código de conducta. Al participar, se espera que mantengas este código. Por favor reporta comportamiento inaceptable a soporte@datapolis.cl.

## 🤝 ¿Cómo Contribuir?

Hay muchas formas de contribuir:

- 🐛 Reportar bugs
- 💡 Sugerir nuevas funcionalidades
- 📝 Mejorar la documentación
- 🔧 Enviar pull requests con correcciones
- ⭐ Dar una estrella al proyecto

## 🛠️ Configuración del Entorno

### Prerequisitos

- Docker y Docker Compose
- Git
- Node.js 20+ (si no usas Docker)
- PHP 8.3+ (si no usas Docker)

### Instalación

1. **Fork el repositorio**

```bash
# Click en "Fork" en GitHub
```

2. **Clonar tu fork**

```bash
git clone https://github.com/TU_USUARIO/datapolis-pro.git
cd datapolis-pro
```

3. **Agregar upstream**

```bash
git remote add upstream https://github.com/datapolis/datapolis-pro.git
```

4. **Iniciar con Docker**

```bash
# Método 1: Script automático
bash start.sh

# Método 2: Make
make start

# Método 3: Docker Compose
docker-compose up -d
```

5. **Configurar backend**

```bash
# Entrar al contenedor
docker-compose exec backend bash

# Instalar dependencias
composer install

# Generar key
php artisan key:generate

# Migrar base de datos
php artisan migrate --seed
```

6. **Verificar instalación**

- Frontend: http://localhost:3000
- Backend: http://localhost:8000
- PhpMyAdmin: http://localhost:8080

## 🔄 Flujo de Trabajo Git

### 1. Crear una rama

```bash
git checkout develop
git pull upstream develop
git checkout -b feature/mi-nueva-funcionalidad
```

**Convención de nombres:**

- `feature/nombre` - Nueva funcionalidad
- `fix/nombre` - Corrección de bug
- `docs/nombre` - Documentación
- `refactor/nombre` - Refactorización
- `test/nombre` - Tests

### 2. Hacer cambios

```bash
# Hacer tus cambios
git add .
git commit -m "feat: agregar nueva funcionalidad"
```

### 3. Mantener actualizado

```bash
git fetch upstream
git rebase upstream/develop
```

### 4. Push

```bash
git push origin feature/mi-nueva-funcionalidad
```

### 5. Crear Pull Request

- Ve a tu fork en GitHub
- Click en "Pull Request"
- Selecciona `develop` como base
- Llena la plantilla

## 📐 Estándares de Código

### Backend (Laravel/PHP)

**PSR-12 Coding Standard**

```php
<?php

namespace App\Services;

use App\Models\Edificio;

class EdificioService
{
    public function __construct(
        private readonly EdificioRepository $repository
    ) {}

    public function create(array $data): Edificio
    {
        // Validación
        $validated = validator($data, [
            'nombre' => 'required|string|max:255',
            'rut' => 'required|string|unique:edificios',
        ])->validate();

        return $this->repository->create($validated);
    }
}
```

**Ejecutar linter:**

```bash
make lint-backend
```

### Frontend (React/TypeScript)

**ESLint + Prettier**

```tsx
import { useState } from 'react'

interface EdificioCardProps {
  edificio: Edificio
  onSelect: (id: number) => void
}

export function EdificioCard({ edificio, onSelect }: EdificioCardProps) {
  const [isHovered, setIsHovered] = useState(false)

  return (
    <div 
      className="card p-4"
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <h3 className="text-lg font-semibold">{edificio.nombre}</h3>
      <button onClick={() => onSelect(edificio.id)}>
        Seleccionar
      </button>
    </div>
  )
}
```

**Ejecutar linter:**

```bash
make lint-frontend
make format-frontend
```

## 📝 Mensajes de Commit

Seguimos [Conventional Commits](https://www.conventionalcommits.org/):

```
<tipo>(<scope>): <descripción corta>

<descripción larga opcional>

<footer opcional>
```

**Tipos:**

- `feat`: Nueva funcionalidad
- `fix`: Corrección de bug
- `docs`: Documentación
- `style`: Formateo
- `refactor`: Refactorización
- `test`: Tests
- `chore`: Tareas de mantenimiento
- `perf`: Mejora de performance

**Ejemplos:**

```bash
feat(edificios): agregar búsqueda por RUT
fix(auth): corregir validación de email
docs(readme): actualizar instrucciones de instalación
refactor(api): simplificar manejo de errores
test(units): agregar tests para EdificioService
```

## 🔍 Proceso de Pull Request

### Checklist antes de enviar

- [ ] El código sigue los estándares
- [ ] Todos los tests pasan
- [ ] Se agregaron tests para nuevo código
- [ ] La documentación está actualizada
- [ ] Los commits siguen Conventional Commits
- [ ] No hay conflictos con `develop`

### Plantilla de PR

```markdown
## Descripción

Breve descripción de los cambios

## Tipo de cambio

- [ ] Bug fix
- [ ] Nueva funcionalidad
- [ ] Breaking change
- [ ] Documentación

## ¿Cómo se ha probado?

Descripción de las pruebas realizadas

## Checklist

- [ ] Mi código sigue el estilo del proyecto
- [ ] He revisado mi propio código
- [ ] He comentado áreas complejas
- [ ] He actualizado la documentación
- [ ] Mis cambios no generan nuevas warnings
- [ ] He agregado tests
- [ ] Todos los tests pasan

## Screenshots (si aplica)

Capturas de pantalla de los cambios visuales
```

### Revisión

- El equipo revisará tu PR en 2-3 días hábiles
- Puede que te pidamos cambios
- Una vez aprobado, será merged a `develop`

## 🐛 Reportar Bugs

### Antes de reportar

- Busca en issues existentes
- Verifica que sea reproducible
- Recopila información del error

### Template de Bug Report

```markdown
## Descripción del Bug

Descripción clara del problema

## Pasos para Reproducir

1. Ir a '...'
2. Click en '...'
3. Ver error

## Comportamiento Esperado

Lo que debería suceder

## Comportamiento Actual

Lo que realmente sucede

## Screenshots

Si aplica

## Entorno

- OS: [ej. Ubuntu 22.04]
- Browser: [ej. Chrome 120]
- Versión: [ej. 2.5.0]

## Información Adicional

Cualquier otro detalle relevante
```

## 💡 Sugerir Mejoras

### Template de Feature Request

```markdown
## ¿Cuál es el problema?

Descripción del problema actual

## Solución Propuesta

Cómo te gustaría que se resuelva

## Alternativas Consideradas

Otras formas de resolver el problema

## Contexto Adicional

Información extra, mockups, etc.
```

## 🧪 Testing

### Backend

```bash
# Todos los tests
make test-backend

# Tests específicos
docker-compose exec backend php artisan test --filter EdificioTest

# Con coverage
docker-compose exec backend php artisan test --coverage
```

### Frontend

```bash
# Todos los tests
make test-frontend

# Watch mode
docker-compose exec frontend npm run test:watch

# Coverage
docker-compose exec frontend npm run test:coverage
```

## 📚 Recursos Útiles

- [Laravel Documentation](https://laravel.com/docs)
- [React Documentation](https://react.dev)
- [TypeScript Documentation](https://www.typescriptlang.org/docs)
- [TailwindCSS Documentation](https://tailwindcss.com/docs)

## 🎖️ Reconocimientos

Todos los contribuidores serán listados en el README del proyecto.

## 📞 ¿Preguntas?

- Email: dev@datapolis.cl
- Slack: [Únete al workspace](https://datapolis.slack.com)
- Discussions: [GitHub Discussions](https://github.com/datapolis/datapolis-pro/discussions)

---

¡Gracias por contribuir a DATAPOLIS PRO! 🚀
