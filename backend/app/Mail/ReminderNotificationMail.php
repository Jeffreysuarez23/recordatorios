<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reminder;

    public function __construct(Reminder $reminder)
    {
        $this->reminder = $reminder;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: ' . $this->reminder->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtmlString(),
        );
    }

    private function buildHtmlString()
    {
        return "
            <h2>Recordatorio de tus tareas</h2>
            <p><strong>Título:</strong> {$this->reminder->titulo}</p>
            <p><strong>Fecha:</strong> {$this->reminder->fecha}</p>
            <p><strong>Hora:</strong> {$this->reminder->hora}</p>
            " . ($this->reminder->lugar ? "<p><strong>Lugar:</strong> {$this->reminder->lugar}</p>" : "") . "
            " . ($this->reminder->descripcion ? "<p><strong>Notas:</strong> {$this->reminder->descripcion}</p>" : "") . "
            <br>
            <p>Este es un mensaje automático de tu app de recordatorios.</p>
        ";
    }

    public function attachments(): array
    {
        return [];
    }
}
