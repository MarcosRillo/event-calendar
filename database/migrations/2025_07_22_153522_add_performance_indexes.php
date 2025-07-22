<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índices para tabla invitations (más consultada)
        Schema::table('invitations', function (Blueprint $table) {
            // Índice compuesto para búsquedas por estado y fecha
            $table->index(['status_id', 'created_at'], 'idx_invitations_status_created');
            
            // Índice para verificación de tokens (crítico para performance)
            $table->index(['email', 'expires_at'], 'idx_invitations_email_expires');
            
            // Índice para auditoría y filtros por creador
            $table->index('created_by', 'idx_invitations_created_by');
            
            // Índice para búsquedas por token (único pero necesario para optimización)
            $table->index('token', 'idx_invitations_token');
            
            // Índice para filtros temporales
            $table->index('expires_at', 'idx_invitations_expires_at');
        });

        // Índices para tabla organizations
        Schema::table('organizations', function (Blueprint $table) {
            // Índice para búsquedas por slug (muy frecuente)
            $table->index('slug', 'idx_organizations_slug');
            
            // Índice compuesto para jerarquías organizacionales
            $table->index(['parent_id', 'created_at'], 'idx_organizations_parent_created');
            
            // Índice para nivel de confianza
            $table->index('trust_level_id', 'idx_organizations_trust_level');
        });

        // Índices para tabla users
        Schema::table('users', function (Blueprint $table) {
            // Índice compuesto para listados de usuarios por rol
            $table->index(['role_id', 'created_at'], 'idx_users_role_created');
            
            // Índice para organización (filtros frecuentes)
            $table->index('organization_id', 'idx_users_organization');
            
            // Índice para verificación de email
            $table->index('email_verified_at', 'idx_users_email_verified');
        });

        // Índices para tabla events (para performance futura)
        Schema::table('events', function (Blueprint $table) {
            // Índice compuesto para eventos por organización y estado
            $table->index(['organization_id', 'status_id'], 'idx_events_org_status');
            
            // Índice para búsquedas por categoría
            $table->index('category_id', 'idx_events_category');
            
            // Índice para fechas de eventos (búsquedas temporales)
            $table->index(['start_date', 'end_date'], 'idx_events_dates');
            
            // Índice para eventos públicos/privados
            $table->index(['is_public', 'status_id'], 'idx_events_public_status');
        });

        // Índices para tablas relacionadas con invitations
        Schema::table('invitation_organization_data', function (Blueprint $table) {
            $table->index('invitation_id', 'idx_inv_org_data_invitation');
            $table->index('slug', 'idx_inv_org_data_slug');
        });

        Schema::table('invitation_admin_data', function (Blueprint $table) {
            $table->index('invitation_id', 'idx_inv_admin_data_invitation');
            $table->index('email', 'idx_inv_admin_data_email');
        });

        // Índices para tabla notifications (performance de notificaciones)
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('invitation_id', 'idx_notifications_invitation');
            $table->index(['recipient_email', 'sent_at'], 'idx_notifications_recipient_sent');
            $table->index('created_at', 'idx_notifications_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropIndex('idx_invitations_status_created');
            $table->dropIndex('idx_invitations_email_expires');
            $table->dropIndex('idx_invitations_created_by');
            $table->dropIndex('idx_invitations_token');
            $table->dropIndex('idx_invitations_expires_at');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex('idx_organizations_slug');
            $table->dropIndex('idx_organizations_parent_created');
            $table->dropIndex('idx_organizations_trust_level');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_created');
            $table->dropIndex('idx_users_organization');
            $table->dropIndex('idx_users_email_verified');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('idx_events_org_status');
            $table->dropIndex('idx_events_category');
            $table->dropIndex('idx_events_dates');
            $table->dropIndex('idx_events_public_status');
        });

        Schema::table('invitation_organization_data', function (Blueprint $table) {
            $table->dropIndex('idx_inv_org_data_invitation');
            $table->dropIndex('idx_inv_org_data_slug');
        });

        Schema::table('invitation_admin_data', function (Blueprint $table) {
            $table->dropIndex('idx_inv_admin_data_invitation');
            $table->dropIndex('idx_inv_admin_data_email');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_invitation');
            $table->dropIndex('idx_notifications_recipient_sent');
            $table->dropIndex('idx_notifications_created');
        });
    }
};
