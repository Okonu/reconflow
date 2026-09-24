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

$coverage = 'Not measured in this run (no coverage driver). CI measures it with `--coverage`';
if ($clover !== null && is_file($clover)) {
    $metrics = simplexml_load_file($clover)?->project->metrics;
    if ($metrics !== null && (int) $metrics['statements'] > 0) {
        $coverage = sprintf('%.1f%% of statements (%d / %d)', 100 * (int) $metrics['coveredstatements'] / (int) $metrics['statements'], (int) $metrics['coveredstatements'], (int) $metrics['statements']);
    }
}

$lines = [
    '# Test report',
    '',
    'This report shows the results of the latest Pest run. `php scripts/test-report.php` made it on '.gmdate('Y-m-d H:i').' UTC.',
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
$lines = [...$lines, '', '## What the tests prove', '',
    '- **Answer keys:** the engine reproduces the golden file (65 items) and the volume file (2,531 items) exactly. This includes the status and the rule of each item, and the quarantine rows.',
    '- **Rules:** each rule from R1 to R7 has unit tests on the engine. The tests include the limits (tolerance, fuzzy window, duplicate window, cut-off, payment window), ties, splits, prior-day matches, escalation and manual matches.',
    '- **Workflow:** one test does the full flow. It covers the golden import, exceptions, maker-checker and the block on self-approval, ERP posting one time only, the value threshold, ERP failure and retry, fuzzy confirm and reject, sign-off blockers, the date lock, reopen, and the relink at a new run.',
    '- **Controls:** the permission contract checks each route. A user without the permission gets a refusal. A user with only that permission gets access. Architecture tests check strict types, no role-name checks, `env()` only in configuration files, no comments, and the page files. The audit chain finds changes. The archive keeps the chain possible to check.',
    '- **AI governance:** no phone number goes to the model. The system keeps the prompt and input hashes. An override needs a reason. The AI never writes to workflow tables. The system rejects output that does not agree with the schema. The kill switch stops all calls. The summary uses totals only. The evaluation set operates offline.',
    '- **Data protection:** masked pages and exports, safe cells in exports, audited unmask and unmasked export, erasure by anonymisation, and anonymisation at the end of retention.',
    '',
];

file_put_contents($output, implode("\n", $lines));
echo "Wrote {$output}\n";
