<?php
/**
 * TactiFC — Add new tactics from a compact definition file.
 *
 * Reads tools/new_tactics.php (a PHP array of compact definitions), expands
 * each into a full tactic record for data/tactics.json and a matching entry
 * in data/tactical_intel.json. Existing ids are skipped so this is safe to
 * re-run.
 *
 * After running this, run:
 *   php tools/merge_tactic_meta.php      (copies light intel onto records)
 *   php tools/generate_role_data.php     (fills player roles per FC version)
 *
 * Usage: php tools/add_tactics.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$defsFile = __DIR__ . '/new_tactics.php';
if (!is_file($defsFile)) {
    fwrite(STDERR, "Missing tools/new_tactics.php\n");
    exit(1);
}
$defs = require $defsFile;

$tacticsFile = $root . '/data/tactics.json';
$intelFile   = $root . '/data/tactical_intel.json';
$formations  = json_decode(file_get_contents($root . '/data/formations.json'), true);

/* Map formation name -> slots for building the tactic's own slot list. */
$formationSlots = [];
foreach ($formations as $f) {
    $formationSlots[$f['name']] = $f['slots'];
}

$tactics = json_decode(file_get_contents($tacticsFile), true);
$intel   = json_decode(file_get_contents($intelFile), true);
$existingIds = array_column($tactics, 'id');

$added = 0;
$skipped = 0;

foreach ($defs as $def) {
    $id = (string) $def['id'];
    if (in_array($id, $existingIds, true)) {
        $skipped++;
        continue;
    }

    $formation = (string) $def['formation'];
    $slots = $formationSlots[$formation] ?? [];
    if (!$slots) {
        fwrite(STDERR, "Unknown formation '$formation' for $id\n");
        continue;
    }

    /* Build the tactic's own slots array (roles are filled from the role DB
     * later by generate_role_data.php, but we store the per-slot roles here
     * too so the record is self-describing). */
    $slotRoles = $def['slotRoles'] ?? [];
    $slotOut = [];
    foreach ($slots as $i => $s) {
        $pos = (string) $s['pos'];
        $slotOut[] = [
            'pos'          => $pos,
            'role'         => (string) ($slotRoles[$i] ?? ''),
            'instructions' => (string) ($def['instructions'][$pos] ?? ''),
            'attacking'    => '',
            'defending'    => '',
        ];
    }

    /* Per-version settings. */
    $v = $def['versions'] ?? [];
    $versionOut = [];
    foreach (['fc25', 'fc26', 'fc27'] as $ver) {
        $src = $v[$ver] ?? $v['default'] ?? [];
        $versionOut[$ver] = [
            'formation'         => (string) ($src['formation'] ?? $formation),
            'buildUp'           => (string) ($src['buildUp'] ?? $def['buildUp'] ?? 'Balanced'),
            'defensiveApproach' => (string) ($src['defensiveApproach'] ?? $def['defensiveApproach'] ?? 'Balanced'),
            'width'             => (int) ($src['width'] ?? $def['width'] ?? 5),
            'depth'             => (int) ($src['depth'] ?? $def['depth'] ?? 5),
            'attackingFocus'    => (string) ($src['attackingFocus'] ?? $def['attackingFocus'] ?? 'Balanced'),
            'notes'             => (string) ($src['notes'] ?? $def['notes'] ?? ''),
            'playerInstructions'=> (string) ($src['playerInstructions'] ?? $def['playerInstructions'] ?? ''),
            'players'           => [],
        ];
    }

    $tacticRecord = [
        'id'          => $id,
        'manager'     => (string) $def['manager'],
        'club'        => (string) $def['club'],
        'season'      => (string) $def['season'],
        'formation'   => $formation,
        'style'       => array_values(array_map('strval', $def['style'] ?? [])),
        'difficulty'  => (string) ($def['difficulty'] ?? 'Intermediate'),
        'description' => (string) $def['description'],
        'philosophy'  => (string) $def['philosophy'],
        'slots'       => $slotOut,
        'versions'    => $versionOut,
        // Light intel (also re-applied by merge_tactic_meta.php).
        'gameModes'   => array_values(array_map('strval', $def['gameModes'] ?? ['Career Mode'])),
        'tags'        => array_values(array_map('strval', $def['tags'] ?? ($def['style'] ?? []))),
        'shapes'      => [
            'base'        => $formation,
            'withBall'    => (string) ($def['withBall'] ?? '3-2-5'),
            'withoutBall' => (string) ($def['withoutBall'] ?? '4-4-2'),
        ],
        'dna'         => $def['dna'] ?? [],
        'variations'  => array_values(array_map('strval', $def['variations'] ?? [])),
    ];

    $tactics[] = $tacticRecord;
    $existingIds[] = $id;

    /* Intel entry. */
    $intel['tactics'][$id] = [
        'dna'        => $def['dna'] ?? [],
        'shapes'     => [
            'base'        => $formation,
            'withBall'    => (string) ($def['withBall'] ?? '3-2-5'),
            'withoutBall' => (string) ($def['withoutBall'] ?? '4-4-2'),
        ],
        'gameModes'  => array_values(array_map('strval', $def['gameModes'] ?? ['Career Mode'])),
        'tags'       => array_values(array_map('strval', $def['tags'] ?? ($def['style'] ?? []))),
        'phases'     => $def['phases'] ?? [],
        'howToPlay'  => $def['howToPlay'] ?? [],
        'adjustments'=> $def['adjustments'] ?? [],
    ];

    $added++;
}

file_put_contents($tacticsFile, json_encode($tactics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($intelFile, json_encode($intel, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

echo "Added $added tactics (skipped $skipped already-present). Total now " . count($tactics) . ".\n";
