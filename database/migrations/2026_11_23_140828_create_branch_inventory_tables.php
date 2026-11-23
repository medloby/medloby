<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->string('unit', 24);
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('branch_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_key', 32);
            $table->string('direction', 3);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('balance_after');
            $table->string('description');
            $table->timestamps();
            $table->unique(['branch_inventory_item_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_inventory_movements');
        Schema::dropIfExists('branch_inventory_items');
    }
};
