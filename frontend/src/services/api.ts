import axios from 'axios'
import toast from 'react-hot-toast'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Interceptor para token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Interceptor para errores
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token')
      window.location.href = '/login'
    } else if (error.response?.status === 422) {
      const errors = error.response.data.errors
      if (errors) {
        Object.values(errors).flat().forEach((msg: any) => {
          toast.error(msg)
        })
      } else if (error.response.data.message) {
        toast.error(error.response.data.message)
      }
    } else if (error.response?.status >= 500) {
      toast.error('Error del servidor. Intente nuevamente.')
    }
    return Promise.reject(error)
  }
)

// Helpers
export const formatMoney = (amount: number | null | undefined): string => {
  if (amount === null || amount === undefined) return '$0'
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    minimumFractionDigits: 0,
  }).format(amount)
}

export const formatDate = (date: string | null | undefined): string => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('es-CL')
}

export const formatDateTime = (date: string | null | undefined): string => {
  if (!date) return '-'
  return new Date(date).toLocaleString('es-CL')
}

export const getEstadoColor = (estado: string): string => {
  const colores: Record<string, string> = {
    pagada: 'badge-success',
    activo: 'badge-success',
    aprobada: 'badge-success',
    confirmado: 'badge-success',
    pendiente: 'badge-warning',
    borrador: 'badge-gray',
    vencida: 'badge-danger',
    anulada: 'badge-danger',
    rechazada: 'badge-danger',
    parcial: 'badge-info',
    en_curso: 'badge-info',
  }
  return colores[estado] || 'badge-gray'
}

export default api
