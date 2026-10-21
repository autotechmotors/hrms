<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('slot', 40)->default('0');
            $table->longText('content');
            $table->timestamps();
            $table->unique(['employee_id', 'kind', 'slot']);
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('punched_at');
            $table->string('direction', 10);
            $table->longText('image')->nullable();
            $table->string('external_key', 191);
            $table->timestamps();
            $table->unique('external_key');
            $table->index(['employee_id', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('employee_credentials');
    }
};
