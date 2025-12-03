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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('會員姓名');
            $table->string('email')->unique()->comment('Email (登入帳號)');
            $table->timestamp('email_verified_at')->nullable()->comment('Email 驗證時間');
            $table->string('password')->comment('密碼 (bcrypt 加密)');
            $table->string('phone', 20)->nullable()->comment('電話號碼');
            $table->text('address')->nullable()->comment('地址');
            $table->rememberToken();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Email');
            $table->string('token')->comment('重置密碼 Token');
            $table->timestamp('created_at')->nullable()->comment('建立時間');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
