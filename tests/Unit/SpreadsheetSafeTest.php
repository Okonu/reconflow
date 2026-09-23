<?php

declare(strict_types=1);

use App\Support\Export\SpreadsheetSafe;

it('neutralises values that spreadsheets would treat as formulas', function (mixed $value, mixed $expected): void {
    expect(SpreadsheetSafe::escape($value))->toBe($expected);
})->with([
    ['=HYPERLINK("http://evil")', "'=HYPERLINK(\"http://evil\")"],
    ['+SUM(A1)', "'+SUM(A1)"],
    ['-2+3', "'-2+3"],
    ['@cmd', "'@cmd"],
    ["\tTab", "'\tTab"],
    ['TUP-S-000001', 'TUP-S-000001'],
    [42, 42],
    [null, null],
]);
