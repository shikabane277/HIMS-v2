<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->char('permission_id', 36)->primary();
            $table->string('resource', 50);
            $table->string('action', 20);
            $table->string('scope', 20)->default('own');
            $table->text('description')->nullable();
            $table->unique(['resource', 'action', 'scope']);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('access_role', 30);
            $table->char('permission_id', 36);
            $table->unique(['access_role', 'permission_id']);
            $table->foreign('permission_id')->references('permission_id')->on('permissions');
            $table->timestamps();
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->char('audit_id', 36)->primary();
            $table->timestamp('timestamp')->useCurrent();
            $table->char('user_id', 36)->nullable();
            $table->char('employee_id', 36)->nullable();
            $table->string('action', 30);
            $table->string('resource_type', 50);
            $table->char('resource_id', 36)->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->text('request_path')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('before_state_hash', 64)->nullable();
            $table->string('after_state_hash', 64)->nullable();
            $table->string('chain_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'timestamp']);
            $table->index(['resource_type', 'resource_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
