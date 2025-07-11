<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_field_id')->constrained('form_fields')->onDelete('cascade');
            $table->foreignId('submitted_by')->constrained('users')->onDelete('restrict');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'form_field_id', 'submitted_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_form_submissions');
    }
};
