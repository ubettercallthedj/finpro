// React import not required with modern JSX transform

interface LoadingProps {
  /**
   * Tamaño del spinner
   */
  size?: 'sm' | 'md' | 'lg' | 'xl'
  
  /**
   * Mensaje de carga opcional
   */
  message?: string
  
  /**
   * Si true, ocupa toda la pantalla
   */
  fullScreen?: boolean
  
  /**
   * Color del spinner
   */
  color?: 'primary' | 'white' | 'gray'
}

const sizeClasses = {
  sm: 'w-4 h-4 border-2',
  md: 'w-8 h-8 border-4',
  lg: 'w-12 h-12 border-4',
  xl: 'w-16 h-16 border-4'
}

const colorClasses = {
  primary: 'border-primary-600 border-t-transparent',
  white: 'border-white border-t-transparent',
  gray: 'border-gray-600 border-t-transparent'
}

export function Loading({ 
  size = 'md', 
  message, 
  fullScreen = false,
  color = 'primary'
}: LoadingProps) {
  const spinner = (
    <div className={`${sizeClasses[size]} ${colorClasses[color]} rounded-full animate-spin`} />
  )

  if (fullScreen) {
    return (
      <div className="fixed inset-0 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center z-50">
        {spinner}
        {message && (
          <p className="mt-4 text-gray-600 text-sm">{message}</p>
        )}
      </div>
    )
  }

  return (
    <div className="flex flex-col items-center justify-center py-8">
      {spinner}
      {message && (
        <p className="mt-4 text-gray-600 text-sm">{message}</p>
      )}
    </div>
  )
}

/**
 * Skeleton loader para tablas
 */
export function TableSkeleton({ rows = 5, columns = 5 }) {
  return (
    <div className="space-y-3 animate-pulse">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="flex gap-4">
          {Array.from({ length: columns }).map((_, j) => (
            <div 
              key={j} 
              className="h-10 bg-gray-200 rounded flex-1"
            />
          ))}
        </div>
      ))}
    </div>
  )
}

/**
 * Skeleton loader para cards
 */
export function CardSkeleton({ count = 3 }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="card p-6 animate-pulse">
          <div className="flex items-start gap-4">
            <div className="w-12 h-12 bg-gray-200 rounded-lg" />
            <div className="flex-1 space-y-2">
              <div className="h-5 bg-gray-200 rounded w-3/4" />
              <div className="h-4 bg-gray-200 rounded w-1/2" />
            </div>
          </div>
          <div className="mt-4 space-y-2">
            <div className="h-4 bg-gray-200 rounded" />
            <div className="h-4 bg-gray-200 rounded w-5/6" />
          </div>
        </div>
      ))}
    </div>
  )
}

/**
 * Spinner inline para botones
 */
export function ButtonSpinner({ size = 'sm' }: { size?: 'sm' | 'md' }) {
  return (
    <svg 
      className={`animate-spin ${size === 'sm' ? 'w-4 h-4' : 'w-5 h-5'}`} 
      viewBox="0 0 24 24"
    >
      <circle 
        className="opacity-25" 
        cx="12" 
        cy="12" 
        r="10" 
        stroke="currentColor" 
        strokeWidth="4" 
        fill="none" 
      />
      <path 
        className="opacity-75" 
        fill="currentColor" 
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" 
      />
    </svg>
  )
}

export default Loading
