@extends('emails.layout')

@section('title', 'Solicitud Aprobada')

@section('content')
    <div class="greeting">
        ¡Felicidades, {{ $adminName }}! 🎉
    </div>
    
    <div class="success-box">
        <p><strong>✅ Tu solicitud ha sido aprobada</strong></p>
        <p>Tu organización <strong>"{{ $organizationName }}"</strong> ha sido creada exitosamente en {{ config('app.name') }}.</p>
    </div>
    
    <div class="message">
        <p>{{ $message }}</p>
    </div>
    
    @if(isset($additionalData['temp_password']))
        <div class="credentials">
            <p><strong>🔑 Credenciales de Acceso</strong></p>
            <p><strong>Email:</strong> {{ $invitation->adminData->email }}</p>
            <p><strong>Contraseña temporal:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 3px;">{{ $additionalData['temp_password'] }}</code></p>
        </div>
        
        <div class="warning-box">
            <p><strong>⚠️ Importante:</strong> Por favor, cambia tu contraseña después del primer inicio de sesión por motivos de seguridad.</p>
        </div>
    @endif
    
    <div style="text-align: center;">
        @if(isset($additionalData['login_url']))
            <a href="{{ $additionalData['login_url'] }}" class="btn btn-success">
                🚀 Iniciar Sesión
            </a>
        @endif
    </div>
    
    <div class="message">
        <p><strong>🎯 Próximos pasos:</strong></p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li>Inicia sesión con las credenciales proporcionadas</li>
            <li>Cambia tu contraseña en la configuración de tu perfil</li>
            <li>Personaliza la información de tu organización</li>
            <li>¡Comienza a crear tus eventos!</li>
        </ol>
    </div>
    
    <div class="info-box">
        <p><strong>📚 ¿Necesitas ayuda?</strong></p>
        <p>Consulta nuestra documentación o contáctanos en soporte@enteturismo.com para recibir asistencia personalizada.</p>
    </div>
    
    <div class="message">
        <p>¡Bienvenido/a al ecosistema de eventos de Tucumán! Estamos emocionados de verte crecer junto a nosotros.</p>
    </div>
@endsection
