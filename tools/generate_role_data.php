<?php
/**
 * One-off generator: inject per-version "players" (position/role/focus/familiarity)
 * into every tactic in data/tactics.json, using roles that are valid for that
 * game version according to data/player_roles.json.
 *
 * Run:  php tools/generate_role_data.php
 */

$root = dirname(__DIR__);
$tactics = json_decode(file_get_contents($root . '/data/tactics.json'), true);
$roleDb  = json_decode(file_get_contents($root . '/data/player_roles.json'), true);
$formations = json_decode(file_get_contents($root . '/data/formations.json'), true);

/* Build formation slot map: formation name => slots. */
$formationSlots = [];
foreach ($formations as $f) {
    $formationSlots[$f['name']] = $f['slots'];
}

/** Check a role exists for a position in a version. */
function roleOk(array $roleDb, string $version, string $position, string $role): bool
{
    foreach ($roleDb[$version][$position] ?? [] as $entry) {
        if (strcasecmp($entry['role'], $role) === 0) {
            return true;
        }
    }
    return false;
}

/** First focus for a role in a version. */
function firstFocus(array $roleDb, string $version, string $position, string $role): string
{
    foreach ($roleDb[$version][$position] ?? [] as $entry) {
        if (strcasecmp($entry['role'], $role) === 0) {
            return $entry['focuses'][0] ?? '';
        }
    }
    return '';
}

/**
 * Map an exact pitch-slot label onto a base role position key that exists
 * in the roles database (e.g. RCB -> CB, RDM -> CDM, LAM -> CAM).
 */
function basePosition(string $position): string
{
    $aliases = [
        'RWB' => 'RB', 'LWB' => 'LB',
        'LCB' => 'CB', 'RCB' => 'CB',
        'RDM' => 'CDM', 'LDM' => 'CDM',
        'LST' => 'ST', 'RST' => 'ST',
        'LAM' => 'CAM', 'RAM' => 'CAM',
        'LCM' => 'CM', 'RCM' => 'CM',
    ];
    return $aliases[$position] ?? $position;
}

/**
 * Choose a role for a position given the tactic's style, restricted to
 * roles valid in the given version. Falls back to the first available role.
 */
function chooseRole(array $roleDb, string $version, string $position, array $styleTags, int $occurrence = 0): string
{
    $group = basePosition($position);
    $available = array_map(static fn ($e) => $e['role'], $roleDb[$version][$group] ?? []);
    if (!$available) {
        return '';
    }

    $normalizedStyle = array_map('strtolower', $styleTags);

    // Preference lists per position group, ordered; first match wins.
    $preferences = [
        'GK'  => ['Ball-Playing Keeper', 'Sweeper Keeper', 'Goalkeeper'],
        'CB'  => ['Ball-Playing Defender', 'Stopper', 'Defender', 'Wide Back'],
        'RB'  => ['Inverted Wingback', 'Wing Back', 'Inverted Full Back', 'Full Back'],
        'LB'  => ['Inverted Wingback', 'Wing Back', 'Inverted Full Back', 'Full Back'],
        'CDM' => ['Deep-Lying Playmaker', 'Holding', 'Ball Winner'],
        'CM'  => ['Mezzala', 'Box to Box', 'Box Crasher', 'Playmaker', 'Deep-Lying Playmaker'],
        'CAM' => ['Playmaker', 'Advanced Playmaker', 'Shadow Striker', 'Box Crasher'],
        'RM'  => ['Wide Playmaker', 'Inside Forward', 'Winger'],
        'LM'  => ['Wide Playmaker', 'Inside Forward', 'Winger'],
        'RW'  => ['Inside Forward', 'Winger', 'Box Crasher'],
        'LW'  => ['Inside Forward', 'Winger', 'Box Crasher'],
        'ST'  => ['Complete Forward', 'Advanced Forward', 'False Nine', 'Poacher', 'Target Forward', 'Box Crasher'],
    ];

    // Style-driven overrides (tweak the ordering).
    $isDefensive = (bool) array_intersect($normalizedStyle, ['low block', 'defensive', 'compact', 'counter attack']);
    $isPossession = (bool) array_intersect($normalizedStyle, ['possession', 'positional play', 'technical']);
    $isPressing = (bool) array_intersect($normalizedStyle, ['high press', 'counter press']);

    $pref = $preferences[$group] ?? $available;

    if ($isDefensive && $group === 'ST') {
        $pref = ['Target Forward', 'Poacher', 'Advanced Forward', 'Complete Forward', 'Box Crasher', 'False Nine'];
    }
    if ($isPossession && $group === 'ST') {
        $pref = ['False Nine', 'Complete Forward', 'Advanced Forward', 'Poacher', 'Target Forward', 'Box Crasher'];
    }
    if ($isPressing && ($group === 'CM')) {
        $pref = ['Box to Box', 'Mezzala', 'Box Crasher', 'Playmaker', 'Deep-Lying Playmaker'];
    }
    if ($isPossession && $group === 'CM') {
        $pref = ['Mezzala', 'Playmaker', 'Box to Box', 'Deep-Lying Playmaker', 'Box Crasher'];
    }
    if ($isPossession && $group === 'CDM') {
        $pref = ['Deep-Lying Playmaker', 'Holding', 'Ball Winner'];
    }
    if ($isDefensive && $group === 'CDM') {
        $pref = ['Holding', 'Ball Winner', 'Deep-Lying Playmaker'];
    }

    foreach ($pref as $candidate) {
        if (in_array($candidate, $available, true)) {
            return $candidate;
        }
    }
    return $available[0];
}

/**
 * Like chooseRole but rotates through the valid preference list so that
 * multiple players in the same position group get varied roles. This makes
 * the dataset use roles like Poacher, Ball Winner, Defender and Winger.
 */
function chooseRoleRotated(array $roleDb, string $version, string $position, array $styleTags, int $occurrence): string
{
    $group = basePosition($position);
    $available = array_map(static fn ($e) => $e['role'], $roleDb[$version][$group] ?? []);
    if (!$available) {
        return '';
    }

    $primary = chooseRole($roleDb, $version, $position, $styleTags, 0);
    if ($occurrence <= 0 || count($available) < 2) {
        return $primary;
    }

    // Build a preference order starting at the primary, then the rest.
    $order = [];
    if ($primary !== '' && in_array($primary, $available, true)) {
        $order[] = $primary;
    }
    foreach ($available as $role) {
        if (!in_array($role, $order, true)) {
            $order[] = $role;
        }
    }
    return $order[$occurrence % count($order)];
}

/** Familiarity: give key players higher familiarity. */
function familiarityFor(int $index, int $total, string $style): string
{
    // Roughly top third get ++, middle get +, rest base.
    $ratio = $total > 0 ? $index / $total : 0;
    if ($ratio < 0.34) {
        return 'Role++';
    }
    if ($ratio < 0.7) {
        return 'Role+';
    }
    return 'Role';
}

foreach ($tactics as &$tactic) {
    $formationName = $tactic['formation'];
    $slots = $formationSlots[$formationName] ?? [];
    $styleTags = $tactic['style'] ?? [];

    // Version-specific formation name (some tactics override per version).
    foreach ($tactic['versions'] as $version => &$versionData) {
        $vFormation = $versionData['formation'] ?? $formationName;

        // Build ordered player list from this tactic's own pitch slots when
        // available (tactic['slots']), otherwise from the formation.
        $sourceSlots = [];
        if (!empty($tactic['slots'])) {
            foreach ($tactic['slots'] as $s) {
                $sourceSlots[] = $s['pos'];
            }
        } elseif (!empty($formationSlots[$vFormation])) {
            foreach ($formationSlots[$vFormation] as $s) {
                $sourceSlots[] = $s['pos'];
            }
        }

        // If the version-specific formation differs, fall back to its slots.
        if (!empty($versionData['formation']) && $versionData['formation'] !== $formationName && !empty($formationSlots[$vFormation])) {
            $sourceSlots = array_map(static fn ($s) => $s['pos'], $formationSlots[$vFormation]);
        }

        $players = [];
        $total = count($sourceSlots);
        $occurrences = [];
        foreach ($sourceSlots as $i => $pos) {
            $base = basePosition($pos);
            $occ = $occurrences[$base] ?? 0;
            $occurrences[$base] = $occ + 1;
            // Rotate roles so repeated positions get varied valid roles.
            $role = chooseRoleRotated($roleDb, $version, $pos, $styleTags, $occ);
            if ($role === '') {
                continue;
            }
            $focus = firstFocus($roleDb, $version, $base, $role);
            $players[] = [
                'position'    => $pos,
                'role'        => $role,
                'focus'       => $focus,
                'familiarity' => familiarityFor($i, $total, implode(' ', $styleTags)),
            ];
        }
        $versionData['players'] = $players;
    }
    unset($versionData);
}
unset($tactic);

file_put_contents(
    $root . '/data/tactics.json',
    json_encode($tactics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

echo "Done. Updated " . count($tactics) . " tactics.\n";
