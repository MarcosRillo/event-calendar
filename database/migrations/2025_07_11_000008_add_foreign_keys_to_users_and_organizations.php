<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['role_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['created_by']);
        });
    }
};
