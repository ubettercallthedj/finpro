<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class AsistenteLegalController extends Controller
{
    public function index(): JsonResponse
    {
        $consultas = DB::table('consultas_legal')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($consultas);
    }

    public function consultar(Request $request): JsonResponse
    {
        $request->validate([
            'consulta' => 'required|string|max:2000',
        ]);

        // Buscar respuesta en base de conocimiento
        $respuesta = $this->buscarRespuesta($request->consulta);

        $id = DB::table('consultas_legal')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'user_id' => Auth::id(),
            'categoria_id' => $request->categoria_id,
            'consulta' => $request->consulta,
            'respuesta' => $respuesta['texto'],
            'referencias_legales' => json_encode($respuesta['referencias']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $id,
            'respuesta' => $respuesta['texto'],
            'referencias' => $respuesta['referencias'],
        ]);
    }

    private function buscarRespuesta(string $consulta): array
    {
        // Base de conocimiento simplificada
        $keywords = strtolower($consulta);

        if (str_contains($keywords, 'morosidad') || str_contains($keywords, 'moroso')) {
            return [
                'texto' => 'Según el Art. 14 de la Ley 21.442, el copropietario moroso no puede votar en asambleas. El administrador puede aplicar intereses de hasta 1.5% mensual según el reglamento.',
                'referencias' => ['Ley 21.442 Art. 14', 'Ley 21.442 Art. 5'],
            ];
        }

        if (str_contains($keywords, 'asamblea') || str_contains($keywords, 'quorum')) {
            return [
                'texto' => 'Las asambleas ordinarias requieren quórum de 50% en primera citación. En segunda citación (30 min después) se puede sesionar con los presentes. Las extraordinarias pueden requerir quórums especiales según la materia (Art. 17 Ley 21.442).',
                'referencias' => ['Ley 21.442 Art. 17', 'Ley 21.442 Art. 18'],
            ];
        }

        if (str_contains($keywords, 'arriendo') || str_contains($keywords, 'antena')) {
            return [
                'texto' => 'El arriendo de espacios comunes (como azoteas para antenas) requiere acuerdo de asamblea extraordinaria con quórum de 75%. Los ingresos deben distribuirse proporcionalmente según prorrateo (Ley 21.713).',
                'referencias' => ['Ley 21.442 Art. 17', 'Ley 21.713', 'Art. 17 N°3 LIR'],
            ];
        }

        if (str_contains($keywords, 'distribución') || str_contains($keywords, 'certificado')) {
            return [
                'texto' => 'Según Ley 21.713, los ingresos por arriendo de bienes comunes deben distribuirse y certificarse anualmente. Los montos proporcionales al derecho de cada copropietario están exentos de impuesto (Art. 17 N°3 LIR).',
                'referencias' => ['Ley 21.713', 'Art. 17 N°3 LIR', 'Circular SII 42/2024'],
            ];
        }

        if (str_contains($keywords, 'fondo de reserva')) {
            return [
                'texto' => 'El fondo de reserva debe ser al menos 5% del presupuesto anual (Art. 8 Ley 21.442). Solo puede usarse para gastos extraordinarios o mantenciones mayores, previo acuerdo de asamblea.',
                'referencias' => ['Ley 21.442 Art. 8'],
            ];
        }

        return [
            'texto' => 'Su consulta ha sido registrada. Le recomendamos revisar la Ley 21.442 de Copropiedad Inmobiliaria y su reglamento (DS 7/2025) para información detallada.',
            'referencias' => ['Ley 21.442', 'DS 7/2025 MINVU'],
        ];
    }

    public function categorias(): JsonResponse
    {
        $categorias = DB::table('categorias_legal')
            ->where('activa', true)
            ->orderBy('orden')
            ->get();

        return response()->json($categorias);
    }

    public function faq(): JsonResponse
    {
        $faq = [
            ['pregunta' => '¿Cuál es el quórum para aprobar gastos extraordinarios?', 'categoria' => 'asambleas'],
            ['pregunta' => '¿Cómo se calcula el interés por mora?', 'categoria' => 'gastos-comunes'],
            ['pregunta' => '¿Qué porcentaje requiere aprobar arriendo de espacios comunes?', 'categoria' => 'arriendos'],
            ['pregunta' => '¿Cómo debo distribuir los ingresos por arriendo de antenas?', 'categoria' => 'tributario'],
            ['pregunta' => '¿Cuál es el mínimo del fondo de reserva?', 'categoria' => 'gastos-comunes'],
        ];

        return response()->json($faq);
    }

    public function oficios(Request $request): JsonResponse
    {
        $oficios = DB::table('oficios')
            ->join('instituciones', 'oficios.institucion_id', '=', 'instituciones.id')
            ->where('oficios.tenant_id', Auth::user()->tenant_id)
            ->when($request->estado, fn($q) => $q->where('oficios.estado', $request->estado))
            ->select('oficios.*', 'instituciones.nombre as institucion')
            ->orderByDesc('oficios.fecha')
            ->paginate(20);

        return response()->json($oficios);
    }

    public function crearOficio(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'institucion_id' => 'required|exists:instituciones,id',
            'tipo' => 'required|in:consulta,reclamo,denuncia,solicitud,fiscalizacion,otro',
            'asunto' => 'required|string|max:300',
            'contenido' => 'required|string',
        ]);

        $numero = sprintf('OF-%04d-%06d', now()->year, DB::table('oficios')->count() + 1);

        $id = DB::table('oficios')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'plantilla_id' => $request->plantilla_id,
            'institucion_id' => $request->institucion_id,
            'numero_oficio' => $numero,
            'fecha' => now(),
            'tipo' => $request->tipo,
            'asunto' => $request->asunto,
            'contenido' => $request->contenido,
            'fundamentos_legales' => $request->fundamentos_legales,
            'peticion_concreta' => $request->peticion_concreta,
            'estado' => 'borrador',
            'creado_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Oficio creado', 'id' => $id, 'numero' => $numero], 201);
    }

    public function showOficio(int $id): JsonResponse
    {
        $oficio = DB::table('oficios')
            ->join('instituciones', 'oficios.institucion_id', '=', 'instituciones.id')
            ->join('edificios', 'oficios.edificio_id', '=', 'edificios.id')
            ->where('oficios.id', $id)
            ->select('oficios.*', 'instituciones.nombre as institucion', 'edificios.nombre as edificio')
            ->first();

        return $oficio
            ? response()->json($oficio)
            : response()->json(['message' => 'No encontrado'], 404);
    }

    public function oficioPdf(int $id)
    {
        $oficio = DB::table('oficios')
            ->join('instituciones', 'oficios.institucion_id', '=', 'instituciones.id')
            ->join('edificios', 'oficios.edificio_id', '=', 'edificios.id')
            ->where('oficios.id', $id)
            ->first();

        $pdf = Pdf::loadView('pdf.oficio', compact('oficio'));
        return $pdf->download("oficio-{$oficio->numero_oficio}.pdf");
    }

    public function plantillas(): JsonResponse
    {
        $plantillas = DB::table('plantillas_oficio')
            ->where('activa', true)
            ->orderBy('tipo')
            ->get();

        return response()->json($plantillas);
    }

    public function instituciones(): JsonResponse
    {
        $instituciones = DB::table('instituciones')
            ->where('activa', true)
            ->orderBy('nombre')
            ->get();

        return response()->json($instituciones);
    }

    public function certificados(Request $request): JsonResponse
    {
        $certificados = DB::table('certificados_cumplimiento')
            ->join('edificios', 'certificados_cumplimiento.edificio_id', '=', 'edificios.id')
            ->where('certificados_cumplimiento.tenant_id', Auth::user()->tenant_id)
            ->select('certificados_cumplimiento.*', 'edificios.nombre as edificio')
            ->orderByDesc('fecha_emision')
            ->paginate(20);

        return response()->json($certificados);
    }

    public function generarCertificado(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'tipo' => 'required|in:cumplimiento_general,tributario,ley_21442,transparencia,deuda',
        ]);

        $edificio = DB::table('edificios')->where('id', $request->edificio_id)->first();
        $numero = sprintf('CERT-%04d-%06d', now()->year, DB::table('certificados_cumplimiento')->count() + 1);
        $codigo = strtoupper(substr(md5($numero . now()), 0, 12));

        $contenido = $this->generarContenidoCertificado($request->tipo, $edificio);

        $id = DB::table('certificados_cumplimiento')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'numero_certificado' => $numero,
            'codigo_verificacion' => $codigo,
            'fecha_emision' => now(),
            'fecha_validez' => now()->addMonths(3),
            'tipo' => $request->tipo,
            'titulo' => $this->getTituloCertificado($request->tipo),
            'contenido' => $contenido,
            'emitido_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Certificado generado', 'id' => $id, 'codigo' => $codigo], 201);
    }

    private function getTituloCertificado(string $tipo): string
    {
        return match($tipo) {
            'cumplimiento_general' => 'Certificado de Cumplimiento General',
            'tributario' => 'Certificado de Cumplimiento Tributario',
            'ley_21442' => 'Certificado de Cumplimiento Ley 21.442',
            'transparencia' => 'Certificado de Transparencia',
            'deuda' => 'Certificado de Deuda',
            default => 'Certificado',
        };
    }

    private function generarContenidoCertificado(string $tipo, object $edificio): string
    {
        return "Se certifica que la comunidad {$edificio->nombre}, RUT {$edificio->rut}, cumple con las obligaciones correspondientes al tipo: {$tipo}.";
    }

    public function verificarCertificado(string $codigo): JsonResponse
    {
        $certificado = DB::table('certificados_cumplimiento')
            ->join('edificios', 'certificados_cumplimiento.edificio_id', '=', 'edificios.id')
            ->where('certificados_cumplimiento.codigo_verificacion', $codigo)
            ->select(
                'certificados_cumplimiento.numero_certificado',
                'certificados_cumplimiento.tipo',
                'certificados_cumplimiento.fecha_emision',
                'certificados_cumplimiento.fecha_validez',
                'edificios.nombre as edificio'
            )
            ->first();

        if (!$certificado) {
            return response()->json(['valido' => false, 'message' => 'Certificado no encontrado']);
        }

        $vigente = $certificado->fecha_validez >= now()->toDateString();

        return response()->json([
            'valido' => true,
            'vigente' => $vigente,
            'certificado' => $certificado,
        ]);
    }
}
