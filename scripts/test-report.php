<?php

declare(strict_types=1);

$junit = $argv[1] ?? 'build/junit.xml';
$clover = $argv[2] ?? null;
$slowNote = $argv[3] ?? null;
$output = 'docs/test-report.md';

$xml = simplexml_load_file($junit);
if ($xml === false) {
    fwrite(STDERR, "Cannot read {$junit}\n");
    exit(1);
}

$groups = [];
foreach ($xml->xpath('//testcase') ?: [] as $case) {
    $file = (string) $case['file'];
    $relative = str_replace(getcwd().'/', '', $file);
    $group = preg_match('#^Modules/([^/]+)/tests/(Unit|Feature)#', $relative, $m) ? "{$m[1]} ({$m[2]})" : (preg_match('#^tests/([^/]+)#', $relative, $m) ? "Shared ({$m[1]})" : 'Other');
    $groups[$group] ??= ['tests' => 0, 'failed' => 0, 'skipped' => 0, 'time' => 0.0];
    $groups[$group]['tests']++;
    $groups[$group]['time'] += (float) $case['time'];
    if (isset($case->failure) || isset($case->error)) {
        $groups[$group]['failed']++;
    }
    if (isset($case->skipped)) {
        $groups[$group]['skipped']++;
    }
}
ksort($groups);

$totals = array_reduce($groups, fn (array $c, array $g): array => [
    'tests' => $c['tests'] + $g['tests'],
    'failed' => $c['failed'] + $g['failed'],
    'skipped' => $c['skipped'] + $g['skipped'],
    'time' => $c['time'] + $g['time'],
], ['tests' => 0, 'failed' => 0, 'skipped' => 0, 'time' => 0.0]);
$assertions = (int) ($xml->testsuite['assertions'] ?? 0);

$coverage = 'not measured in this run (no coverage driver); CI measures it with `--coverage`';
if ($clover !== null && is_file($clover)) {
    $metrics = simplexml_load_file($clover)?->project->metrics;
    if ($metrics !== null && (int) $metrics['statements'] > 0) {
        $coverage = sprintf('%.1f%% of statements (%d / %d)', 100 * (int) $metrics['coveredstatements'] / (int) $metrics['statements'], (int) $metrics['coveredstatements'], (int) $metrics['statements']);
    }
}

$lines = [
    '# Test report',
    '',
    'Generated '.gmdate('Y-m-d H:i').' UTC by `php scripts/test-report.php` from the latest Pest run.',
    '',
    '| Metric | Value |',
    '|---|---|',
    "| Tests | {$totals['tests']} |",
    '| Passed | '.($totals['tests'] - $totals['failed'] - $totals['skipped']).' |',
    "| Failed | {$totals['failed']} |",
    "| Skipped | {$totals['skipped']} |",
    "| Assertions | {$assertions} |",
    sprintf('| Duration | %.1f s |', $totals['time']),
    "| Coverage | {$coverage} |",
];
if ($slowNote !== null && $slowNote !== '') {
    $lines[] = "| Performance (group `slow`) | {$slowNote} |";
}
$lines = [...$lines, '', '## By suite', '', '| Suite | Tests | Failed | Skipped | Time |', '|---|---|---|---|---|'];
foreach ($groups as $name => $g) {
    $lines[] = sprintf('| %s | %d | %d | %d | %.1f s |', $name, $g['tests'], $g['failed'], $g['skipped'], $g['time']);
}
$lines = [...$lines, '', '## What the suite proves', '',
    '- **Answer keys**: the golden file (65 items) and the volume file (2,531 items) are reproduced exactly: status and rule for every item, plus quarantine rows.',
    '- **Rules**: each rule R1–R7 has unit tests on the pure engine, including boundaries (tolerance, fuzzy window, duplicate window, cut-off, payment window), ties, splits, prior-day matching, escalation and manual matches.',
    '- **Workflow**: an end-to-end scripted flow covers the golden import, exceptions, maker-checker with the SoD block, idempotent ERP posting and replay, the high-value threshold, ERP failure and retry, fuzzy confirm and reject, sign-off blockers, the date lock, reopen, and re-run relinking.',
    '- **Controls**: the permission contract checks every authenticated route (denied without its permission, allowed with only it). Architecture tests cover strict types, no role-name checks, `env()` only in config, no comments, and page resolution. The audit chain detects tampering; the archive keeps it verifiable.',
    '- **AI governance**: no raw phone number is ever sent to the model; prompt and input hashes are stored; an override needs a reason; the AI never writes workflow tables; schema violations are rejected; the kill switch stops all calls; the narrative is built from aggregates only; the eval set runs offline.',
    '- **Data protection**: masked views and exports, formula-safe cells, audited unmask and unmasked export, erasure by anonymisation, and retention anonymisation.',
    '',
];

file_put_contents($output, implode("\n", $lines));
echo "Wrote {$output}\n";
