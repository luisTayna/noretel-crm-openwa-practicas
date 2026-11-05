<?php

namespace Modules\Customer\Services;

use Illuminate\Support\Arr;
use Modules\Customer\Entities\Lead;

class CommercialLeadService
{
    public const SOURCE_CALL_REQUEST = 'website_call_request';
    public const SOURCE_WHATSAPP_OPENWA = 'whatsapp_openwa';
    public const SOURCE_WHATSAPP_CLICK = 'website_whatsapp_click';

    public function createFromPublicRequest(array $data, string $ip, ?string $userAgent = null): Lead
    {
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $metadata = array_filter([
            'ip' => $ip,
            'user_agent' => $userAgent,
            'page' => $data['page'] ?? null,
            'utm' => $data['utm'] ?? null,
            'raw' => Arr::except($data, ['name', 'email', 'phone', 'message']),
        ]);

        return Lead::create([
            'name' => trim((string) $data['name']),
            'contact' => $phone ?: (string) ($data['phone'] ?? ''),
            'phone' => $phone,
            'email' => $data['email'] ?? null,
            'source' => $data['source'] ?? self::SOURCE_CALL_REQUEST,
            'capture_date' => now(),
            'status' => 'new',
            'message' => $data['message'] ?? 'Solicita contacto comercial desde la web.',
            'interest' => $data['interest'] ?? 'call_me',
            'metadata' => $metadata,
            'last_contacted_at' => now(),
        ]);
    }


    public function trackWhatsAppClick(array $data, string $ip, ?string $userAgent = null): Lead
    {
        $destination = config('services.whatsapp.business_destination');
        $destination = is_string($destination) && trim($destination) !== '' ? $destination : null;

        return Lead::create([
            'name' => 'Visitante WhatsApp',
            'contact' => 'Click WhatsApp web',
            'source' => self::SOURCE_WHATSAPP_CLICK,
            'capture_date' => now(),
            'status' => 'new',
            'message' => $data['message'] ?? 'Click en el botón de WhatsApp desde la web.',
            'interest' => 'whatsapp',
            'metadata' => array_filter([
                'ip' => $ip,
                'user_agent' => $userAgent,
                'page' => $data['page'] ?? null,
                'utm' => $data['utm'] ?? null,
                'clicked_to' => $destination,
            ]),
            'last_contacted_at' => now(),
        ]);
    }

    public function captureFromWhatsApp(array $message): array
    {
        $phone = $this->normalizePhone($message['phone'] ?? $message['chat_id'] ?? null);
        $text = trim((string) ($message['text'] ?? ''));

        if (!$phone) {
            return [
                'processed' => false,
                'reason' => 'missing_phone',
            ];
        }

        if ($this->isCollectionMessage($text)) {
            return [
                'processed' => false,
                'reason' => 'collection_or_yape_message',
            ];
        }

        if ($this->isOptOutMessage($text)) {
            $this->markDoNotContact($phone, $message);

            return [
                'processed' => false,
                'reason' => 'opt_out_message',
            ];
        }

        $interest = $this->detectInterest($text);

        if (!$interest) {
            return [
                'processed' => false,
                'reason' => 'unclear_commercial_intent',
            ];
        }

        $lead = $this->findOpenLeadByPhone($phone);
        $incoming = [
            'at' => now()->toDateTimeString(),
            'text' => $text,
            'message_id' => $message['message_id'] ?? null,
            'chat_id' => $message['chat_id'] ?? null,
        ];

        if ($lead) {
            $metadata = $lead->metadata ?: [];
            $messages = $metadata['whatsapp_messages'] ?? [];
            $messages[] = $incoming;
            $metadata['whatsapp_messages'] = array_slice($messages, -10);
            $metadata['last_openwa_payload'] = $message['payload'] ?? null;

            $lead->update([
                'message' => $text ?: $lead->message,
                'interest' => $interest ?: $lead->interest,
                'status' => $lead->status === 'new' ? 'contacted' : $lead->status,
                'metadata' => $metadata,
                'last_contacted_at' => now(),
            ]);
        } else {
            $lead = Lead::create([
                'name' => $message['display_name'] ?: 'WhatsApp ' . $phone,
                'contact' => $phone,
                'phone' => $phone,
                'source' => self::SOURCE_WHATSAPP_OPENWA,
                'capture_date' => now(),
                'status' => 'contacted',
                'message' => $text ?: 'Mensaje recibido por WhatsApp.',
                'interest' => $interest ?: 'whatsapp_inquiry',
                'metadata' => [
                    'openwa_chat_id' => $message['chat_id'] ?? null,
                    'whatsapp_messages' => [$incoming],
                    'last_openwa_payload' => $message['payload'] ?? null,
                ],
                'last_contacted_at' => now(),
            ]);
        }

        return [
            'processed' => true,
            'lead' => $lead->fresh(),
            'reply' => $this->replyForInterest($interest),
            'interest' => $interest,
        ];
    }

    public function normalizePhone(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = preg_replace('/@(c|g)\.us$/i', '', $value);
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51' . $digits;
        }

        return $digits;
    }

    public function isCollectionMessage(string $text): bool
    {
        $text = mb_strtolower($text);

        foreach ([
            'yape',
            'plin',
            'pago',
            'pagado',
            'voucher',
            'comprobante',
            'operación',
            'operacion',
            'constancia',
            'recibo',
            'depósito',
            'deposito',
            'transferencia',
            'dni',
            'captura',
        ] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function isOptOutMessage(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        return in_array($text, ['stop', 'baja', 'no me escribas', 'cancelar', 'salir'], true);
    }

    protected function markDoNotContact(string $phone, array $message): void
    {
        $lead = $this->findOpenLeadByPhone($phone);

        if (!$lead) {
            return;
        }

        $metadata = $lead->metadata ?: [];
        $metadata['do_not_contact'] = true;
        $metadata['do_not_contact_at'] = now()->toDateTimeString();
        $metadata['do_not_contact_source'] = 'whatsapp_openwa';
        $metadata['do_not_contact_message'] = [
            'text' => $message['text'] ?? null,
            'message_id' => $message['message_id'] ?? null,
            'chat_id' => $message['chat_id'] ?? null,
        ];

        $lead->update([
            'metadata' => $metadata,
            'last_contacted_at' => now(),
        ]);
    }

    protected function findOpenLeadByPhone(string $phone): ?Lead
    {
        return Lead::where('phone', $phone)
            ->whereNotIn('status', ['converted', 'lost'])
            ->latest('last_contacted_at')
            ->latest('id')
            ->first();
    }

    protected function detectInterest(string $text): ?string
    {
        $text = mb_strtolower($text);

        return match (true) {
            str_contains($text, '1') || str_contains($text, 'plan') || str_contains($text, 'precio') || str_contains($text, 'internet') || str_contains($text, 'fibra') || str_contains($text, 'información') || str_contains($text, 'informacion') => 'plans',
            str_contains($text, '2') || str_contains($text, 'cobertura') || str_contains($text, 'zona') || str_contains($text, 'dirección') || str_contains($text, 'direccion') => 'coverage',
            str_contains($text, '3') || str_contains($text, 'llamar') || str_contains($text, 'llamada') => 'call_me',
            str_contains($text, '4') || str_contains($text, 'asesor') || str_contains($text, 'humano') || str_contains($text, 'contratar') || str_contains($text, 'contrato') || str_contains($text, 'instalación') || str_contains($text, 'instalacion') => 'advisor',
            default => null,
        };
    }

    protected function replyForInterest(?string $interest): string
    {
        return match ($interest) {
            'plans' => "Perfecto. Para recomendarte el plan correcto, indícanos tu zona o dirección de instalación.",
            'coverage' => "Claro. Envíanos tu dirección o referencia exacta para revisar cobertura.",
            'call_me' => "Con gusto te llamamos. Envíanos tu nombre y el mejor horario para contactarte.",
            'advisor' => "Listo. Registramos tu solicitud y un asesor comercial te responderá por este medio.",
            default => "Hola 👋, bienvenido a Noretel.\nSoy el asistente comercial.\n\n¿En qué podemos ayudarte?\n1️⃣ Ver planes de internet\n2️⃣ Consultar cobertura\n3️⃣ Solicitar llamada\n4️⃣ Hablar con un asesor",
        };
    }
}
