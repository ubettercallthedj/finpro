<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request) {
        return response()->json([
            'total_edificios' => 0,
            'total_unidades' => 0,
            'total_recaudado_mes' => 0,
            'morosidad_total' => 0,
        ]);
    }
    
    public function morosidad(Request $request) {
        return response()->json(['data' => []]);
    }
    
    public function ingresos(Request $request) {
        return response()->json(['data' => []]);
    }
    
    public function alertas(Request $request) {
        return response()->json(['data' => []]);
    }
}
