import { useState, useEffect } from 'react'
import { 
  DocumentTextIcon, 
  ArrowDownTrayIcon, 
  CheckCircleIcon, 
  ExclamationTriangleIcon, 
  CalendarIcon,
  BuildingOffice2Icon, 
  CurrencyDollarIcon, 
  UsersIcon, 
  DocumentCheckIcon, 
  MagnifyingGlassIcon, 
  FunnelIcon,
  ChartPieIcon, 
  ArrowTrendingUpIcon, 
  ClipboardDocumentListIcon, 
  ShieldCheckIcon, 
  PrinterIcon
} from '@heroicons/react/24/outline'
import api from '../services/api'
import toast from 'react-hot-toast'

interface Edificio {
  id: number
  nombre: string
  rut: string
}

interface Balance {
  id: number
  anio_tributario: number
  tipo: string
  total_activos: number
  total_pasivos: number
  total_patrimonio: number
  cuadrado: boolean
  estado: string
  created_at: string
}

interface EstadoResultados {
  id: number
  anio_tributario: number
  total_ingresos_operacionales: number
  total_gastos_operacionales: number
  resultado_operacional: number
  distribucion_copropietarios: number
  resultado_ejercicio: number
}

interface CertificadoDeuda {
  id: number
  numero_certificado: string
  tipo: string
  unidad_numero: string
  tiene_deuda: boolean
  deuda_total: number
  fecha_emision: string
  fecha_validez: string
  codigo_verificacion: string
}

interface ChecklistUnidad {
  id: number
  numero: string
  tipo: string
  porcentaje_cumplimiento: number
  estado_general: string
  alertas: string[]
}

export default function ReportesTributariosPage() {
  const [activeTab, setActiveTab] = useState<'balance' | 'eerr' | 'dj' | 'distribucion' | 'certificados' | 'checklist'>('balance')
  const [edificios, setEdificios] = useState<Edificio[]>([])
  const [selectedEdificio, setSelectedEdificio] = useState<number | null>(null)
  const [selectedAnio, setSelectedAnio] = useState<number>(new Date().getFullYear())
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  // Data states
  const [balances, setBalances] = useState<Balance[]>([])
  const [estadosResultados, setEstadosResultados] = useState<EstadoResultados[]>([])
  const [certificadosDeuda, setCertificadosDeuda] = useState<CertificadoDeuda[]>([])
  const [checklistUnidades, setChecklistUnidades] = useState<ChecklistUnidad[]>([])
  const [resumenChecklist, setResumenChecklist] = useState<any>(null)
  
  // Modal states
  const [showGenerarModal, setShowGenerarModal] = useState(false)
  const [showCertificadoModal, setShowCertificadoModal] = useState(false)
  const [unidadSeleccionada, setUnidadSeleccionada] = useState<number | null>(null)

  const anios = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i)

  useEffect(() => {
    fetchEdificios()
  }, [])

  useEffect(() => {
    if (selectedEdificio) {
      fetchData()
    }
  }, [selectedEdificio, selectedAnio, activeTab])

  const fetchEdificios = async () => {
    try {
      const res = await api.get('/edificios')
      const data = res.data.data || res.data
      setEdificios(data)
      if (data.length > 0) {
        setSelectedEdificio(data[0].id)
      }
    } catch (err) {
      console.error('Error fetching edificios:', err)
      toast.error('Error al cargar edificios')
    }
  }

  const fetchData = async () => {
    setLoading(true)
    setError(null)

    try {
      switch (activeTab) {
        case 'balance':
          const balRes = await api.get('/contabilidad/balance-general', {
            params: { edificio_id: selectedEdificio, anio: selectedAnio }
          })
          setBalances(balRes.data.data || [])
          break

        case 'certificados':
          const certRes = await api.get('/certificados-deuda', {
            params: { edificio_id: selectedEdificio }
          })
          setCertificadosDeuda(certRes.data.data || [])
          break

        case 'checklist':
          const checkRes = await api.get(`/cumplimiento/checklist/edificio/${selectedEdificio}`, {
            params: { anio: selectedAnio }
          })
          setChecklistUnidades(checkRes.data.checklists || [])
          setResumenChecklist(checkRes.data.resumen || null)
          break
      }
    } catch (err: any) {
      const errorMsg = err.response?.data?.message || 'Error al cargar datos'
      setError(errorMsg)
      toast.error(errorMsg)
    } finally {
      setLoading(false)
    }
  }

  const generarBalanceGeneral = async () => {
    if (!selectedEdificio) return
    
    setLoading(true)
    setError(null)
    
    try {
      const res = await api.post('/contabilidad/balance-general/generar', {
        edificio_id: selectedEdificio,
        anio_tributario: selectedAnio,
        tipo: 'anual',
        fecha_inicio: `${selectedAnio}-01-01`,
        fecha_cierre: `${selectedAnio}-12-31`
      })

      toast.success('Balance General generado exitosamente')
      setSuccess('Balance General generado exitosamente')
      fetchData()
    } catch (err: any) {
      const errorMsg = err.response?.data?.message || 'Error al generar balance'
      setError(errorMsg)
      toast.error(errorMsg)
    } finally {
      setLoading(false)
      setShowGenerarModal(false)
    }
  }

  const generarEstadoResultados = async () => {
    if (!selectedEdificio) return
    
    setLoading(true)
    try {
      await api.post('/contabilidad/estado-resultados/generar', {
        edificio_id: selectedEdificio,
        anio_tributario: selectedAnio,
        tipo: 'anual',
        fecha_inicio: `${selectedAnio}-01-01`,
        fecha_cierre: `${selectedAnio}-12-31`
      })

      toast.success('Estado de Resultados generado')
      setSuccess('Estado de Resultados generado')
      fetchData()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al generar')
    } finally {
      setLoading(false)
    }
  }

  const generarDJ1887 = async () => {
    if (!selectedEdificio) return
    
    setLoading(true)
    try {
      const res = await api.post('/tributario/declaraciones-juradas/dj1887', {
        edificio_id: selectedEdificio,
        anio_tributario: selectedAnio
      })

      const cantidad = res.data.declaracion?.cantidad_informados || 0
      toast.success(`DJ 1887 generada: ${cantidad} informados`)
      setSuccess(`DJ 1887 generada: ${cantidad} informados`)
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al generar DJ')
    } finally {
      setLoading(false)
    }
  }

  const generarReporteDistribucion = async () => {
    if (!selectedEdificio) return
    
    setLoading(true)
    try {
      await api.post('/distribucion/reportes/consolidado', {
        edificio_id: selectedEdificio,
        anio: selectedAnio
      })

      toast.success('Reporte de distribución generado')
      setSuccess('Reporte de distribución generado')
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al generar reporte')
    } finally {
      setLoading(false)
    }
  }

  const generarCertificadoDeuda = async (tipo: string) => {
    if (!unidadSeleccionada) return
    
    setLoading(true)
    try {
      await api.post('/certificados-deuda/generar', {
        unidad_id: unidadSeleccionada,
        tipo: tipo,
        motivo_solicitud: 'Solicitud del sistema'
      })

      toast.success('Certificado generado')
      setSuccess('Certificado generado')
      setShowCertificadoModal(false)
      fetchData()
    } catch (err: any) {
      const errorMsg = err.response?.data?.error || 'Error al generar certificado'
      setError(errorMsg)
      toast.error(errorMsg)
    } finally {
      setLoading(false)
    }
  }

  const generarChecklistMasivo = async () => {
    if (!selectedEdificio) return
    
    setLoading(true)
    try {
      await api.post('/cumplimiento/checklist/generar-masivo', {
        edificio_id: selectedEdificio,
        anio: selectedAnio
      })

      toast.success('Checklist generado para todas las unidades')
      setSuccess('Checklist generado para todas las unidades')
      fetchData()
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al generar checklist')
    } finally {
      setLoading(false)
    }
  }

  const descargarPdf = async (endpoint: string, filename: string) => {
    try {
      const res = await api.get(endpoint, { responseType: 'blob' })
      
      const url = window.URL.createObjectURL(new Blob([res.data]))
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      a.click()
      window.URL.revokeObjectURL(url)
      toast.success('Descarga iniciada')
    } catch (err) {
      toast.error('Error al descargar')
    }
  }

  const formatMoney = (amount: number) => {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount)
  }

  const tabs = [
    { id: 'balance', label: 'Balance General', icon: ChartPieIcon },
    { id: 'eerr', label: 'Estado de Resultados', icon: ArrowTrendingUpIcon },
    { id: 'dj', label: 'Declaraciones Juradas', icon: DocumentTextIcon },
    { id: 'distribucion', label: 'Distribución', icon: UsersIcon },
    { id: 'certificados', label: 'Certificados Deuda', icon: DocumentCheckIcon },
    { id: 'checklist', label: 'Cumplimiento Legal', icon: ClipboardDocumentListIcon },
  ]

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <ShieldCheckIcon className="w-7 h-7 text-blue-600" />
          Reportes Tributarios y Cumplimiento
        </h1>
        <p className="text-gray-500 mt-1">
          Balance General, Estado de Resultados, DJ, Certificados y Cumplimiento Legal
        </p>
      </div>

      {/* Alerts */}
      {error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2 text-red-700">
          <ExclamationTriangleIcon className="w-5 h-5 flex-shrink-0" />
          <span className="flex-1">{error}</span>
          <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600">✕</button>
        </div>
      )}
      
      {success && (
        <div className="p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2 text-green-700">
          <CheckCircleIcon className="w-5 h-5 flex-shrink-0" />
          <span className="flex-1">{success}</span>
          <button onClick={() => setSuccess(null)} className="text-green-400 hover:text-green-600">✕</button>
        </div>
      )}

      {/* Filters */}
      <div className="card">
        <div className="card-body">
          <div className="flex flex-wrap gap-4 items-center">
            <div className="flex items-center gap-2">
              <BuildingOffice2Icon className="w-5 h-5 text-gray-400" />
              <select
                value={selectedEdificio || ''}
                onChange={(e) => setSelectedEdificio(Number(e.target.value))}
                className="input"
                disabled={loading}
              >
                <option value="">Seleccionar edificio</option>
                {edificios.map(e => (
                  <option key={e.id} value={e.id}>{e.nombre}</option>
                ))}
              </select>
            </div>
            
            <div className="flex items-center gap-2">
              <CalendarIcon className="w-5 h-5 text-gray-400" />
              <select
                value={selectedAnio}
                onChange={(e) => setSelectedAnio(Number(e.target.value))}
                className="input"
                disabled={loading}
              >
                {anios.map(a => (
                  <option key={a} value={a}>{a}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="card">
        <div className="border-b border-gray-200">
          <nav className="flex overflow-x-auto">
            {tabs.map(tab => {
              const Icon = tab.icon
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id as any)}
                  disabled={loading}
                  className={`flex items-center gap-2 px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                    activeTab === tab.id
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  } disabled:opacity-50`}
                >
                  <Icon className="w-4 h-4" />
                  {tab.label}
                </button>
              )
            })}
          </nav>
        </div>

        <div className="p-6">
          {loading && (
            <div className="flex justify-center py-8">
              <div className="w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full animate-spin" />
            </div>
          )}

          {!loading && (
            <>
              {/* Balance General */}
              {activeTab === 'balance' && (
                <div>
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Balance General {selectedAnio}</h2>
                    <button
                      onClick={generarBalanceGeneral}
                      disabled={!selectedEdificio}
                      className="btn-primary"
                    >
                      <DocumentTextIcon className="w-4 h-4 mr-2" />
                      Generar Balance
                    </button>
                  </div>

                  {balances.length > 0 ? (
                    <div className="space-y-4">
                      {balances.map(balance => (
                        <div key={balance.id} className="border rounded-lg p-4">
                          <div className="flex justify-between items-start">
                            <div>
                              <h3 className="font-semibold">Año {balance.anio_tributario}</h3>
                              <p className="text-sm text-gray-500 capitalize">{balance.tipo}</p>
                            </div>
                            <div className="flex items-center gap-2">
                              <span className={`badge ${balance.cuadrado ? 'badge-success' : 'badge-danger'}`}>
                                {balance.cuadrado ? '✓ Cuadrado' : '✗ Descuadrado'}
                              </span>
                              <button
                                onClick={() => descargarPdf(`/contabilidad/balance-general/${balance.id}/pdf`, `balance-${balance.anio_tributario}.pdf`)}
                                className="p-2 text-blue-600 hover:bg-blue-50 rounded"
                                title="Descargar PDF"
                              >
                                <ArrowDownTrayIcon className="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                          <div className="grid grid-cols-3 gap-4 mt-4">
                            <div className="bg-blue-50 p-3 rounded">
                              <p className="text-xs text-blue-600">Total Activos</p>
                              <p className="font-semibold text-blue-900">{formatMoney(balance.total_activos)}</p>
                            </div>
                            <div className="bg-red-50 p-3 rounded">
                              <p className="text-xs text-red-600">Total Pasivos</p>
                              <p className="font-semibold text-red-900">{formatMoney(balance.total_pasivos)}</p>
                            </div>
                            <div className="bg-green-50 p-3 rounded">
                              <p className="text-xs text-green-600">Patrimonio</p>
                              <p className="font-semibold text-green-900">{formatMoney(balance.total_patrimonio)}</p>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <ChartPieIcon className="w-12 h-12 mx-auto mb-2 opacity-50" />
                      <p>No hay balances generados para este período</p>
                      <button onClick={generarBalanceGeneral} disabled={!selectedEdificio} className="btn-primary mt-4">
                        Generar primer balance
                      </button>
                    </div>
                  )}
                </div>
              )}

              {/* Estado de Resultados */}
              {activeTab === 'eerr' && (
                <div>
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Estado de Resultados {selectedAnio}</h2>
                    <button
                      onClick={generarEstadoResultados}
                      disabled={!selectedEdificio}
                      className="btn-primary"
                    >
                      <ArrowTrendingUpIcon className="w-4 h-4 mr-2" />
                      Generar EERR
                    </button>
                  </div>
                  <div className="text-center py-8 text-gray-500">
                    <ArrowTrendingUpIcon className="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>Seleccione un edificio y genere el Estado de Resultados</p>
                  </div>
                </div>
              )}

              {/* Declaraciones Juradas */}
              {activeTab === 'dj' && (
                <div>
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Declaraciones Juradas {selectedAnio}</h2>
                    <button
                      onClick={generarDJ1887}
                      disabled={!selectedEdificio}
                      className="btn-primary"
                    >
                      <DocumentTextIcon className="w-4 h-4 mr-2" />
                      Generar DJ 1887
                    </button>
                  </div>
                  
                  <div className="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h3 className="font-semibold text-purple-800">DJ 1887 - Rentas Art. 42 N°1</h3>
                    <p className="text-sm text-purple-600 mt-1">
                      Declaración de rentas pagadas por arriendo de bienes comunes. 
                      Incluye montos del Art. 17 N°3 LIR (no constituyen renta).
                    </p>
                    <p className="text-xs text-purple-500 mt-2">
                      <strong>Vencimiento:</strong> 31 de marzo de {selectedAnio + 1}
                    </p>
                  </div>
                </div>
              )}

              {/* Distribución */}
              {activeTab === 'distribucion' && (
                <div>
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Reporte Consolidado de Distribución {selectedAnio}</h2>
                    <button
                      onClick={generarReporteDistribucion}
                      disabled={!selectedEdificio}
                      className="btn-primary"
                    >
                      <UsersIcon className="w-4 h-4 mr-2" />
                      Generar Reporte
                    </button>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="bg-orange-50 border border-orange-200 rounded-lg p-4">
                      <h3 className="font-semibold text-orange-800">Ingresos por Arriendos</h3>
                      <ul className="text-sm text-orange-600 mt-2 space-y-1">
                        <li>• Antenas de telecomunicaciones</li>
                        <li>• Publicidad en fachadas</li>
                        <li>• Espacios comunes</li>
                        <li>• Estacionamientos</li>
                      </ul>
                    </div>
                    <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                      <h3 className="font-semibold text-green-800">Certificados de Renta</h3>
                      <ul className="text-sm text-green-600 mt-2 space-y-1">
                        <li>• Individual por propiedad</li>
                        <li>• Consolidado multi-propiedad</li>
                        <li>• Detalle mensual de pagos</li>
                        <li>• Código de verificación</li>
                      </ul>
                    </div>
                  </div>
                </div>
              )}

              {/* Certificados de Deuda */}
              {activeTab === 'certificados' && (
                <div>
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Certificados de Deuda / No Deuda</h2>
                    <button
                      onClick={() => setShowCertificadoModal(true)}
                      disabled={!selectedEdificio}
                      className="btn-primary"
                    >
                      <DocumentCheckIcon className="w-4 h-4 mr-2" />
                      Nuevo Certificado
                    </button>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                      <DocumentCheckIcon className="w-8 h-8 mx-auto text-green-600 mb-2" />
                      <h3 className="font-semibold text-green-800">Certificado No Deuda</h3>
                      <p className="text-xs text-green-600 mt-1">Para unidades sin deuda pendiente</p>
                    </div>
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                      <CheckCircleIcon className="w-8 h-8 mx-auto text-blue-600 mb-2" />
                      <h3 className="font-semibold text-blue-800">Certificado Pago al Día</h3>
                      <p className="text-xs text-blue-600 mt-1">Confirma pagos regulares</p>
                    </div>
                    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                      <DocumentTextIcon className="w-8 h-8 mx-auto text-yellow-600 mb-2" />
                      <h3 className="font-semibold text-yellow-800">Estado de Cuenta</h3>
                      <p className="text-xs text-yellow-600 mt-1">Detalle de deuda si existe</p>
                    </div>
                  </div>

                  {certificadosDeuda.length > 0 ? (
                    <div className="card">
                      <div className="table-container">
                        <table className="table">
                          <thead>
                            <tr>
                              <th>N° Certificado</th>
                              <th>Unidad</th>
                              <th>Tipo</th>
                              <th>Estado</th>
                              <th>Válido hasta</th>
                              <th>Acciones</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-gray-200">
                            {certificadosDeuda.map(cert => (
                              <tr key={cert.id}>
                                <td className="font-mono text-sm">{cert.numero_certificado}</td>
                                <td>{cert.unidad_numero}</td>
                                <td className="capitalize">{cert.tipo.replace('_', ' ')}</td>
                                <td>
                                  <span className={`badge ${cert.tiene_deuda ? 'badge-danger' : 'badge-success'}`}>
                                    {cert.tiene_deuda ? `Deuda: ${formatMoney(cert.deuda_total)}` : 'Sin Deuda'}
                                  </span>
                                </td>
                                <td>{new Date(cert.fecha_validez).toLocaleDateString('es-CL')}</td>
                                <td>
                                  <button
                                    onClick={() => descargarPdf(`/certificados-deuda/${cert.id}/pdf`, `certificado-${cert.numero_certificado}.pdf`)}
                                    className="text-blue-600 hover:text-blue-800"
                                    title="Descargar PDF"
                                  >
                                    <ArrowDownTrayIcon className="w-4 h-4" />
                                  </button>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <DocumentCheckIcon className="w-12 h-12 mx-auto mb-2 opacity-50" />
                      <p>No hay certificados emitidos</p>
                    </div>
                  )}
                </div>
              )}

              {/* Checklist Cumplimiento */}
              {activeTab === 'checklist' && (
                <div>
                  <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Checklist de Cumplimiento Legal {selectedAnio}</h2>
                    <button
                      onClick={generarChecklistMasivo}
                      disabled={!selectedEdificio}
                      className="btn-primary"
                    >
                      <ClipboardDocumentListIcon className="w-4 h-4 mr-2" />
                      Generar Checklist
                    </button>
                  </div>

                  {resumenChecklist && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                      <div className="stat-card">
                        <p className="stat-label">Total Unidades</p>
                        <p className="stat-value">{resumenChecklist.total_unidades}</p>
                      </div>
                      <div className="stat-card">
                        <p className="stat-label">Cumplen</p>
                        <p className="stat-value text-green-600">{resumenChecklist.cumplen}</p>
                      </div>
                      <div className="stat-card">
                        <p className="stat-label">Parcial</p>
                        <p className="stat-value text-yellow-600">{resumenChecklist.cumplen_parcial}</p>
                      </div>
                      <div className="stat-card">
                        <p className="stat-label">No Cumplen</p>
                        <p className="stat-value text-red-600">{resumenChecklist.no_cumplen}</p>
                      </div>
                    </div>
                  )}

                  {checklistUnidades.length > 0 ? (
                    <div className="card">
                      <div className="table-container">
                        <table className="table">
                          <thead>
                            <tr>
                              <th>Unidad</th>
                              <th>Tipo</th>
                              <th>Cumplimiento</th>
                              <th>Estado</th>
                              <th>Alertas</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-gray-200">
                            {checklistUnidades.map(check => (
                              <tr key={check.id}>
                                <td className="font-semibold">{check.numero}</td>
                                <td className="capitalize">{check.tipo}</td>
                                <td>
                                  <div className="flex items-center gap-2">
                                    <div className="w-24 bg-gray-200 rounded-full h-2">
                                      <div 
                                        className={`h-2 rounded-full ${
                                          check.porcentaje_cumplimiento >= 90 ? 'bg-green-500' :
                                          check.porcentaje_cumplimiento >= 60 ? 'bg-yellow-500' : 'bg-red-500'
                                        }`}
                                        style={{ width: `${check.porcentaje_cumplimiento}%` }}
                                      />
                                    </div>
                                    <span className="text-sm">{check.porcentaje_cumplimiento}%</span>
                                  </div>
                                </td>
                                <td>
                                  <span className={`badge ${
                                    check.estado_general === 'cumple' ? 'badge-success' :
                                    check.estado_general === 'cumple_parcial' ? 'badge-warning' :
                                    'badge-danger'
                                  }`}>
                                    {check.estado_general === 'cumple' ? 'Cumple' :
                                     check.estado_general === 'cumple_parcial' ? 'Parcial' : 'No Cumple'}
                                  </span>
                                </td>
                                <td>
                                  {check.alertas && check.alertas.length > 0 && (
                                    <span className="text-xs text-red-600">
                                      {check.alertas.length} alerta(s)
                                    </span>
                                  )}
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  ) : (
                    <div className="text-center py-8 text-gray-500">
                      <ClipboardDocumentListIcon className="w-12 h-12 mx-auto mb-2 opacity-50" />
                      <p>Genere el checklist para ver el estado de cumplimiento</p>
                    </div>
                  )}
                </div>
              )}
            </>
          )}
        </div>
      </div>

      {/* Modal Certificado Deuda */}
      {showCertificadoModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
            <h3 className="text-lg font-semibold mb-4">Generar Certificado de Deuda</h3>
            
            <div className="mb-4">
              <label className="label">Unidad</label>
              <select
                value={unidadSeleccionada || ''}
                onChange={(e) => setUnidadSeleccionada(Number(e.target.value))}
                className="input"
              >
                <option value="">Seleccionar unidad</option>
                {/* Las unidades se cargarían dinámicamente desde el edificio seleccionado */}
              </select>
            </div>

            <div className="mb-4">
              <label className="label">Tipo de Certificado</label>
              <div className="space-y-2">
                <button
                  onClick={() => generarCertificadoDeuda('no_deuda')}
                  disabled={!unidadSeleccionada}
                  className="w-full text-left px-4 py-3 border rounded-lg hover:bg-green-50 hover:border-green-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span className="font-medium text-green-700">Certificado de No Deuda</span>
                  <p className="text-xs text-gray-500">Solo si la unidad no tiene deuda</p>
                </button>
                <button
                  onClick={() => generarCertificadoDeuda('pago_al_dia')}
                  disabled={!unidadSeleccionada}
                  className="w-full text-left px-4 py-3 border rounded-lg hover:bg-blue-50 hover:border-blue-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span className="font-medium text-blue-700">Certificado Pago al Día</span>
                  <p className="text-xs text-gray-500">Confirma pagos regulares</p>
                </button>
                <button
                  onClick={() => generarCertificadoDeuda('estado_cuenta')}
                  disabled={!unidadSeleccionada}
                  className="w-full text-left px-4 py-3 border rounded-lg hover:bg-yellow-50 hover:border-yellow-300 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span className="font-medium text-yellow-700">Estado de Cuenta</span>
                  <p className="text-xs text-gray-500">Incluye detalle de deuda si existe</p>
                </button>
              </div>
            </div>

            <div className="flex justify-end gap-2 mt-6">
              <button
                onClick={() => setShowCertificadoModal(false)}
                className="btn-secondary"
              >
                Cancelar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
