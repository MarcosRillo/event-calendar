@extends('emails.layout')

@section('title', 'Bienvenido a tu nueva organización')

@section('content')
    <div class="greeting">
        ¡Bienvenido/a, {{ $adminName }}! 🎉
    </div>
    
    <div class="success-box">
        <p><strong>🎊 ¡Tu organización está lista!</strong></p>
        <p>La organización <strong>"{{ $organizationName }}"</strong> ha sido creada exitosamente y ya puedes comenzar a usarla.</p>
    </div>
    
    <div class="credentials">
        <p><strong>🔑 Credenciales de Acceso</strong></p>
        <p><strong>Email:</strong> {{ $adminEmail }}</p>
        <p><strong>Contraseña temporal:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 3px;">{{ $tempPassword }}</code></p>
        <p><strong>URL de acceso:</strong> <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
    </div>
    
    <div class="error-box">
        <p><strong>⚠️ IMPORTANTE - Seguridad</strong></p>
        <p>Esta es una contraseña temporal. <strong>Debes cambiarla</strong> en tu primer inicio de sesión por motivos de seguridad.</p>
    </div>
    
    <div style="text-align: center;">
        <a href="{{ $loginUrl }}" class="btn btn-success">
            🚀 Iniciar Sesión Ahora
        </a>
    </div>
    
    <div class="message">
        <p><strong>🎯 Primeros pasos recomendados:</strong></p>
        <ol style="margin-left: 20px; margin-top: 10px;">
            <li><strong>Cambiar contraseña:</strong> Ve a tu perfil y actualiza tu contraseña</li>
            <li><strong>Completar perfil:</strong> Agrega información adicional de tu organización</li>
            <li><strong>Personalizar estilo:</strong> Configura los colores y logos de tu organización</li>
            <li><strong>Crear tu primer evento:</strong> ¡Comienza a promocionar tus actividades!</li>
        </ol>
    </div>
    
    <div class="info-box">
        <p><strong>🌟 Funcionalidades disponibles:</strong></p>
        <ul style="margin-left: 20px; margin-top: 10px;">
            <li>✅ <strong>Gestión de eventos:</strong> Crear, editar y promocionar eventos</li>
            <li>✅ <strong>Formularios personalizados:</strong> Recopilar información específica</li>
            <li>✅ <strong>Panel de control:</strong> Estadísticas y análisis en tiempo real</li>
            <li>✅ <strong>Gestión de equipo:</strong> Invitar colaboradores</li>
            <li>✅ <strong>Personalización:</strong> Adaptar la plataforma a tu imagen</li>
        </ul>
    </div>
    
    <div class="warning-box">
        <p><strong>📚 Recursos de ayuda:</strong></p>
        <p>• <strong>Documentación:</strong> Guías paso a paso para todas las funciones</p>
        <p>• <strong>Soporte técnico:</strong> <a href="mailto:soporte@enteturismo.com">soporte@enteturismo.com</a></p>
        <p>• <strong>Tutoriales:</strong> Videos explicativos en nuestro canal</p>
    </div>
    
    <div class="message">
        <p><strong>🤝 Nuestro compromiso contigo:</strong></p>
        <p>Estamos comprometidos con tu éxito. Nuestro equipo de soporte está disponible para ayudarte a aprovechar al máximo todas las herramientas disponibles.</p>
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <a href="{{ $dashboardUrl }}" class="btn">
            📊 Ir al Panel de Control
        </a>
    </div>
    
    <div class="success-box">
        <p><strong>🎊 ¡Gracias por unirte!</strong></p>
        <p>Estamos emocionados de verte crecer y contribuir al vibrante ecosistema de eventos de Tucumán.</p>
    </div>
@endsection
