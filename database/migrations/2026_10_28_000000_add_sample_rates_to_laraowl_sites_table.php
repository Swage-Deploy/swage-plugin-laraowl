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
        Schema::table('laraowl_sites', function (Blueprint $table) {
            $table->decimal('sample_requests', 4, 3)->nullable()->after('token');
            $table->decimal('sample_commands', 4, 3)->nullable()->after('sample_requests');
            $table->decimal('sample_exceptions', 4, 3)->nullable()->after('sample_commands');
            $table->decimal('sample_tasks', 4, 3)->nullable()->after('sample_exceptions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laraowl_sites', function (Blueprint $table) {
            $table->dropColumn(['sample_requests', 'sample_commands', 'sample_exceptions', 'sample_tasks']);
        });
    }
};
