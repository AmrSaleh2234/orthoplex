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
        Schema::connection('central')->create('login_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('global_user_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('login_method')->default('password'); // password, magic_link, two_factor
            $table->boolean('two_factor_used')->default(false);
            $table->integer('session_duration')->nullable(); // in seconds
            $table->timestamp('login_at');
            $table->timestamp('logout_at')->nullable();
            $table->json('device_info')->nullable();
            $table->json('location_info')->nullable();
            $table->boolean('success')->default(true);
            $table->string('failure_reason')->nullable();
            
            // Add indexes for performance
            $table->index(['central_user_id', 'login_at']);
            $table->index(['tenant_id', 'login_at']);
            $table->index(['success', 'login_at']);
            $table->index('login_at');
            
            // Add foreign key constraints
            $table->foreign('central_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('login_events');
    }
};
