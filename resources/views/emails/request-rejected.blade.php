@extends('emails.layout')

@section('title', 'Solicitud Rechazada')

@section('content')
    <div class="greeting">
        Hola {{ $adminName }},
    </div>
    
    <div class="error-box">
        <p><strong>❌ Solicitud no aprobada</strong></p>
        <p>Lamentamos informarte que tu solicitud para crear la organización <strong>"{{ $organizationName }}"</strong> no ha sido aprobada en esta ocasión.</p>
    </div>
    
    <div class="message">
        <p><strong>📝 Motivo:</strong></p>
        <p>{{ $message }}</p>
    </div>
    
    @if(isset($additionalData['reason']) && $additionalData['reason'])
        <div class="info-box">
            <p><strong>📋 Detalles adicionales:</strong></p>
            <p>{{ $additionalData['reason'] }}</p>
        </div>
    @endif
    
    <div class="message">
        <p><strong>🔄 ¿Qué puedes hacer ahora?</strong></p>
        <ul style="margin-left: 20px; margin-top: 10px;">
            <li>✅ Revisar los criterios de selección</li>
            <li>✅ Preparar una nueva solicitud con información actualizada</li>
            <li>✅ Contactar a nuestro equipo de soporte para obtener orientación</li>
        </ul>
    </div>
    
    <div class="info-box">
        <p><strong>💡 Próximos pasos:</strong></p>
        <p>Si crees que hay un error en esta decisión o necesitas más información sobre los criterios de aprobación, no dudes en contactarnos en <strong>soporte@enteturismo.com</strong>.</p>
    </div>
    
    <div class="message">
        <p>Agradecemos tu interés en formar parte del ecosistema de eventos de Tucumán. Te animamos a volver a intentarlo cuando tengas la información necesaria.</p>
    </div>
    
    <div style="text-align: center;">
        <a href="mailto:soporte@enteturismo.com" class="btn">
            📧 Contactar Soporte
        </a>
    </div>
@endsection
