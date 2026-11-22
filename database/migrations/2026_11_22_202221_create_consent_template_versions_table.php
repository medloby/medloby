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
        Schema::create('consent_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consent_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title');
            $table->text('content');
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['consent_template_id', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_template_versions');
    }
};
