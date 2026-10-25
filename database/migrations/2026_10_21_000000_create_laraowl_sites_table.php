<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the plugin's table and move in the settings Swage kept on sites before LaraOwl became a plugin.
     */
    public function up(): void
    {
        Schema::create('laraowl_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('server_url');
            $table->text('token');
            $table->timestamps();
        });

        if (! Schema::hasColumn('sites', 'laraowl_server_url')) {
            return;
        }

        // The token is copied still encrypted: both were encrypted with the same application key.
        DB::table('sites')->whereNotNull('laraowl_server_url')->whereNotNull('laraowl_token')->orderBy('id')
            ->each(fn (object $site) => DB::table('laraowl_sites')->insert([
                'site_id' => $site->id,
                'server_url' => $site->laraowl_server_url,
                'token' => $site->laraowl_token,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['laraowl_server_url', 'laraowl_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laraowl_sites');
    }
};
