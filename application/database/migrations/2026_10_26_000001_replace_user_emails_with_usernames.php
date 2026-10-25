<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique();
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $local = explode('@', $user->email, 2)[0];
                $base = trim(strtolower(preg_replace('/[^a-zA-Z0-9._-]+/', '-', $local)), '._-');
                if (strlen($base) < 3) {
                    $base = 'user-'.$user->id;
                }
                $candidate = substr($base, 0, 50);
                $attempt = 0;
                while (DB::table('users')->where('username', $candidate)->exists()) {
                    $suffix = '-'.$user->id.($attempt ? '-'.$attempt : '');
                    $candidate = substr($base, 0, 50 - strlen($suffix)).$suffix;
                    $attempt++;
                }
                DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
            $table->dropUnique('users_email_unique');
            $table->dropColumn(['email', 'email_verified_at']);
        });
        // No email reset flow is exposed; discard obsolete email-addressed tokens.
        DB::table('password_reset_tokens')->delete();
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->renameColumn('email', 'username');
        });
    }

    public function down(): void
    {
        // Removed addresses cannot be recovered. Rollback uses unique placeholders.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
        });
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['email' => 'user-'.$user->id.'@rollback.invalid']);
            }
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
        DB::table('password_reset_tokens')->delete();
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->renameColumn('username', 'email');
        });
    }
};
