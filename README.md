# DATAPOLIS PRO - Frontend

Sistema de administración de propiedades inmobiliarias para empresas en Chile.

## 🚀 Tecnologías

- **React 18.2** con TypeScript
- **Vite** - Build tool
- **TailwindCSS** - Estilos
- **React Router v6** - Navegación
- **TanStack Query** - State management y data fetching
- **Axios** - HTTP client
- **Chart.js** - Gráficos
- **React Hot Toast** - Notificaciones

## 📋 Prerequisitos

- Node.js >= 18.0.0
- npm >= 9.0.0 o yarn >= 1.22.0

## 🔧 Instalación

```bash
# Clonar repositorio
git clone [repository-url]
cd datapolis-frontend

# Instalar dependencias
npm install

# Copiar archivo de entorno
cp .env.example .env

# Configurar variables de entorno
# VITE_API_URL=http://localhost:8000/api
```

## 🏃 Desarrollo

```bash
# Iniciar servidor de desarrollo
npm run dev

# Abrir en navegador
# http://localhost:5173
```

## 🏗️ Build

```bash
# Build de producción
npm run build

# Preview del build
npm run preview
```

## 🧪 Testing

```bash
# Ejecutar tests unitarios
npm run test

# Tests con coverage
npm run test:coverage

# Tests E2E con Playwright
npm run test:e2e
```

## 📝 Linting

```bash
# Ejecutar linter
npm run lint

# Fix automático
npm run lint:fix

# Formatear código
npm run format
```

## 📁 Estructura del Proyecto

```
frontend/
├── public/                 # Archivos estáticos
│   └── favicon.svg
├── src/
│   ├── components/        # Componentes reutilizables
│   │   ├── ui/           # Componentes UI básicos
│   │   └── layouts/      # Layouts
│   ├── context/          # React Context
│   │   └── AuthContext.tsx
│   ├── hooks/            # Custom hooks
│   ├── pages/            # Páginas de la aplicación
│   │   ├── DashboardPage.tsx
│   │   ├── EdificiosPage.tsx
│   │   ├── GastosComunesPage.tsx
│   │   ├── ArriendosPage.tsx
│   │   ├── DistribucionPage.tsx
│   │   ├── RRHHPage.tsx
│   │   ├── ContabilidadPage.tsx
│   │   ├── ReunionesPage.tsx
│   │   ├── AsistenteLegalPage.tsx
│   │   ├── ReportesPage.tsx
│   │   ├── ProteccionDatosPage.tsx
│   │   ├── ReportesTributariosPage.tsx
│   │   └── ConfiguracionPage.tsx
│   ├── services/         # Servicios y API
│   │   └── api.ts
│   ├── types/            # TypeScript types
│   ├── utils/            # Utilidades
│   ├── App.tsx           # Componente principal
│   ├── main.tsx          # Entry point
│   └── index.css         # Estilos globales
├── .eslintrc.json        # Configuración ESLint
├── .prettierrc           # Configuración Prettier
├── tailwind.config.js    # Configuración Tailwind
├── tsconfig.json         # Configuración TypeScript
├── vite.config.ts        # Configuración Vite
└── package.json
```

## 🎨 Convenciones de Código

### Componentes
- Usar PascalCase para nombres de componentes
- Un componente por archivo
- Preferir function components sobre class components
- Usar TypeScript interfaces para props

```tsx
interface EdificioCardProps {
  edificio: Edificio
  onSelect: (id: number) => void
}

export function EdificioCard({ edificio, onSelect }: EdificioCardProps) {
  return (
    <div className="card">
      {/* ... */}
    </div>
  )
}
```

### Hooks
- Prefijo `use` para custom hooks
- Colocar hooks al inicio del componente
- No llamar hooks condicionalmente

```tsx
function useEdificios() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['edificios'],
    queryFn: () => api.get('/edificios').then(r => r.data)
  })
  
  return { edificios: data, isLoading, error }
}
```

### Estilos
- Usar clases de Tailwind
- Clases personalizadas en index.css
- Seguir mobile-first approach

```tsx
<div className="card p-4 md:p-6 lg:p-8">
  <h2 className="text-lg md:text-xl font-semibold">Título</h2>
</div>
```

## 🔐 Autenticación

El sistema usa JWT tokens almacenados en localStorage (pendiente migrar a httpOnly cookies).

```tsx
// Login
const { login } = useAuth()
await login(email, password)

// Logout
const { logout } = useAuth()
logout()

// Verificar autenticación
const { isAuthenticated, user } = useAuth()
```

## 📡 API Client

Todas las llamadas a la API se hacen a través del cliente centralizado:

```tsx
import api from '@/services/api'

// GET request
const response = await api.get('/edificios')
const edificios = response.data

// POST request
await api.post('/edificios', {
  nombre: 'Edificio Demo',
  direccion: 'Calle Principal 123'
})

// Con parámetros
await api.get('/unidades', {
  params: { edificio_id: 1 }
})
```

## 🎯 React Query

### Queries
```tsx
const { data, isLoading, error } = useQuery({
  queryKey: ['edificios'],
  queryFn: () => api.get('/edificios').then(r => r.data),
  staleTime: 5 * 60 * 1000, // 5 minutos
})
```

### Mutations
```tsx
const mutation = useMutation({
  mutationFn: (data) => api.post('/edificios', data),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['edificios'] })
    toast.success('Edificio creado')
  },
  onError: (error) => {
    toast.error(error.message)
  }
})
```

## 🌐 Rutas

| Ruta | Componente | Descripción |
|------|-----------|-------------|
| `/` | DashboardPage | Dashboard principal |
| `/edificios` | EdificiosPage | Gestión de edificios |
| `/unidades` | UnidadesPage | Gestión de unidades |
| `/gastos-comunes` | GastosComunesPage | Gastos comunes |
| `/arriendos` | ArriendosPage | Gestión de arriendos |
| `/distribucion` | DistribucionPage | Distribución de ingresos |
| `/rrhh` | RRHHPage | Recursos humanos |
| `/contabilidad` | ContabilidadPage | Contabilidad |
| `/reuniones` | ReunionesPage | Reuniones y asambleas |
| `/legal` | AsistenteLegalPage | Asistente legal |
| `/reportes` | ReportesPage | Reportes generales |
| `/proteccion-datos` | ProteccionDatosPage | Protección de datos |
| `/reportes-tributarios` | ReportesTributariosPage | Reportes tributarios |
| `/configuracion` | ConfiguracionPage | Configuración |

## 🐛 Debugging

### React Query Devtools
Habilitado en desarrollo:

```tsx
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'

<QueryClientProvider client={queryClient}>
  <App />
  <ReactQueryDevtools initialIsOpen={false} />
</QueryClientProvider>
```

### Console Logs
Evitar en producción. Usar en desarrollo:

```tsx
if (import.meta.env.DEV) {
  console.log('Debug info:', data)
}
```

## 📦 Deployment

### Vercel (Recomendado)
```bash
npm install -g vercel
vercel
```

### Build Manual
```bash
npm run build
# Los archivos estarán en dist/
```

### Variables de Entorno en Producción
```
VITE_API_URL=https://api.datapolis.cl
VITE_ENV=production
```

## 🔒 Seguridad

### Mejoras Pendientes
- [ ] Migrar de localStorage a httpOnly cookies
- [ ] Implementar CSRF protection
- [ ] Agregar Content Security Policy
- [ ] Validación robusta de formularios con Zod
- [ ] Sanitización de HTML

### Buenas Prácticas
- ✅ No almacenar datos sensibles en state
- ✅ Validar inputs del usuario
- ✅ Usar HTTPS en producción
- ✅ Sanitizar contenido HTML dinámico
- ✅ Implementar rate limiting

## 📊 Performance

### Métricas Objetivo
- First Contentful Paint: < 1.5s
- Time to Interactive: < 3.5s
- Lighthouse Score: > 90

### Optimizaciones Implementadas
- ✅ Code splitting por ruta
- ✅ Lazy loading de componentes
- ✅ React Query caching
- ✅ Memoización con React.memo
- ✅ Virtualización de listas (pendiente)

## 🤝 Contribución

1. Fork el proyecto
2. Crear feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a branch (`git push origin feature/AmazingFeature`)
5. Abrir Pull Request

### Commits
Seguir Conventional Commits:
```
feat: nueva funcionalidad
fix: corrección de bug
docs: cambios en documentación
style: formateo, punto y coma faltantes, etc
refactor: refactorización de código
test: agregar tests
chore: tareas de mantenimiento
perf: mejora de performance
```

## 📄 Licencia

Propietario - DATAPOLIS PRO © 2026

## 👥 Equipo

- Desarrollo Frontend: [Tu Nombre]
- Desarrollo Backend: [Nombre]
- UI/UX: [Nombre]
- QA: [Nombre]

## 📞 Soporte

- Email: soporte@datapolis.cl
- Docs: https://docs.datapolis.cl
- Issues: GitHub Issues

---

**Última actualización**: 1 de enero de 2026
