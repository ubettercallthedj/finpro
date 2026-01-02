<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'boleta_id' => 'required|exists:boletas_gc,id',
            'monto' => 'required|numeric|min:1',
            'fecha_pago' => 'required|date|before_or_equal:today',
            'medio_pago' => 'required|in:efectivo,transferencia,cheque,tarjeta,pac,webpay,otro',
            'referencia' => 'nullable|string|max:100',
            'banco' => 'nullable|string|max:100',
            'numero_operacion' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'boleta_id.required' => 'La boleta es obligatoria',
            'boleta_id.exists' => 'La boleta no existe',
            'monto.required' => 'El monto es obligatorio',
            'monto.min' => 'El monto debe ser mayor a 0',
            'fecha_pago.required' => 'La fecha de pago es obligatoria',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser futura',
            'medio_pago.required' => 'El medio de pago es obligatorio',
            'medio_pago.in' => 'Medio de pago inválido',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->filled('boleta_id')) {
                $boleta = \DB::table('boletas_gc')->find($this->boleta_id);
                
                if ($boleta) {
                    $saldoPendiente = $boleta->total_a_pagar - ($boleta->total_abonos ?? 0);
                    
                    if ($this->monto > $saldoPendiente) {
                        $validator->errors()->add('monto', 
                            "El monto excede el saldo pendiente ($" . number_format($saldoPendiente, 0, ',', '.') . ")"
                        );
                    }
                    
                    if ($boleta->estado === 'anulada') {
                        $validator->errors()->add('boleta_id', 'No se puede registrar pago en una boleta anulada');
                    }
                }
            }
        });
    }
}
