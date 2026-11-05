<?php

namespace Modules\Contract\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Contract\Entities\SLA;

class SLASeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Premium SLA
        SLA::create([
            'name' => 'Premium Enterprise',
            'service_level' => 'premium',
            'response_time' => 1, // 1 hora
            'resolution_time' => 4, // 4 horas
            'penalties' => [
                'response_time_penalty' => '5% de la factura mensual por hora de retraso',
                'resolution_time_penalty' => '10% de la factura mensual por hora de retraso',
                'availability_penalty' => '2% de la factura mensual por cada 0.1% por debajo del 99.9% de disponibilidad'
            ],
            'active' => true
        ]);

        // Standard SLA
        SLA::create([
            'name' => 'Standard Business',
            'service_level' => 'standard',
            'response_time' => 4, // 4 horas
            'resolution_time' => 24, // 24 horas
            'penalties' => [
                'response_time_penalty' => '2% de la factura mensual por hora de retraso',
                'resolution_time_penalty' => '5% de la factura mensual por hora de retraso',
                'availability_penalty' => '1% de la factura mensual por cada 0.1% por debajo del 99.5% de disponibilidad'
            ],
            'active' => true
        ]);

        // Basic SLA
        SLA::create([
            'name' => 'Basic Residential',
            'service_level' => 'basic',
            'response_time' => 24, // 24 horas
            'resolution_time' => 48, // 48 horas
            'penalties' => [
                'response_time_penalty' => 'N/A',
                'resolution_time_penalty' => 'N/A',
                'availability_penalty' => 'N/A'
            ],
            'active' => true
        ]);
    }
}