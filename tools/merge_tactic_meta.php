<?php
/**
 * TactiFC — one-off/rerunnable merge script.
 *
 * Copies the lightweight intelligence fields (gameModes, tags, shapes, dna,
 * variations) from data/tactical_intel.json onto each tactic in
 * data/tactics.json so list/filter pages stay fast without loading the heavy
 * intel file.
 *
 * Run:  php tools/merge_tactic_meta.php
 */

$root = dirname(__DIR__);
$intelFile  = $root . '/data/tactical_intel.json';
$tacticsFile = $root . '/data/tactics.json';

$intel = json_decode(file_get_contents($intelFile), true);
$tactics = json_decode(file_get_contents($tacticsFile), true);

if (!is_array($intel) || !isset($intel['tactics'])) {
    fwrite(STDERR, "Could not read tactical_intel.json\n");
    exit(1);
}

/* Common variation sets per base formation family. These are offered as
 * in-tactic alternates (different shapes the same idea can take). */
$variationMap = [
    '4-3-3'         => ['4-2-3-1', '4-3-3 Holding', '4-1-4-1'],
    '4-3-3-holding' => ['4-3-3', '4-2-3-1', '4-1-4-1'],
    '4-2-3-1'       => ['4-3-3', '4-4-2', '4-4-2 Holding'],
    '4-4-2'         => ['4-2-3-1', '4-4-2 Holding', '4-1-2-1-2'],
    '4-4-2-holding' => ['4-4-2', '4-1-4-1', '5-3-2'],
    '4-1-2-1-2'     => ['4-2-2-2', '4-4-2', '4-3-3'],
    '4-2-2-2'       => ['4-1-2-1-2', '4-4-2', '4-2-3-1'],
    '3-5-2'         => ['3-4-2-1', '5-3-2', '3-4-3'],
    '3-4-2-1'       => ['3-4-3', '3-5-2', '5-2-3'],
    '3-4-3'         => ['3-4-2-1', '3-5-2', '5-2-3'],
    '5-3-2'         => ['3-5-2', '5-2-3', '4-4-2 Holding'],
    '5-2-3'         => ['5-3-2', '3-4-3', '3-4-2-1'],
    '4-1-4-1'       => ['4-3-3 Holding', '4-2-3-1', '4-4-2 Holding'],
    '4-3-2-1'       => ['4-3-3', '4-2-3-1', '4-1-2-1-2'],
];

$count = 0;
foreach ($tactics as &$tactic) {
    $id = (string) ($tactic['id'] ?? '');
    if ($id === '' || !isset($intel['tactics'][$id])) {
        continue;
    }
    $meta = $intel['tactics'][$id];

    $tactic['gameModes'] = $meta['gameModes'] ?? ['Career Mode'];
    $tactic['tags']      = $meta['tags'] ?? ($tactic['style'] ?? []);
    $tactic['shapes']    = $meta['shapes'] ?? [
        'base'        => $tactic['formation'] ?? '',
        'withBall'    => $tactic['formation'] ?? '',
        'withoutBall' => $tactic['formation'] ?? '',
    ];
    $tactic['dna'] = $meta['dna'] ?? [];

    // Variations: derive from the base formation family, excluding the base.
    $base = strtolower((string) ($tactic['formation'] ?? ''));
    $variations = $variationMap[$base] ?? [];
    $tactic['variations'] = array_values(array_filter($variations, static fn ($v) => strcasecmp($v, (string) ($tactic['formation'] ?? '')) !== 0));

    $count++;
}
unset($tactic);

file_put_contents(
    $tacticsFile,
    json_encode($tactics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "Merged intelligence metadata into $count tactics.\n";
