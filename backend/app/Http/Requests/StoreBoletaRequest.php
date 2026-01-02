<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Manejar con middleware auth
    }

    public function rules(): array
    {
        return [
            'unidad_id' => 'required|exists:unidades,id',
            'periodo_id' => 'required|exists:periodos_gc,id',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'required|date|after:fecha_emision',
            'cargos' => 'required|array|min:1',
            'cargos.*.concepto_id' => 'required|exists:conceptos_gc,id',
            'cargos.*.descripcion' => 'required|string|max:200',
            'cargos.*.monto' => 'required|numeric|min:0',
            'saldo_anterior' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'unidad_id.required' => 'La unidad es obligatoria',
            'unidad_id.exists' => 'La unidad no existe',
            'periodo_id.required' => 'El período es obligatorio',
            'fecha_vencimiento.after' => 'La fecha de vencimiento debe ser posterior a la emisión',
            'cargos.required' => 'Debe incluir al menos un cargo',
            'cargos.*.concepto_id.required' => 'Cada cargo debe tener un concepto',
            'cargos.*.monto.min' => 'El monto debe ser mayor a 0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que no exista boleta duplicada para la unidad en ese período
            $existe = \DB::table('boletas_gc')
                ->where('unidad_id', $this->unidad_id)
                ->where('periodo_id', $this->periodo_id)
                ->exists();

            if ($existe) {
                $validator->errors()->add('unidad_id', 'Ya existe una boleta para esta unidad en este período');
            }
        });
    }
}
