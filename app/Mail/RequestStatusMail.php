<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use App\Models\Invitation;

class RequestStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $invitation;
    public $status;
    public $message;
    public $additionalData;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation, string $status, string $message, array $additionalData = [])
    {
        $this->invitation = $invitation;
        $this->status = $status;
        $this->message = $message;
        $this->additionalData = $additionalData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subjects = [
            'approved' => '✅ Solicitud Aprobada - Bienvenido a ' . config('app.name'),
            'rejected' => '❌ Solicitud Rechazada - ' . config('app.name'),
            'corrections_needed' => '🔄 Correcciones Requeridas - ' . config('app.name'),
            'expiration_reminder' => '⏰ Recordatorio de Expiración - ' . config('app.name'),
        ];

        return new Envelope(
            subject: $subjects[$this->status] ?? 'Actualización de Solicitud - ' . config('app.name'),
            from: new Address(config('mail.from.address'), config('app.name')),
            replyTo: [
                new Address('soporte@enteturismo.com', 'Soporte EnteT')
            ]
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $viewMap = [
            'approved' => 'emails.request-approved',
            'rejected' => 'emails.request-rejected',
            'corrections_needed' => 'emails.request-corrections',
            'expiration_reminder' => 'emails.request-reminder',
        ];

        return new Content(
            view: $viewMap[$this->status] ?? 'emails.request-status',
            with: [
                'invitation' => $this->invitation,
                'status' => $this->status,
                'customMessage' => $this->message,
                'organizationName' => $this->invitation->organizationData?->name,
                'adminName' => $this->invitation->adminData ? 
                    $this->invitation->adminData->first_name . ' ' . $this->invitation->adminData->last_name : 
                    null,
                'additionalData' => $this->additionalData
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
