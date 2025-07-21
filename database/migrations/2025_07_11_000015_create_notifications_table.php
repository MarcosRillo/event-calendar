<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('recipient_email');
            $table->text('content')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
            
            // Índice para mejor performance
            $table->index(['invitation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
