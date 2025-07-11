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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('invitation_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('message');
            $table->timestamps();
            $table->index(['user_id', 'invitation_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
