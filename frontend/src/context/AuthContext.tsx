import { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import api from '../services/api'
import toast from 'react-hot-toast'

type User = { 
  id?: number
  name?: string
  email?: string
  ultimo_login?: string 
} | null

type AuthContextValue = {
  user: User
  isAuthenticated: boolean
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => void
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // Verificar si hay un token guardado
    const token = localStorage.getItem('token')
    if (token) {
      // Verificar si el token es válido
      api.get('/auth/user')
        .then(response => {
          setUser(response.data.user)
        })
        .catch(() => {
          localStorage.removeItem('token')
        })
        .finally(() => {
          setIsLoading(false)
        })
    } else {
      setIsLoading(false)
    }
  }, [])

  const login = async (email: string, password: string) => {
    try {
      const response = await api.post('/auth/login', { email, password })
      
      const { user, token } = response.data
      
      // Guardar token en localStorage
      localStorage.setItem('token', token)
      
      // Actualizar estado del usuario
      setUser(user)
      
      toast.success(`Bienvenido ${user.name}`)
    } catch (error: any) {
      const message = error.response?.data?.message || 'Error al iniciar sesión'
      toast.error(message)
      throw error
    }
  }

  const logout = () => {
    // Llamar al endpoint de logout
    api.post('/auth/logout')
      .catch(() => {
        // Ignorar errores del logout
      })
      .finally(() => {
        localStorage.removeItem('token')
        setUser(null)
        window.location.href = '/login'
      })
  }

  return (
    <AuthContext.Provider value={{ user, isAuthenticated: !!user, isLoading, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}

export default AuthProvider
