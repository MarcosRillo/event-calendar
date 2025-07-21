<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use App\Models\Invitation;

class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $invitation;
    public $invitationUrl;
    public $customMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation, string $invitationUrl, ?string $customMessage = null)
    {
        $this->invitation = $invitation;
        $this->invitationUrl = $invitationUrl;
        $this->customMessage = $customMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Invitación para crear tu organización - ' . config('app.name'),
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
            view: 'emails.organization-invitation',
            with: [
                'invitation' => $this->invitation,
                'invitationUrl' => $this->invitationUrl,
                'customMessage' => $this->customMessage,
                'expiresAt' => $this->invitation->expires_at->format('d/m/Y H:i'),
                'daysLeft' => now()->diffInDays($this->invitation->expires_at, false)
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
