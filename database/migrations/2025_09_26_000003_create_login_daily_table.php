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
        Schema::connection('central')->create('login_daily', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->date('date');
            $table->integer('login_count')->default(0);
            $table->integer('successful_logins')->default(0);
            $table->integer('failed_logins')->default(0);
            $table->integer('unique_users')->default(0);
            $table->integer('two_factor_logins')->default(0);
            $table->integer('magic_link_logins')->default(0);
            $table->integer('password_logins')->default(0);
            $table->timestamps();
            
            // Add indexes for performance
            $table->index(['tenant_id', 'date']);
            $table->index('date');
            
            // Add foreign key constraint
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Add unique constraint for tenant_id + date
            $table->unique(['tenant_id', 'date'], 'login_daily_tenant_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('login_daily');
    }
};
