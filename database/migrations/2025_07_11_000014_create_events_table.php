<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('event_categories')->onDelete('restrict');
            $table->foreignId('status_id')->constrained('event_statuses')->onDelete('restrict');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('banner')->nullable();
            $table->string('flyer')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->text('rejected_reason')->nullable();
            $table->text('corrections_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
