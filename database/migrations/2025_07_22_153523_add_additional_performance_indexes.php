<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Función auxiliar para verificar si un índice existe
        $indexExists = function ($table, $indexName) {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($result);
        };

        // Índices para tabla organizations
        Schema::table('organizations', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('organizations', 'idx_organizations_slug')) {
                $table->index('slug', 'idx_organizations_slug');
            }
            if (!$indexExists('organizations', 'idx_organizations_parent_created')) {
                $table->index(['parent_id', 'created_at'], 'idx_organizations_parent_created');
            }
            if (!$indexExists('organizations', 'idx_organizations_trust_level')) {
                $table->index('trust_level_id', 'idx_organizations_trust_level');
            }
        });

        // Índices para tabla users
        Schema::table('users', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('users', 'idx_users_role_created')) {
                $table->index(['role_id', 'created_at'], 'idx_users_role_created');
            }
            if (!$indexExists('users', 'idx_users_organization')) {
                $table->index('organization_id', 'idx_users_organization');
            }
            if (!$indexExists('users', 'idx_users_email_verified')) {
                $table->index('email_verified_at', 'idx_users_email_verified');
            }
        });

        // Índices para tabla events
        Schema::table('events', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('events', 'idx_events_org_status')) {
                $table->index(['organization_id', 'status_id'], 'idx_events_org_status');
            }
            if (!$indexExists('events', 'idx_events_category')) {
                $table->index('category_id', 'idx_events_category');
            }
            if (!$indexExists('events', 'idx_events_dates')) {
                $table->index(['start_date', 'end_date'], 'idx_events_dates');
            }
            if (!$indexExists('events', 'idx_events_public_status')) {
                $table->index(['is_public', 'status_id'], 'idx_events_public_status');
            }
        });

        // Índices para tablas relacionadas con invitations
        Schema::table('invitation_organization_data', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('invitation_organization_data', 'idx_inv_org_data_invitation')) {
                $table->index('invitation_id', 'idx_inv_org_data_invitation');
            }
            if (!$indexExists('invitation_organization_data', 'idx_inv_org_data_slug')) {
                $table->index('slug', 'idx_inv_org_data_slug');
            }
        });

        Schema::table('invitation_admin_data', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('invitation_admin_data', 'idx_inv_admin_data_invitation')) {
                $table->index('invitation_id', 'idx_inv_admin_data_invitation');
            }
            if (!$indexExists('invitation_admin_data', 'idx_inv_admin_data_email')) {
                $table->index('email', 'idx_inv_admin_data_email');
            }
        });

        // Índices para tabla notifications
        Schema::table('notifications', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('notifications', 'idx_notifications_invitation')) {
                $table->index('invitation_id', 'idx_notifications_invitation');
            }
            if (!$indexExists('notifications', 'idx_notifications_recipient_sent')) {
                $table->index(['recipient_email', 'sent_at'], 'idx_notifications_recipient_sent');
            }
            if (!$indexExists('notifications', 'idx_notifications_created')) {
                $table->index('created_at', 'idx_notifications_created');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexExists = function ($table, $indexName) {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($result);
        };

        Schema::table('organizations', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('organizations', 'idx_organizations_slug')) {
                $table->dropIndex('idx_organizations_slug');
            }
            if ($indexExists('organizations', 'idx_organizations_parent_created')) {
                $table->dropIndex('idx_organizations_parent_created');
            }
            if ($indexExists('organizations', 'idx_organizations_trust_level')) {
                $table->dropIndex('idx_organizations_trust_level');
            }
        });

        Schema::table('users', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('users', 'idx_users_role_created')) {
                $table->dropIndex('idx_users_role_created');
            }
            if ($indexExists('users', 'idx_users_organization')) {
                $table->dropIndex('idx_users_organization');
            }
            if ($indexExists('users', 'idx_users_email_verified')) {
                $table->dropIndex('idx_users_email_verified');
            }
        });

        Schema::table('events', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('events', 'idx_events_org_status')) {
                $table->dropIndex('idx_events_org_status');
            }
            if ($indexExists('events', 'idx_events_category')) {
                $table->dropIndex('idx_events_category');
            }
            if ($indexExists('events', 'idx_events_dates')) {
                $table->dropIndex('idx_events_dates');
            }
            if ($indexExists('events', 'idx_events_public_status')) {
                $table->dropIndex('idx_events_public_status');
            }
        });

        Schema::table('invitation_organization_data', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('invitation_organization_data', 'idx_inv_org_data_invitation')) {
                $table->dropIndex('idx_inv_org_data_invitation');
            }
            if ($indexExists('invitation_organization_data', 'idx_inv_org_data_slug')) {
                $table->dropIndex('idx_inv_org_data_slug');
            }
        });

        Schema::table('invitation_admin_data', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('invitation_admin_data', 'idx_inv_admin_data_invitation')) {
                $table->dropIndex('idx_inv_admin_data_invitation');
            }
            if ($indexExists('invitation_admin_data', 'idx_inv_admin_data_email')) {
                $table->dropIndex('idx_inv_admin_data_email');
            }
        });

        Schema::table('notifications', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('notifications', 'idx_notifications_invitation')) {
                $table->dropIndex('idx_notifications_invitation');
            }
            if ($indexExists('notifications', 'idx_notifications_recipient_sent')) {
                $table->dropIndex('idx_notifications_recipient_sent');
            }
            if ($indexExists('notifications', 'idx_notifications_created')) {
                $table->dropIndex('idx_notifications_created');
            }
        });
    }
};
