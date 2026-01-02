<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnidadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'tipo' => $this->tipo,
            'piso' => $this->piso,
            'superficie' => [
                'util' => $this->superficie_util,
                'terraza' => $this->superficie_terraza,
                'total' => $this->superficie_total,
            ],
            'prorrateo' => (float) $this->prorrateo,
            'prorrateo_porcentaje' => number_format($this->prorrateo * 100, 4) . '%',
            'rol_avaluo' => $this->rol_avaluo,
            'avaluo_fiscal' => $this->avaluo_fiscal,
            'caracteristicas' => [
                'dormitorios' => $this->dormitorios,
                'banos' => $this->banos,
                'estacionamientos' => $this->estacionamientos,
                'bodegas' => $this->bodegas,
            ],
            'propietario' => $this->whenLoaded('propietario', function () {
                return [
                    'id' => $this->propietario->id,
                    'nombre' => $this->propietario->nombre_completo,
                    'rut' => $this->propietario->rut,
                    'email' => $this->propietario->email,
                    'telefono' => $this->propietario->telefono,
                ];
            }),
            'residente' => $this->whenLoaded('residente', function () {
                return [
                    'id' => $this->residente->id,
                    'nombre' => $this->residente->nombre_completo,
                    'email' => $this->residente->email,
                ];
            }),
            'edificio' => $this->whenLoaded('edificio', function () {
                return [
                    'id' => $this->edificio->id,
                    'nombre' => $this->edificio->nombre,
                    'direccion' => $this->edificio->direccion,
                ];
            }),
            'estado_financiero' => $this->when(isset($this->saldo_deuda), [
                'saldo_deuda' => $this->saldo_deuda ?? 0,
                'tiene_deuda' => ($this->saldo_deuda ?? 0) > 0,
            ]),
            'activa' => (bool) $this->activa,
            'fecha_compra' => $this->fecha_compra?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
