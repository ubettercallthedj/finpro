<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rut' => $this->rut,
            'tipo_persona' => $this->tipo_persona,
            'nombre_completo' => $this->nombre_completo,
            'nombre' => $this->when($this->tipo_persona === 'natural', $this->nombre),
            'apellido_paterno' => $this->when($this->tipo_persona === 'natural', $this->apellido_paterno),
            'apellido_materno' => $this->when($this->tipo_persona === 'natural', $this->apellido_materno),
            'razon_social' => $this->when($this->tipo_persona === 'juridica', $this->razon_social),
            'contacto' => [
                'email' => $this->email,
                'telefono' => $this->telefono,
                'telefono_secundario' => $this->telefono_secundario,
                'direccion' => $this->direccion,
                'comuna' => $this->comuna,
            ],
            'datos_personales' => $this->when($this->tipo_persona === 'natural', [
                'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
                'edad' => $this->fecha_nacimiento?->age,
                'sexo' => $this->sexo,
                'nacionalidad' => $this->nacionalidad,
                'estado_civil' => $this->estado_civil,
            ]),
            'datos_bancarios' => $this->when(
                $request->user()?->can('ver_datos_bancarios') || $request->user()?->id === $this->user_id,
                [
                    'banco' => $this->banco,
                    'tipo_cuenta' => $this->tipo_cuenta,
                    'numero_cuenta' => $this->numero_cuenta,
                ]
            ),
            'unidades_propietario' => $this->whenLoaded('unidadesPropietario', function () {
                return $this->unidadesPropietario->map(function ($unidad) {
                    return [
                        'id' => $unidad->id,
                        'numero' => $unidad->numero,
                        'edificio' => $unidad->edificio->nombre ?? null,
                    ];
                });
            }),
            'user' => $this->whenLoaded('user', [
                'id' => $this->user->id,
                'email' => $this->user->email,
            ]),
            'activo' => (bool) $this->activo,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
