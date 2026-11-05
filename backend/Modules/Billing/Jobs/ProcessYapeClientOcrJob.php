<?php

namespace Modules\Billing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;
use Modules\Billing\Services\YapeReconciliationService;
use Modules\Billing\Services\OpenWaService;

class ProcessYapeClientOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El número de intentos permitidos para el job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * El número de segundos que el job puede ejecutarse antes de expirar.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $evidenceId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(
        YapeReconciliationService $reconciliationService,
        OpenWaService $openWaService
    ): void {
        $evidence = YapeClientEvidence::find($this->evidenceId);

        if (!$evidence) {
            \Log::warning('ProcessYapeClientOcrJob: No se encontró la evidencia Yape.', [
                'evidence_id' => $this->evidenceId,
            ]);
            return;
        }

        if ($evidence->status !== 'pending_ocr') {
            \Log::info('ProcessYapeClientOcrJob: La evidencia ya fue procesada por OCR.', [
                'evidence_id' => $this->evidenceId,
                'status' => $evidence->status,
            ]);
            return;
        }

        \Log::info('ProcessYapeClientOcrJob: Iniciando procesamiento OCR asíncrono.', [
            'evidence_id' => $evidence->id,
            'whatsapp_phone' => $evidence->whatsapp_phone,
        ]);

        // Ejecutar OCR y enriquecer datos
        $ocrSuccess = $reconciliationService->enrichEvidenceWithOcr($evidence);

        // Volver a cargar la evidencia fresca
        $evidence = $evidence->fresh();

        // Procesar matching y conciliación asíncrona
        $evidence = $reconciliationService->processEvidenceReconciliation($evidence);

        $evidence->loadMissing('intent');
        $intent = $evidence->intent;
        $openWaData = $openWaService->openWaDataFromClientEvidence($evidence);

        if ($intent && $intent->status === YapePaymentIntent::STATUS_VALIDATED) {
            \Log::info('ProcessYapeClientOcrJob: Pago validado correctamente de forma asíncrona.', [
                'evidence_id' => $evidence->id,
                'intent_id' => $intent->id,
            ]);

            $openWaService->sendTextMessage(
                $openWaData,
                '✅ Gracias. Tu pago Yape fue validado correctamente. Te enviamos tu constancia de pago.'
            );

            $openWaService->sendPaymentReceipt($openWaData, $intent);
        } else {
            \Log::info('ProcessYapeClientOcrJob: Pago no validado aún. Solicitando DNI de forma asíncrona.', [
                'evidence_id' => $evidence->id,
                'intent_id' => $intent?->id,
            ]);

            $openWaService->sendTextMessage(
                $openWaData,
                'Hemos recibido la captura Yape. Para asociar el pago correctamente, envía el DNI del cliente de 8 dígitos.'
            );
        }
    }
}
