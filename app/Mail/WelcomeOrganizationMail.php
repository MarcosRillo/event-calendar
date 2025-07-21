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

class WelcomeOrganizationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $invitation;
    public $tempPassword;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation, string $tempPassword)
    {
        $this->invitation = $invitation;
        $this->tempPassword = $tempPassword;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🚀 ¡Bienvenido a ' . config('app.name') . '! - Credenciales de Acceso',
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
        return new Content(
            view: 'emails.welcome-organization',
            with: [
                'invitation' => $this->invitation,
                'organizationName' => $this->invitation->organizationData->name,
                'adminName' => $this->invitation->adminData->first_name . ' ' . $this->invitation->adminData->last_name,
                'adminEmail' => $this->invitation->adminData->email,
                'tempPassword' => $this->tempPassword,
                'loginUrl' => config('app.frontend_url') . '/login',
                'dashboardUrl' => config('app.frontend_url') . '/dashboard'
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
