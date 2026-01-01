import { createContext, useContext, useState, useEffect, ReactNode } from 'react'

type User = { id?: number; name?: string; email?: string; ultimo_login?: string } | null

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
    // Minimal bootstrap: no real auth, simulate loaded state
    setTimeout(() => setIsLoading(false), 200)
  }, [])

  const login = async (email: string, _password: string) => {
    // TODO: replace with real API call; for now simulate success
    setUser({ id: 1, name: 'Admin', email })
  }

  const logout = () => {
    setUser(null)
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
