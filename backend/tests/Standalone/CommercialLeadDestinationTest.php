<?php
namespace Modules\Customer\Entities {
    class Lead {
        public function __construct(public array $attributes) {}
        public static function create(array $attributes): self { return new self($attributes); }
    }
}
namespace {
    function config(string $key, mixed $default = null): mixed {
        return $key === 'services.whatsapp.business_destination' ? $GLOBALS['destination'] : $default;
    }
    function now(): string { return '2000-01-01 00:00:00'; }
    require __DIR__ . '/../../Modules/Customer/Services/CommercialLeadService.php';
    $service = new \Modules\Customer\Services\CommercialLeadService();
    $cases = [
        ['missing configuration', null, null],
        ['empty configuration', '', null],
        ['whitespace configuration', '   ', null],
        ['non-string configuration', ['test'], null],
        ['explicit synthetic destination', '00000000000', '00000000000'],
    ];
    $failures = 0;
    foreach ($cases as [$name, $value, $wanted]) {
        $GLOBALS['destination'] = $value;
        try {
            $lead = $service->trackWhatsAppClick([], '127.0.0.1', 'test');
            $metadata = $lead->attributes['metadata'];
            $actual = $metadata['clicked_to'] ?? null;
            if ($actual !== $wanted || ($wanted === null && array_key_exists('clicked_to', $metadata))) {
                fwrite(STDERR, "FAIL {$name}\n"); $failures++;
            } else { echo "PASS {$name}\n"; }
        } catch (\Throwable $error) {
            fwrite(STDERR, "FAIL {$name}: " . get_class($error) . "\n"); $failures++;
        }
    }
    echo count($cases) . " cases, {$failures} failures\n";
    exit($failures === 0 ? 0 : 1);
}
