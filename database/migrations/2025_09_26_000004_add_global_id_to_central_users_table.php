<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('central')->table('users', function (Blueprint $table) {
            $table->string('global_id')->unique()->after('id');
        });

        // Generate global_id for existing users
        $connection = config('database.connections.central.driver') === 'mysql' ? 'central' : 'mysql';
        
        DB::connection($connection)->table('users')->whereNull('global_id')->get()->each(function ($user) use ($connection) {
            DB::connection($connection)->table('users')
                ->where('id', $user->id)
                ->update(['global_id' => (string) Str::uuid()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('users', function (Blueprint $table) {
            $table->dropColumn('global_id');
        });
    }
};
