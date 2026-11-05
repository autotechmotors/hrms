<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_pass_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('allow_self_apply')->default(true);
            $table->boolean('require_parent_approval')->default(true);
            $table->boolean('prevent_duplicate_active')->default(true);
            $table->unsignedSmallInteger('max_duration_minutes')->default(360);
            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->timestamps();
        });

        Schema::create('gate_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 24)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->time('out_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_early_leave')->default(false);
            $table->string('work_type', 16)->default('personal');
            $table->string('reason', 1000);
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['company_id', 'date']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('gate_passes');
        Schema::dropIfExists('gate_pass_settings');
    }
};
