<?php

declare(strict_types=1);

use Modules\Ingestion\Models\QuarantinedRow;

it('reproduces the golden answer key exactly: every item, status and rule', function (): void {
    importAnswerKeyFiles('golden');
    $run = reconcile();

    expect($run->status->value)->toBe('completed')
        ->and($run->provisional)->toBeFalse()
        ->and(QuarantinedRow::query()->count())->toBe(6)
        ->and(runItems($run))->toBe(answerKeyItems('golden'));
});

it('reproduces the volume answer key exactly: all 2,531 items', function (): void {
    importAnswerKeyFiles('volume');
    $run = reconcile();

    expect(QuarantinedRow::query()->count())->toBe(6)
        ->and($run->summary['items'])->toBe(2531)
        ->and(runItems($run))->toBe(answerKeyItems('volume'));
});
