<?php

namespace Modules\Billing\Services;

class OpenWaWebhookPayloadService
{
    public const DNI_ERROR_TOO_SHORT = 'too_short';
    public const DNI_ERROR_TOO_LONG = 'too_long';
    public const DNI_ERROR_MISSING = 'missing';
    public const DNI_ERROR_MULTIPLE = 'multiple';

    public function normalize(array $payload): array
    {
        $event = $this->firstString($payload, [
            'event',
            'type',
            'eventName',
            'name',
        ]);

        $message = $this->messagePayload($payload);

        if (!$message) {
            return [
                'should_process' => false,
                'ignored_reason' => 'El webhook no contiene un mensaje procesable.',
                'event' => $event,
            ];
        }

        $messageIds = $this->messageIdentifiers($message);
        $fromMe = $this->messageFromMe($payload, $message);

        if ($fromMe) {
            return [
                'should_process' => false,
                'ignored_reason' => 'from_me_ignored',
                'event' => $event,
                'from_me' => true,
            ];
        }

        if (!$this->isMessageEvent($payload)) {
            return [
                'should_process' => false,
                'ignored_reason' => 'non_message_event_ignored',
                'event' => $event,
                'from_me' => false,
            ];
        }

        $chatId = $this->messageChatId($message);
        $chatContext = $this->chatContext($chatId);
        $phoneContext = $this->confirmedPhoneContext($message, $chatContext);

        if ($chatContext['is_group'] || $this->messageIsGroup($message) || $this->containsGroupIdentifier($messageIds)) {
            return [
                'should_process' => false,
                'ignored_reason' => 'group_ignored',
                'event' => $event,
                'chat_id_original' => $chatContext['original'],
                'chat_id' => $chatContext['normalized'],
                'is_group' => true,
                'from_me' => false,
                'identifiers' => $messageIds,
            ];
        }

        if (!$chatContext['is_private']) {
            return [
                'should_process' => false,
                'ignored_reason' => 'non_private_ignored',
                'event' => $event,
                'chat_id_original' => $chatContext['original'],
                'chat_id' => $chatContext['normalized'],
                'is_group' => false,
                'is_private' => false,
                'from_me' => false,
                'identifiers' => $messageIds,
            ];
        }

        $messageKind = $this->messageKind($message);

        if (!in_array($messageKind, ['text', 'image'], true)) {
            return [
                'should_process' => false,
                'ignored_reason' => $messageKind . '_ignored',
                'event' => $event,
                'message_kind' => $messageKind,
                'chat_id_original' => $chatContext['original'],
                'chat_id' => $chatContext['normalized'],
                'is_group' => false,
                'is_private' => true,
                'from_me' => false,
                'identifiers' => $messageIds,
            ];
        }

        if ($messageKind === 'text') {
            $rawText = $this->firstString($message, [
                'caption',
                'body',
                'text',
                'message.caption',
            ]);
            $interactiveSelectionId = $this->firstString($message, [
                'selectedRowId',
                'selectedButtonId',
                'buttonId',
                'listResponse.singleSelectReply.selectedRowId',
            ]);

            if ($interactiveSelectionId) {
                return [
                    'should_process' => true,
                    'type' => 'interactive_selection',
                    'event' => $event,
                    'message_kind' => 'text',
                    'data' => [
                        'whatsapp_phone' => $phoneContext['phone'],
                        'whatsapp_phone_source' => $phoneContext['source'],
                        'whatsapp_phone_confidence' => $phoneContext['confidence'],
                        'source_message_id' => $this->messageDedupKey($payload, $message, $chatContext['normalized']),
                        'source_chat_name' => $chatContext['normalized'],
                        'source_chat_display_name' => $this->firstString($message, ['chat.name', 'chatName', 'sender.name', 'notifyName', 'pushname', 'from']),
                        'openwa_chat_id' => $chatContext['normalized'],
                        'openwa_chat_id_original' => $chatContext['original'],
                        'openwa_is_group' => false,
                        'openwa_is_private' => true,
                        'openwa_from_me' => false,
                        'raw_text' => $rawText,
                        'interactive_selection_id' => $interactiveSelectionId,
                        'payload' => $payload,
                    ],
                ];
            }
            $dniResult = $this->extractDniResult($rawText);
            $dni = $dniResult['dni'];

            if ($dni) {
                return [
                    'should_process' => true,
                    'type' => 'dni_resolution',
                    'event' => $event,
                    'message_kind' => 'text',
                    'data' => [
                        'whatsapp_phone' => $phoneContext['phone'],
                        'whatsapp_phone_source' => $phoneContext['source'],
                        'whatsapp_phone_confidence' => $phoneContext['confidence'],
                        'source_message_id' => $this->messageDedupKey($payload, $message, $chatContext['normalized']),
                        'source_chat_name' => $chatContext['normalized'],
                        'source_chat_display_name' => $this->firstString($message, [
                            'chat.name',
                            'chatName',
                            'sender.name',
                            'notifyName',
                            'pushname',
                            'from',
                        ]),
                        'openwa_chat_id' => $chatContext['normalized'],
                        'openwa_chat_id_original' => $chatContext['original'],
                        'openwa_is_group' => false,
                        'openwa_is_private' => true,
                        'openwa_from_me' => false,
                        'raw_text' => $rawText,
                        'dni' => $dni,
                        'payload' => $payload,
                    ],
                ];
            }

            return [
                'should_process' => true,
                'type' => 'dni_resolution_error',
                'event' => $event,
                'message_kind' => 'text',
                'data' => [
                        'whatsapp_phone' => $phoneContext['phone'],
                        'whatsapp_phone_source' => $phoneContext['source'],
                        'whatsapp_phone_confidence' => $phoneContext['confidence'],
                        'source_message_id' => $this->messageDedupKey($payload, $message, $chatContext['normalized']),
                    'source_chat_name' => $chatContext['normalized'],
                    'source_chat_display_name' => $this->firstString($message, [
                        'chat.name',
                        'chatName',
                        'sender.name',
                        'notifyName',
                        'pushname',
                        'from',
                    ]),
                        'openwa_chat_id' => $chatContext['normalized'],
                        'openwa_chat_id_original' => $chatContext['original'],
                        'openwa_is_group' => false,
                        'openwa_is_private' => true,
                        'openwa_from_me' => false,
                        'raw_text' => $rawText,
                        'dni_error' => $dniResult['error'],
                        'payload' => $payload,
                ],
            ];
        }

        $imageBase64 = $this->firstString($message, [
            'image_base64',
            'imageBase64',
            'body.data',
            'media.data',
            'media.base64',
            'mediaData.data',
            'downloadedMedia.data',
        ]);

        return [
            'should_process' => true,
            'type' => 'image',
            'event' => $event,
            'message_kind' => 'image',
            'data' => [
                'whatsapp_phone' => $phoneContext['phone'],
                'whatsapp_phone_source' => $phoneContext['source'],
                'whatsapp_phone_confidence' => $phoneContext['confidence'],
                'source_message_id' => $this->messageDedupKey($payload, $message, $chatContext['normalized']),
                'source_chat_name' => $chatContext['normalized'],
                'source_chat_display_name' => $this->firstString($message, [
                    'chat.name',
                    'chatName',
                    'sender.name',
                    'notifyName',
                    'pushname',
                    'from',
                ]),
                'openwa_chat_id' => $chatContext['normalized'],
                'openwa_chat_id_original' => $chatContext['original'],
                'openwa_is_group' => false,
                'openwa_is_private' => true,
                'openwa_from_me' => false,
                'raw_text' => $this->firstString($message, [
                    'caption',
                    'body',
                    'text',
                    'message.caption',
                ]),
                'amount' => $this->firstValue($message, [
                    'amount',
                    'parsed_amount',
                    'yape.amount',
                ]),
                'operation_code' => $this->firstString($message, [
                    'operation_code',
                    'operationCode',
                    'yape.operation_code',
                    'yape.operationCode',
                ]),
                'security_code' => $this->firstString($message, [
                    'security_code',
                    'securityCode',
                    'yape.security_code',
                    'yape.securityCode',
                ]),
                'occurred_at' => $this->timestampToDateTime($this->firstValue($message, [
                    'timestamp',
                    't',
                    'createdAt',
                    'date',
                ])),
                'receiver_name' => $this->firstString($message, [
                    'receiver_name',
                    'receiverName',
                    'yape.receiver_name',
                    'yape.receiverName',
                ]),
                'receiver_phone' => $this->extractPhone($this->firstString($message, [
                    'receiver_phone',
                    'receiverPhone',
                    'yape.receiver_phone',
                    'yape.receiverPhone',
                ])),
                'confidence' => $this->firstValue($message, [
                    'confidence',
                    'ocr_confidence',
                    'yape.confidence',
                ]),
                'image_base64' => $imageBase64,
                'media_mimetype' => $this->firstString($message, [
                    'mimetype',
                    'mimeType',
                    'media.mimetype',
                    'media.mimeType',
                    'downloadedMedia.mimetype',
                ]),
                'media_url' => $this->firstString($message, [
                    'media.url',
                    'mediaUrl',
                    'downloadUrl',
                    'url',
                    'body.url',
                    'message.mediaUrl',
                ]),
                'payload' => $payload,
            ],
        ];
    }

    protected function messagePayload(array $payload): ?array
    {
        foreach ([
            'data.message',
            'payload.message',
            'message',
            'data',
            'payload',
        ] as $path) {
            $value = $this->get($payload, $path);
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function isIncomingMessage(array $payload, array $message): bool
    {
        if ($this->messageFromMe($payload, $message)) {
            return false;
        }

        return $this->isMessageEvent($payload);
    }

    protected function isMessageEvent(array $payload): bool
    {
        $event = strtolower((string) $this->firstString($payload, [
            'event',
            'type',
            'eventName',
            'name',
        ]));

        return $event === ''
            || str_contains($event, 'message')
            || str_contains($event, 'received');
    }

    protected function messageFromMe(array $payload, array $message): bool
    {
        $fromMe = $this->firstValue($message, [
            'fromMe',
            'id.fromMe',
            'isFromMe',
            'key.fromMe',
            'message.fromMe',
        ]);

        $fromMe ??= $this->firstValue($payload, [
            'fromMe',
            'isFromMe',
            'data.fromMe',
            'data.isFromMe',
            'payload.fromMe',
            'payload.isFromMe',
        ]);

        return $fromMe === true || $fromMe === 'true' || $fromMe === 1 || $fromMe === '1';
    }

    protected function hasImageMedia(array $message): bool
    {
        $mime = strtolower((string) $this->firstString($message, [
            'mimetype',
            'mimeType',
            'media.mimetype',
            'media.mimeType',
            'downloadedMedia.mimetype',
        ]));

        $type = strtolower((string) $this->firstString($message, [
            'type',
            'media.type',
            'message.type',
        ]));

        $hasMedia = $this->firstValue($message, [
            'hasMedia',
            'media',
            'mediaUrl',
            'downloadUrl',
            'image_base64',
            'imageBase64',
        ]);

        return str_starts_with($mime, 'image/')
            || $type === 'image'
            || ($hasMedia && in_array($type, ['', 'image', 'media'], true));
    }

    protected function messageKind(array $message): string
    {
        $type = strtolower((string) $this->firstString($message, [
            'type',
            'media.type',
            'message.type',
        ]));

        $mime = strtolower((string) $this->firstString($message, [
            'mimetype',
            'mimeType',
            'media.mimetype',
            'media.mimeType',
            'downloadedMedia.mimetype',
        ]));

        $isSticker = $this->firstValue($message, [
            'isSticker',
            'sticker',
            'media.isSticker',
        ]);

        if ($isSticker === true || $isSticker === 'true' || $isSticker === 1 || $isSticker === '1'
            || in_array($type, ['sticker', 'sticker_message'], true)
            || $mime === 'image/webp') {
            return 'sticker';
        }

        if (in_array($type, ['list_response', 'buttons_response', 'template_button_reply', 'interactive'], true)) {
            return 'text';
        }

        if (in_array($type, ['audio', 'ptt', 'voice', 'voice_message'], true)
            || str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        if (in_array($type, ['video', 'gif', 'video_message'], true)
            || str_starts_with($mime, 'video/')) {
            return 'video';
        }

        if (in_array($type, ['document', 'document_message'], true)
            || ($mime !== '' && !str_starts_with($mime, 'image/') && !str_starts_with($mime, 'text/'))) {
            return 'document';
        }

        if (in_array($type, ['reaction', 'reaction_message'], true)) {
            return 'reaction';
        }

        if (in_array($type, ['location', 'live_location'], true)) {
            return 'location';
        }

        if (in_array($type, ['contact', 'contacts', 'vcard'], true)) {
            return 'contact';
        }

        if ($type === 'image') {
            return 'image';
        }

        if ($this->hasImageMedia($message)) {
            return 'image';
        }

        $rawText = $this->firstString($message, [
            'caption',
            'body',
            'text',
            'message.caption',
            'message.body',
            'message.text',
        ]);

        if (in_array($type, ['', 'chat', 'text', 'text_message'], true) && $rawText !== null) {
            return 'text';
        }

        return 'unknown';
    }

    protected function messageChatId(array $message): ?string
    {
        return $this->firstString($message, [
            'chatId',
            'remoteJid',
            'id.remote',
            'id.remoteJid',
            'key.remoteJid',
            'from',
            'sender.id',
            'author',
            'participant',
        ]);
    }

    protected function messageIdentifiers(array $message): array
    {
        $identifiers = [];

        foreach ([
            'chatId',
            'from',
            'to',
            'sender.id',
            'remoteJid',
            'id.remote',
            'id.remoteJid',
            'key.remoteJid',
            'author',
            'participant',
            'recipient.id',
        ] as $path) {
            $value = $this->firstValue($message, [$path]);

            if (is_string($value) && trim($value) !== '') {
                $identifiers[$path] = trim($value);
            }
        }

        return $identifiers;
    }

    protected function containsGroupIdentifier(array $identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            if (is_string($identifier) && str_ends_with(strtolower(trim($identifier)), '@g.us')) {
                return true;
            }
        }

        return false;
    }

    protected function messageIsGroup(array $message): bool
    {
        $isGroup = $this->firstValue($message, [
            'isGroup',
            'group',
            'chat.isGroup',
        ]);

        return $isGroup === true || $isGroup === 'true' || $isGroup === 1 || $isGroup === '1';
    }

    protected function chatContext(?string $chatId): array
    {
        $original = is_string($chatId) ? trim($chatId) : '';
        $normalized = $this->normalizeChatId($original);

        return [
            'original' => $original ?: null,
            'normalized' => $normalized,
            'is_group' => is_string($normalized) && str_ends_with($normalized, '@g.us'),
            'is_private' => is_string($normalized)
                && (str_ends_with($normalized, '@c.us') || str_ends_with($normalized, '@lid')),
        ];
    }

    protected function normalizeChatId(?string $chatId): ?string
    {
        $chatId = is_string($chatId) ? trim($chatId) : '';

        if ($chatId === '') {
            return null;
        }

        if (preg_match('/@(c|g)\.us$/i', $chatId)) {
            $digits = preg_replace('/\D+/', '', preg_replace('/@(c|g)\.us$/i', '', $chatId));
            $suffix = strtolower(substr($chatId, -5));

            return $digits ? $digits . $suffix : strtolower($chatId);
        }

        if (str_ends_with(strtolower($chatId), '@lid')) {
            $lid = trim(substr($chatId, 0, -4));
            $safeLid = preg_replace('/[^A-Za-z0-9._-]+/', '', $lid);

            return $safeLid ? $safeLid . '@lid' : strtolower($chatId);
        }

        $digits = preg_replace('/\D+/', '', $chatId);

        if (strlen((string) $digits) === 9 && str_starts_with((string) $digits, '9')) {
            $digits = '51' . $digits;
        }

        return $digits ? $digits . '@c.us' : null;
    }

    protected function messageDedupKey(array $payload, array $message, ?string $normalizedChatId): ?string
    {
        foreach ([
            'id._serialized',
            'id.id',
            'messageId',
            'id',
        ] as $path) {
            $value = $this->firstValue($message, [$path]);

            if (is_string($value) && trim($value) !== '' && trim($value) !== 'msg_unknown') {
                return trim($value);
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        $deliveryId = $this->firstString($payload, ['deliveryId', 'delivery_id']);
        if ($deliveryId && $deliveryId !== 'msg_unknown') {
            return $deliveryId;
        }

        $body = $this->firstString($message, ['body', 'text', 'caption', 'message.body', 'message.text', 'message.caption']) ?? '';
        $timestamp = $this->firstString($message, ['timestamp', 't', 'createdAt', 'date']) ?? '';
        $sessionId = $this->firstString($payload, ['sessionId', 'session_id', 'session.id']) ?? '';

        if ($normalizedChatId || $body || $timestamp || $sessionId) {
            return 'hash_' . sha1($sessionId . '|' . $normalizedChatId . '|' . $body . '|' . $timestamp);
        }

        return null;
    }


    protected function extractDni(?string $value): ?string
    {
        return $this->extractDniResult($value)['dni'];
    }

    protected function extractDniResult(?string $value): array
    {
        if (!$value) {
            return [
                'dni' => null,
                'error' => self::DNI_ERROR_MISSING,
            ];
        }

        preg_match_all('/(?<!\d)\d(?:[\s-]*\d)*(?!\d)/u', $value, $matches);

        $validCandidates = [];
        $hasShortCandidate = false;
        $hasLongCandidate = false;

        foreach ($matches[0] ?? [] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate);
            $length = strlen((string) $digits);

            if ($length === 0) {
                continue;
            }

            if ($length === 8) {
                $validCandidates[] = $digits;
                continue;
            }

            if ($length > 8) {
                $hasLongCandidate = true;
                continue;
            }

            $hasShortCandidate = true;
        }

        $validCandidates = array_values(array_unique($validCandidates));

        if (count($validCandidates) > 1) {
            return [
                'dni' => null,
                'error' => self::DNI_ERROR_MULTIPLE,
            ];
        }

        if ($hasLongCandidate) {
            return [
                'dni' => null,
                'error' => self::DNI_ERROR_TOO_LONG,
            ];
        }

        if (count($validCandidates) === 1) {
            return [
                'dni' => $validCandidates[0],
                'error' => null,
            ];
        }

        if ($hasShortCandidate) {
            return [
                'dni' => null,
                'error' => self::DNI_ERROR_TOO_SHORT,
            ];
        }

        return [
            'dni' => null,
            'error' => self::DNI_ERROR_MISSING,
        ];
    }

    protected function confirmedPhoneContext(array $message, array $chatContext): array
    {
        $chatId = strtolower(trim((string) ($chatContext['normalized'] ?? '')));

        if (str_ends_with($chatId, '@c.us')) {
            return [
                'phone' => $this->extractPhone($chatId),
                'source' => 'chat_id_c_us',
                'confidence' => 'high',
            ];
        }

        if (! str_ends_with($chatId, '@lid')) {
            return ['phone' => null, 'source' => null, 'confidence' => null];
        }

        $resolvedLid = strtolower(trim((string) $this->firstString($message, ['sender.lid'])));
        $resolvedPhoneId = $this->firstString($message, ['sender.id']);
        $resolvedPhoneNumber = $this->firstString($message, ['sender.phoneNumber']);
        $source = $this->firstString($message, ['sender.source']);
        $confidence = strtolower(trim((string) $this->firstString($message, ['sender.confidence'])));
        $phoneFromId = $this->extractPhone($resolvedPhoneId);
        $phoneFromNumber = $this->extractPhone($resolvedPhoneNumber);

        $mappingIsConfirmed = $resolvedLid === $chatId
            && is_string($resolvedPhoneId)
            && str_ends_with(strtolower(trim($resolvedPhoneId)), '@c.us')
            && $phoneFromId !== null
            && $phoneFromId === $phoneFromNumber
            && $source === 'whatsapp_web_js.getContactLidAndPhone'
            && $confidence === 'high';

        if (! $mappingIsConfirmed) {
            return ['phone' => null, 'source' => null, 'confidence' => null];
        }

        return [
            'phone' => $phoneFromId,
            'source' => $source,
            'confidence' => $confidence,
        ];
    }

    protected function extractPhone(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '' || str_ends_with($value, '@lid') || str_ends_with($value, '@g.us')) {
            return null;
        }

        if (str_contains($value, '@') && ! str_ends_with($value, '@c.us')) {
            return null;
        }

        $value = preg_replace('/@c\.us$/', '', $value);
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51' . $digits;
        }

        return $digits;
    }

    protected function timestampToDateTime(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return date('Y-m-d H:i:s', $timestamp);
        }

        return (string) $value;
    }

    protected function firstString(array $payload, array $paths): ?string
    {
        $value = $this->firstValue($payload, $paths);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    protected function firstValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->get($payload, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function get(array $payload, string $path): mixed
    {
        $current = $payload;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
