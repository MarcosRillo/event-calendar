@extends('emails.layout')

@section('title', 'Invitación para crear organización')

@section('content')
    <div class="greeting">
        ¡Hola! 👋
    </div>
    
    <div class="message">
        <p>Has sido invitado/a a crear una organización en <strong>{{ config('app.name') }}</strong>, el sistema de gestión de eventos del Ente de Turismo de Tucumán.</p>
        
        @if($customMessage)
            <div class="info-box">
                <strong>Mensaje personalizado:</strong><br>
                {{ $customMessage }}
            </div>
        @endif
        
        <p>Para completar tu solicitud y crear tu organización, simplemente haz clic en el botón de abajo:</p>
    </div>
    
    <div style="text-align: center;">
        <a href="{{ $invitationUrl }}" class="btn">
            🚀 Crear Mi Organización
        </a>
    </div>
    
    <div class="warning-box">
        <p><strong>⏰ ¡Importante!</strong></p>
        <p>Esta invitación expira el <strong>{{ $expiresAt }}</strong> 
        @if($daysLeft > 0)
            ({{ $daysLeft }} {{ $daysLeft == 1 ? 'día' : 'días' }} restantes).
        @else
            <span style="color: #dc2626;">(¡Ya expiró!)</span>
        @endif
        </p>
    </div>
    
    <div class="message">
        <p><strong>¿Qué puedes hacer después de crear tu organización?</strong></p>
        <ul style="margin-left: 20px; margin-top: 10px;">
            <li>✅ Gestionar eventos de tu organización</li>
            <li>✅ Crear formularios personalizados</li>
            <li>✅ Administrar tu equipo</li>
            <li>✅ Personalizar el estilo de tu organización</li>
        </ul>
    </div>
    
    <div class="divider"></div>
    
    <div style="font-size: 14px; color: #64748b;">
        <p><strong>¿No puedes hacer clic en el botón?</strong> Copia y pega este enlace en tu navegador:</p>
        <p style="word-break: break-all; margin-top: 5px;">{{ $invitationUrl }}</p>
    </div>
@endsection
