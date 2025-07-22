@extends('emails.layout')

@section('title', 'Recordatorio de Invitación')

@section('content')
    <div class="greeting">
        ¡Hola! 👋
    </div>
    
    <div class="warning-box">
        <p><strong>⏰ Recordatorio importante</strong></p>
        <p>{{ $customMessage }}</p>
    </div>
    
    <div class="message">
        <p>Recibiste una invitación para crear tu organización en <strong>{{ config('app.name') }}</strong>, pero aún no has completado el proceso.</p>
    </div>
    
    @if(isset($additionalData['days_left']))
        <div class="info-box">
            <p><strong>📅 Tiempo restante:</strong></p>
            <p>Tu invitación expira en <strong>{{ $additionalData['days_left'] }} {{ $additionalData['days_left'] == 1 ? 'día' : 'días' }}</strong></p>
            @if(isset($additionalData['expires_at']))
                <p><small>Fecha de expiración: {{ $additionalData['expires_at'] }}</small></p>
            @endif
        </div>
    @endif
    
    <div class="message">
        <p><strong>🚀 ¿Por qué completar tu registro?</strong></p>
        <ul style="margin-left: 20px; margin-top: 10px;">
            <li>✅ Gestiona eventos de manera profesional</li>
            <li>✅ Accede a herramientas avanzadas de promoción</li>
            <li>✅ Forma parte del directorio oficial de eventos de Tucumán</li>
            <li>✅ Recibe soporte técnico especializado</li>
        </ul>
    </div>
    
    <div style="text-align: center;">
        @if(isset($additionalData['invitation_url']))
            <a href="{{ $additionalData['invitation_url'] }}" class="btn">
                🎯 Completar Registro Ahora
            </a>
        @endif
    </div>
    
    @if(isset($additionalData['days_left']) && $additionalData['days_left'] <= 3)
        <div class="error-box">
            <p><strong>🚨 ¡Últimos días!</strong></p>
            <p>Tu invitación expira muy pronto. No pierdas esta oportunidad de formar parte del ecosistema de eventos más importante de Tucumán.</p>
        </div>
    @endif
    
    <div class="message">
        <p><strong>⚡ El proceso es rápido y sencillo:</strong></p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li>Completa la información de tu organización (5 minutos)</li>
            <li>Ingresa los datos del administrador</li>
            <li>Envía tu solicitud para revisión</li>
            <li>¡Listo! Recibirás una respuesta en 24-48 horas</li>
        </ol>
    </div>
    
    <div class="info-box">
        <p><strong>❓ ¿Tienes dudas?</strong></p>
        <p>Si necesitas ayuda con el proceso de registro o tienes preguntas sobre los beneficios, contáctanos en <strong>soporte@enteturismo.com</strong>.</p>
    </div>
    
    <div class="divider"></div>
    
    <div style="font-size: 14px; color: #64748b;">
        <p><strong>¿No puedes hacer clic en el botón?</strong> Copia y pega este enlace en tu navegador:</p>
        <p style="word-break: break-all; margin-top: 5px;">{{ $additionalData['invitation_url'] ?? '' }}</p>
    </div>
@endsection
