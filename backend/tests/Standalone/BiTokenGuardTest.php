<?php
namespace Illuminate\Routing { class Controller {} }
namespace Illuminate\Http {
    class Request {
        public function __construct(private mixed $token, private mixed $bodyToken = null) {}
        public function header(string $name): mixed { return $this->token; }
        public function input(string $name, mixed $default = null): mixed {
            return $name === 'token' ? $this->bodyToken : $default;
        }
    }
}
namespace Carbon { class Carbon { public static function now(): self { return new self(); } } }
namespace {
    class StandaloneResponse {
        public function __construct(public int $status, public array $data) {}
    }
    class StandaloneResponseFactory {
        public function json(array $data, int $status = 200): StandaloneResponse {
            return new StandaloneResponse($status, $data);
        }
    }
    function config(string $key, mixed $default = null): mixed { return $GLOBALS['bi_expected']; }
    function response(): StandaloneResponseFactory { return new StandaloneResponseFactory(); }
    require __DIR__ . '/../../Modules/Customer/Http/Controllers/CustomerAPIController.php';
    $controller = (new ReflectionClass(\Modules\Customer\Http\Controllers\CustomerAPIController::class))->newInstanceWithoutConstructor();
    $cases = [
        ['missing configuration and header', null, null, null, 401],
        ['empty configuration and header', '', null, null, 401],
        ['null config with supplied header', null, 'sample-token', null, 401],
        ['missing header', 'sample-token', null, null, 401],
        ['empty header', 'sample-token', '', null, 401],
        ['body token without header', 'sample-token', null, 'sample-token', 401],
        ['wrong token', 'sample-token', 'wrong-token', null, 401],
        ['non-string header', 'sample-token', ['sample-token'], null, 401],
        ['non-string config', ['sample-token'], ['sample-token'], null, 401],
        ['valid explicit token', 'sample-token', 'sample-token', null, 200],
    ];
    $failures = 0;
    foreach ($cases as [$name, $expected, $header, $body, $status]) {
        $GLOBALS['bi_expected'] = $expected;
        try {
            $result = $controller->syncBiClassification(new \Illuminate\Http\Request($header, $body));
            if ($result->status !== $status) { fwrite(STDERR, "FAIL {$name}\n"); $failures++; }
            else { echo "PASS {$name}\n"; }
        } catch (\Throwable $error) {
            fwrite(STDERR, "FAIL {$name}: " . get_class($error) . "\n"); $failures++;
        }
    }
    echo count($cases) . " cases, {$failures} failures\n";
    exit($failures === 0 ? 0 : 1);
}
