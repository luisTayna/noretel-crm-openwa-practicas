<?php
require __DIR__ . '/../../Modules/Billing/Services/PaymentReminderService.php';
$service = (new ReflectionClass(\Modules\Billing\Services\PaymentReminderService::class))->newInstanceWithoutConstructor();
$format = new ReflectionMethod($service, 'formatPhoneForMessage');
$format->setAccessible(true);
$message = new ReflectionMethod($service, 'paymentInstructionsMessage');
$message->setAccessible(true);
$cases = [
    ['missing number has no fallback', fn () => $format->invoke($service, '') === ''],
    ['invalid characters have no fallback', fn () => $format->invoke($service, '---') === ''],
    ['no Yape instructions without number', fn () => $message->invoke($service, ['yape_phone' => '', 'yape_receiver_name' => 'Prueba']) === ''],
    ['no Yape instructions with whitespace', fn () => $message->invoke($service, ['yape_phone' => '  ', 'yape_receiver_name' => 'Prueba']) === ''],
    ['explicit synthetic number formats', fn () => $format->invoke($service, '51000000000') === '000 000 000'],
    ['explicit number preserves instructions', fn () => str_contains($message->invoke($service, ['yape_phone' => '000 000 000', 'yape_receiver_name' => 'Prueba']), '000 000 000')],
];
$failures = 0;
foreach ($cases as [$name, $check]) {
    try { $passed = $check(); } catch (Throwable $e) { $passed = false; }
    if ($passed) { echo "PASS {$name}\n"; }
    else { fwrite(STDERR, "FAIL {$name}\n"); $failures++; }
}
echo count($cases) . " cases, {$failures} failures\n";
exit($failures === 0 ? 0 : 1);
