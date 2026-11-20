<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->string('account_holder', 120)->nullable();
            $table->string('branch_name', 120)->nullable();
            $table->string('gst_number', 15)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_holder', 'branch_name', 'gst_number']);
        });
    }
};
