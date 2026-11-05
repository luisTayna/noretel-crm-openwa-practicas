<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Services\Entities\Service;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')
            ->where('service_type', Service::SERVICE_TYPE_INTERNET)
            ->whereIn('technology', Service::LEGACY_FIBER_TECHNOLOGIES)
            ->update([
                'technology' => Service::TECHNOLOGY_FIBER_WIFI,
                'updated_at' => now(),
            ]);

        DB::table('services')
            ->where(function ($query) {
                $query
                    ->where('service_type', '!=', Service::SERVICE_TYPE_INTERNET)
                    ->orWhere('technology', '!=', Service::TECHNOLOGY_FIBER_WIFI);
            })
            ->update([
                'active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally left blank: the previous active state cannot be recovered safely.
    }
};
