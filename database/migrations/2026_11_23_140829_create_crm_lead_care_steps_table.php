<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_care_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phase', 20);
            $table->string('status', 12);
            $table->date('planned_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['crm_lead_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_care_steps');
    }
};
