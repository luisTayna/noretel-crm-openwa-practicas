<?php

namespace Modules\Services\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Services\Entities\Service;

class ServicesTableSeeder extends Seeder
{
    public function run()
    {
        Service::insert([
            [
                'name' => 'Internet Fibra Óptica',
                'description' => 'Servicio de internet de alta velocidad vía fibra óptica con WiFi',
                'service_type' => 'internet',
                'technology' => Service::TECHNOLOGY_FIBER_WIFI,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Internet Cable',
                'description' => 'Servicio de internet por cable coaxial',
                'service_type' => 'internet',
                'technology' => 'cable',
                'active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Internet Inalámbrico',
                'description' => 'Servicio de internet por tecnología inalámbrica',
                'service_type' => 'internet',
                'technology' => 'wireless',
                'active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $serviceId = Service::first()->id;

        \Illuminate\Support\Facades\DB::table('plans')->insert([
            [
                'service_id' => $serviceId,
                'name' => 'Plan Básico 50MB',
                'price' => 50.00,
                'upload_speed' => 50,
                'download_speed' => 50,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_id' => $serviceId,
                'name' => 'Plan Intermedio 100MB',
                'price' => 80.00,
                'upload_speed' => 100,
                'download_speed' => 100,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'service_id' => $serviceId,
                'name' => 'Plan Avanzado 200MB',
                'price' => 120.00,
                'upload_speed' => 200,
                'download_speed' => 200,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
