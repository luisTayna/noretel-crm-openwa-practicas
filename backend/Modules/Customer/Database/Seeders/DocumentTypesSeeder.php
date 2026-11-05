<?php

namespace Modules\Customer\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Customer\Entities\DocumentType;

class DocumentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $documentTypes = [
            [
                'name' => 'Documento de Identidad',
                'description' => 'Documento nacional de identidad del cliente',
                'requires_verification' => true,
                'allowed_format' => json_encode(['pdf', 'jpg', 'jpeg', 'png'])
            ],
            [
                'name' => 'Contrato de Servicio',
                'description' => 'Contrato firmado de prestación de servicios',
                'requires_verification' => true,
                'allowed_format' => json_encode(['pdf'])
            ],
            [
                'name' => 'Factura',
                'description' => 'Factura emitida al cliente',
                'requires_verification' => false,
                'allowed_format' => json_encode(['pdf'])
            ],
            [
                'name' => 'Comprobante de Domicilio',
                'description' => 'Documento que acredita el domicilio del cliente',
                'requires_verification' => true,
                'allowed_format' => json_encode(['pdf', 'jpg', 'jpeg', 'png'])
            ],
            [
                'name' => 'Formulario de Solicitud',
                'description' => 'Formulario de solicitud de servicio',
                'requires_verification' => true,
                'allowed_format' => json_encode(['pdf'])
            ],
            [
                'name' => 'Reporte Técnico',
                'description' => 'Reporte de instalación o servicio técnico',
                'requires_verification' => false,
                'allowed_format' => json_encode(['pdf', 'jpg', 'jpeg', 'png'])
            ],
            [
                'name' => 'Otro',
                'description' => 'Otro tipo de documento',
                'requires_verification' => false,
                'allowed_format' => json_encode(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'])
            ]
        ];

        foreach ($documentTypes as $type) {
            DocumentType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }
    }
}