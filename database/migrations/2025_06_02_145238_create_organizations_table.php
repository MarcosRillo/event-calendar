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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('header_image_url')->nullable();
            $table->string('footer_image_url')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->json('color_palette');
            $table->json('config')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('organizations')->onDelete('cascade');
            $table->enum('trust_level', ['none', 'internal', 'public'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
