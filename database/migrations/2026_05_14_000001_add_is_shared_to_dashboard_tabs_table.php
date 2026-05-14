<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashboard_tabs') || Schema::hasColumn('dashboard_tabs', 'is_shared')) {
            return;
        }

        Schema::table('dashboard_tabs', function (Blueprint $table) {
            $table->boolean('is_shared')->default(false)->after('is_default');
            $table->index('is_shared');
        });

        if (Schema::hasTable('roles') && Schema::hasTable('role_assignments')) {
            $adminUserIds = DB::table('role_assignments')
                ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
                ->where('roles.name', 'Admin')
                ->pluck('role_assignments.user_id');

            if ($adminUserIds->isNotEmpty()) {
                DB::table('dashboard_tabs')
                    ->whereIn('user_id', $adminUserIds)
                    ->where(function ($query) {
                        $query
                            ->where('slug', 'reports-dashboard')
                            ->orWhere('slug', 'like', '%report%')
                            ->orWhere('name', 'like', '%Report%');
                    })
                    ->update(['is_shared' => true]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashboard_tabs') || ! Schema::hasColumn('dashboard_tabs', 'is_shared')) {
            return;
        }

        Schema::table('dashboard_tabs', function (Blueprint $table) {
            $table->dropIndex(['is_shared']);
            $table->dropColumn('is_shared');
        });
    }
};
