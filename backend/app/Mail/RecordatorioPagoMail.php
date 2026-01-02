<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class RecordatorioPagoMail extends Mailable
{
    use Queueable, SerializesModels;

    public $boleta;

    public function __construct($boleta)
    {
        $this->boleta = $boleta;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Recordatorio: Vencimiento de Gastos Comunes - {$this->boleta->edificio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recordatorio-pago',
            with: [
                'propietario' => $this->boleta->propietario,
                'edificio' => $this->boleta->edificio,
                'unidad' => $this->boleta->unidad,
                'numero_boleta' => $this->boleta->numero_boleta,
                'monto' => number_format($this->boleta->total_a_pagar, 0, ',', '.'),
                'fecha_vencimiento' => date('d/m/Y', strtotime($this->boleta->fecha_vencimiento)),
                'periodo' => $this->getPeriodo(),
                'dias_restantes' => now()->diffInDays($this->boleta->fecha_vencimiento, false),
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        // Adjuntar PDF de la boleta si existe
        if ($this->boleta->archivo_pdf && \Storage::exists($this->boleta->archivo_pdf)) {
            $attachments[] = Attachment::fromStorage($this->boleta->archivo_pdf)
                ->as("boleta-{$this->boleta->numero_boleta}.pdf")
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    protected function getPeriodo(): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        return ($meses[$this->boleta->mes] ?? $this->boleta->mes) . ' ' . $this->boleta->anio;
    }
}
