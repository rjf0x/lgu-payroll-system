<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 64)->unique();
            $table->string('value', 255)->nullable();
            $table->timestamps();
        });

        foreach (['system_name' => 'LGU Payroll System', 'organization_name' => 'Local Government Unit', 'support_email' => null] as $key => $value) {
            DB::table('system_settings')->insert(['setting_key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('permissions')->updateOrInsert(['permission_name' => 'settings.manage'], ['description' => 'Manage system display and contact settings.']);
        $permissionId = DB::table('permissions')->where('permission_name', 'settings.manage')->value('permission_id');
        foreach (DB::table('roles')->where('role_name', 'Admin')->pluck('role_id') as $roleId) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        $permissionId = DB::table('permissions')->where('permission_name', 'settings.manage')->value('permission_id');
        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('permission_id', $permissionId)->delete();
    }
};
