@extends('emails.layout')

@section('title', 'Correcciones Requeridas')

@section('content')
    <div class="greeting">
        Hola {{ $adminName }},
    </div>
    
    <div class="warning-box">
        <p><strong>🔄 Correcciones requeridas</strong></p>
        <p>Tu solicitud para crear la organización <strong>"{{ $organizationName }}"</strong> requiere algunas correcciones antes de poder ser aprobada.</p>
    </div>
    
    <div class="message">
        <p>{{ $customMessage }}</p>
    </div>
    
    @if(isset($additionalData['corrections']))
        <div class="info-box">
            <p><strong>📋 Correcciones específicas solicitadas:</strong></p>
            <div style="background: #f8fafc; padding: 15px; border-radius: 6px; margin-top: 10px;">
                {!! nl2br(e($additionalData['corrections'])) !!}
            </div>
        </div>
    @endif
    
    <div class="message">
        <p><strong>✏️ ¿Cómo proceder?</strong></p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li>Revisa cuidadosamente las correcciones solicitadas</li>
            <li>Prepara la información actualizada</li>
            <li>Accede al formulario usando el botón de abajo</li>
            <li>Completa los campos con la información corregida</li>
            <li>Reenvía tu solicitud para revisión</li>
        </ol>
    </div>
    
    <div style="text-align: center;">
        @if(isset($additionalData['correction_url']))
            <a href="{{ $additionalData['correction_url'] }}" class="btn btn-warning">
                🔧 Corregir Solicitud
            </a>
        @endif
    </div>
    
    <div class="warning-box">
        <p><strong>⏰ Tiempo límite:</strong></p>
        <p>Tu enlace de corrección tiene una validez limitada. Te recomendamos realizar las correcciones lo antes posible.</p>
    </div>
    
    <div class="info-box">
        <p><strong>💡 ¿Necesitas ayuda?</strong></p>
        <p>Si no entiendes alguna de las correcciones solicitadas o necesitas orientación adicional, contáctanos en <strong>soporte@enteturismo.com</strong>.</p>
    </div>
    
    <div class="message">
        <p>Agradecemos tu paciencia y colaboración para mejorar tu solicitud. Estamos aquí para ayudarte en este proceso.</p>
    </div>
    
    <div class="divider"></div>
    
    <div style="font-size: 14px; color: #64748b;">
        <p><strong>¿No puedes hacer clic en el botón?</strong> Copia y pega este enlace en tu navegador:</p>
        <p style="word-break: break-all; margin-top: 5px;">{{ $additionalData['correction_url'] ?? '' }}</p>
    </div>
@endsection
