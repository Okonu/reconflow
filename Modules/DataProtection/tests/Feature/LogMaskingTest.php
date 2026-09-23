<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Modules\DataProtection\Logging\MaskPersonalData;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;

it('never writes phone numbers or tokens to the logs', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'log');
    config(['logging.channels.masking_probe' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'handler_with' => ['stream' => $path],
        'formatter' => JsonFormatter::class,
        'tap' => [MaskPersonalData::class],
    ]]);
    $logger = Log::channel('masking_probe');
    $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl';

    $logger->info('payment received from 254700123456', [
        'payer_phone' => '254711222333',
        'headers' => ['authorization' => "Bearer {$jwt}"],
        'note' => "token {$jwt}",
    ]);
    $output = (string) file_get_contents($path);

    foreach (['254700123456', '254711222333', $jwt] as $leaked) {
        expect($output)->not->toContain($leaked);
    }
    $line = json_decode(trim($output), true);
    expect($line['context']['payer_phone'])->toBe('07•• ••• 333')
        ->and($line['context']['headers']['authorization'])->toBe('[REDACTED]');
});

it('applies the masking tap to every configured log channel that writes output', function (string $channel): void {
    expect(config("logging.channels.{$channel}.tap"))->toContain(MaskPersonalData::class);
})->with(['json', 'single', 'daily', 'stderr']);
