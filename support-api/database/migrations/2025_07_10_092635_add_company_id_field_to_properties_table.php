<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('properties', function (Blueprint $table) {
            //
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->onDelete('set null')
                ->after('in_use_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('properties', function (Blueprint $table) {
            //
            $table->dropForeign(['company_id']);
        });
    }
};
