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
        Schema::connection('central')->table('permissions', function (Blueprint $table) {
            // Add custom columns for modular permissions structure
            $table->string('module')->nullable()->after('guard_name');
            $table->string('resource')->nullable()->after('module');
            $table->string('action')->nullable()->after('resource');
            $table->text('description')->nullable()->after('action');
            $table->boolean('is_global')->default(true)->after('description');
            
            // Add index for better performance
            $table->index(['module', 'resource', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('permissions', function (Blueprint $table) {
            // Drop the custom columns
            $table->dropIndex(['module', 'resource', 'action']);
            $table->dropColumn(['module', 'resource', 'action', 'description', 'is_global']);
        });
    }
};
