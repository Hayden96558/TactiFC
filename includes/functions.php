<?php
/**
 * TactiFC — Core helper functions.
 *
 * Loads the JSON "database", exposes search / filter helpers and
 * provides small rendering utilities used across the site.
 *
 * Player Roles system:
 *   - Roles live in data/player_roles.json keyed by FC version.
 *   - Roles and focuses are version-specific (FC25 / FC26 / FC27 differ).
 *   - All role/focus lookups and validation flow through this file so the
 *     UI can never offer an invalid position/role/focus combination.
 */

declare(strict_types=1);

if (!defined('TACTIFC_ROOT')) {
    define('TACTIFC_ROOT', dirname(__DIR__));
}

/* -------------------------------------------------------------------------
 * Generic JSON loading (cached for the duration of the request)
 * ---------------------------------------------------------------------- */

/**
 * Load and decode a JSON file from the /data directory.
 *
 * @return array<int|string, mixed>
 */
function loadJson(string $file): array
{
    static $cache = [];

    if (array_key_exists($file, $cache)) {
        return $cache[$file];
    }

    $path = TACTIFC_ROOT . '/data/' . basename($file);
    if (!is_file($path)) {
        return $cache[$file] = [];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return $cache[$file] = [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $cache[$file] = [];
    }

    return $cache[$file] = $decoded;
}

/* -------------------------------------------------------------------------
 * Collection loaders
 * ---------------------------------------------------------------------- */

/** @return array<int, array<string, mixed>> */
function loadTactics(): array
{
    return loadJson('tactics.json');
}

/** @return array<int, array<string, mixed>> */
function loadManagers(): array
{
    return loadJson('managers.json');
}

/** @return array<int, array<string, mixed>> */
function loadClubs(): array
{
    return loadJson('clubs.json');
}

/** @return array<int, array<string, mixed>> */
function loadFormations(): array
{
    return loadJson('formations.json');
}

/** @return array<string, array<string, array<int, array<string, mixed>>>> */
function loadPlayerRoles(): array
{
    return loadJson('player_roles.json');
}

/* -------------------------------------------------------------------------
 * Lookups
 * ---------------------------------------------------------------------- */

/** @return array<string, mixed>|null */
function getTacticById(string $id): ?array
{
    foreach (loadTactics() as $tactic) {
        if (($tactic['id'] ?? '') === $id) {
            return $tactic;
        }
    }
    return null;
}

/**
 * Look up a formation by its id OR its display name (so manually-authored
 * tactics can reference either). Ids are checked first.
 *
 * @return array<string, mixed>|null
 */
function getFormationById(string $id): ?array
{
    $needle = trim($id);
    $needleNorm = normalizeText(str_replace('-', ' ', $needle));
    foreach (loadFormations() as $formation) {
        if ((string) ($formation['id'] ?? '') === $needle) {
            return $formation;
        }
    }
    // Fall back to matching by display name (accent/spacing/case-insensitive).
    foreach (loadFormations() as $formation) {
        $name = (string) ($formation['name'] ?? '');
        if (strcasecmp($name, $needle) === 0) {
            return $formation;
        }
        if (normalizeText(str_replace('-', ' ', $name)) === $needleNorm) {
            return $formation;
        }
    }
    return null;
}

/** @return array<string, mixed>|null */
function getManagerByName(string $name): ?array
{
    foreach (loadManagers() as $manager) {
        if (strcasecmp($manager['name'] ?? '', $name) === 0) {
            return $manager;
        }
    }
    return null;
}

/* -------------------------------------------------------------------------
 * Player Roles system
 * ---------------------------------------------------------------------- */

/** All version keys that have a roles database. @return array<int, string> */
function roleVersions(): array
{
    return array_keys(loadPlayerRoles());
}

/**
 * Is this a valid FC version key (fc25 / fc26 / fc27 …)?
 */
function isValidVersion(string $version): bool
{
    return array_key_exists(strtolower($version), loadPlayerRoles());
}

/**
 * Return the full role map for a version: [position => [ {role, focuses, ...}, ... ]].
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function getRolesForVersion(string $version): array
{
    $version = strtolower($version);
    $db = loadPlayerRoles();
    return $db[$version] ?? [];
}

/**
 * All positions available in a version.
 *
 * @return array<int, string>
 */
function getPositionsForVersion(string $version): array
{
    return array_keys(getRolesForVersion($version));
}

/**
 * All known positions across every version (ordered sensibly).
 *
 * @return array<int, string>
 */
function allPositions(): array
{
    $order = ['GK', 'RB', 'CB', 'LB', 'RWB', 'LWB', 'CDM', 'CM', 'CAM', 'RM', 'LM', 'RW', 'LW', 'ST'];
    $found = [];
    foreach (loadPlayerRoles() as $positions) {
        foreach (array_keys($positions) as $position) {
            $found[$position] = true;
        }
    }
    $ordered = array_values(array_filter($order, static fn ($p) => isset($found[$p])));
    foreach (array_keys($found) as $position) {
        if (!in_array($position, $ordered, true)) {
            $ordered[] = $position;
        }
    }
    return $ordered;
}

/**
 * Return the list of role definitions available for a position in a version.
 *
 * @return array<int, array<string, mixed>>
 */
function getRolesForPosition(string $position, string $version): array
{
    $map = getRolesForVersion($version);
    $position = strtoupper($position);
    if (!isset($map[$position])) {
        return [];
    }
    return $map[$position];
}

/**
 * Does this role exist for this position in this version?
 */
function roleExists(string $position, string $role, string $version): bool
{
    foreach (getRolesForPosition($position, $version) as $entry) {
        if (strcasecmp((string) ($entry['role'] ?? ''), $role) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Return the focus options for a specific role.
 *
 * @return array<int, string>
 */
function getFocusesForRole(string $position, string $role, string $version): array
{
    foreach (getRolesForPosition($position, $version) as $entry) {
        if (strcasecmp((string) ($entry['role'] ?? ''), $role) === 0) {
            $focuses = $entry['focuses'] ?? [];
            return is_array($focuses) ? array_values(array_map('strval', $focuses)) : [];
        }
    }
    return [];
}

/**
 * Return the full role definition (description + behaviours) for a role.
 *
 * @return array<string, mixed>|null
 */
function getRoleDefinition(string $position, string $role, string $version): ?array
{
    foreach (getRolesForPosition($position, $version) as $entry) {
        if (strcasecmp((string) ($entry['role'] ?? ''), $role) === 0) {
            return $entry;
        }
    }
    return null;
}

/**
 * Validate a full position + role + focus selection for a version.
 * Returns an array with 'valid' and a normalised 'focus'.
 *
 * @return array{valid: bool, role: string, focus: string, reason: string}
 */
function validateRole(string $position, string $role, string $focus, string $version): array
{
    $position = strtoupper(trim($position));
    $role     = trim($role);
    $focus    = trim($focus);

    if (!roleExists($position, $role, $version)) {
        return ['valid' => false, 'role' => '', 'focus' => '', 'reason' => "Role '$role' is not available for $position in " . versionLabel($version) . '.'];
    }

    $focuses = getFocusesForRole($position, $role, $version);
    if ($focus !== '' && !in_array($focus, $focuses, true)) {
        $fallback = $focuses[0] ?? '';
        return ['valid' => false, 'role' => $role, 'focus' => $fallback, 'reason' => "Focus '$focus' is not available for '$role'. Using '$fallback'."];
    }

    return ['valid' => true, 'role' => $role, 'focus' => $focus !== '' ? $focus : ($focuses[0] ?? ''), 'reason' => ''];
}

/**
 * Role familiarity levels supported by FC25+.
 *
 * @return array<int, string>
 */
function familiarityLevels(): array
{
    return ['', 'Role', 'Role+', 'Role++'];
}

/**
 * Human label / star display for a familiarity value.
 */
function familiarityLabel(string $level): string
{
    return match ($level) {
        'Role'  => 'Role',
        'Role+' => 'Role+',
        'Role++' => 'Role++',
        default => '',
    };
}

/**
 * Stars representing familiarity (0-3).
 */
function familiarityStars(string $level): int
{
    return match ($level) {
        'Role'   => 1,
        'Role+'  => 2,
        'Role++' => 3,
        default  => 0,
    };
}

/**
 * Find every role definition across all versions for a role name.
 *
 * @return array<int, array{version: string, position: string, entry: array<string, mixed>}>
 */
function findRoleAcrossVersions(string $role): array
{
    $results = [];
    foreach (loadPlayerRoles() as $version => $positions) {
        foreach ($positions as $position => $entries) {
            foreach ($entries as $entry) {
                if (strcasecmp((string) ($entry['role'] ?? ''), $role) === 0) {
                    $results[] = ['version' => $version, 'position' => $position, 'entry' => $entry];
                }
            }
        }
    }
    return $results;
}

/**
 * Return a de-duplicated catalogue of every role, merging version data.
 *
 * Each item: name, positions[], focuses[], versions[], description,
 * attacking[], defending[].
 *
 * @return array<int, array<string, mixed>>
 */
function roleCatalogue(): array
{
    $catalogue = [];
    foreach (loadPlayerRoles() as $version => $positions) {
        foreach ($positions as $position => $entries) {
            foreach ($entries as $entry) {
                $name = (string) ($entry['role'] ?? '');
                if ($name === '') {
                    continue;
                }
                $key = strtolower($name);
                if (!isset($catalogue[$key])) {
                    $catalogue[$key] = [
                        'name'        => $name,
                        'positions'   => [],
                        'focuses'     => [],
                        'versions'    => [],
                        'description' => (string) ($entry['description'] ?? ''),
                        'attacking'   => $entry['attacking'] ?? [],
                        'defending'   => $entry['defending'] ?? [],
                    ];
                }
                if (!in_array($position, $catalogue[$key]['positions'], true)) {
                    $catalogue[$key]['positions'][] = $position;
                }
                foreach ($entry['focuses'] ?? [] as $focus) {
                    if (!in_array($focus, $catalogue[$key]['focuses'], true)) {
                        $catalogue[$key]['focuses'][] = $focus;
                    }
                }
                if (!in_array($version, $catalogue[$key]['versions'], true)) {
                    $catalogue[$key]['versions'][] = $version;
                }
                // Prefer the richest description available.
                if (($catalogue[$key]['description'] === '') && !empty($entry['description'])) {
                    $catalogue[$key]['description'] = (string) $entry['description'];
                }
            }
        }
    }
    // Order positions within each role.
    $positionOrder = allPositions();
    foreach ($catalogue as &$item) {
        usort($item['positions'], static function ($a, $b) use ($positionOrder) {
            return array_search($a, $positionOrder, true) <=> array_search($b, $positionOrder, true);
        });
        sort($item['versions']);
    }
    unset($item);

    $list = array_values($catalogue);
    usort($list, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
    return $list;
}

/**
 * Get a single role from the catalogue by name.
 *
 * @return array<string, mixed>|null
 */
function getRoleFromCatalogue(string $role): ?array
{
    foreach (roleCatalogue() as $item) {
        if (strcasecmp($item['name'], $role) === 0) {
            return $item;
        }
    }
    return null;
}

/* -------------------------------------------------------------------------
 * Search & filtering
 * ---------------------------------------------------------------------- */

/**
 * Normalise a string for accent-insensitive, fuzzy-ish matching.
 */
function normalizeText(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        $converted = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        if (is_string($converted)) {
            $value = $converted;
        }
    } else {
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'æ' => 'ae',
        ]);
    }
    return $value;
}

/**
 * Convert a search term like "433" into a formation with dashes "4-3-3".
 *
 * @return array<int, string>
 */
function formationSearchVariants(string $term): array
{
    $variants = [$term];
    if (preg_match('/^\d{3,5}$/', $term)) {
        $variants[] = implode('-', str_split($term));
    }
    return $variants;
}

/**
 * Build the list of player role strings stored inside a tactic across
 * every version (for search and role filters).
 *
 * @return array<int, string>
 */
function tacticPlayerRoles(array $tactic): array
{
    $roles = [];
    foreach ($tactic['versions'] ?? [] as $versionData) {
        foreach ($versionData['players'] ?? [] as $player) {
            if (!empty($player['role'])) {
                $roles[] = (string) $player['role'];
            }
            if (!empty($player['focus'])) {
                $roles[] = (string) $player['focus'];
            }
            if (!empty($player['position'])) {
                $roles[] = (string) $player['position'];
            }
        }
    }
    return array_values(array_unique($roles));
}

/**
 * Return the distinct key roles a tactic uses (deduped, ordered by first use).
 *
 * @return array<int, array{position: string, role: string, focus: string}>
 */
function tacticKeyRoles(array $tactic, int $limit = 3): array
{
    $out = [];
    $seen = [];
    foreach ($tactic['versions'] ?? [] as $versionData) {
        foreach ($versionData['players'] ?? [] as $player) {
            $role = (string) ($player['role'] ?? '');
            if ($role === '' || isset($seen[$role])) {
                continue;
            }
            $seen[$role] = true;
            $out[] = [
                'position' => (string) ($player['position'] ?? ''),
                'role'     => $role,
                'focus'    => (string) ($player['focus'] ?? ''),
            ];
            if (count($out) >= $limit) {
                return $out;
            }
        }
    }
    return $out;
}

/**
 * Common search aliases so nicknames resolve to the stored names
 * (e.g. "barca" -> Barcelona, "utd" -> United, "mourinho" is already stored).
 *
 * @return array<int, string>
 */
function searchAliases(string $manager, string $club): array
{
    $map = [
        'barcelona'   => ['barca', 'bcn'],
        'manchester united' => ['man utd', 'utd', 'man united'],
        'manchester city'   => ['man city', 'city'],
        'real madrid' => ['madrid', 'los blancos'],
        'atlético madrid' => ['atletico', 'atleti'],
        'atletico madrid' => ['atletico', 'atleti'],
        'paris saint-germain' => ['psg', 'paris'],
        'bayern munich' => ['bayern', 'munich'],
        'inter milan' => ['inter', 'nerazzurri'],
        'ac milan'    => ['milan', 'rossoneri'],
        'juventus'    => ['juve'],
        'tottenham hotspur' => ['spurs', 'tottenham'],
        'borussia dortmund' => ['dortmund', 'bvb'],
        'fc porto'    => ['porto'],
        'as roma'     => ['roma'],
        'leicester city' => ['leicester'],
        'ajax'        => ['amsterdam'],
        'villarreal'  => ['villarreal', 'villareal'],
        'athletic club' => ['athletic', 'athletic bilbao', 'bilbao'],
        'leeds united' => ['leeds'],
        'afc bournemouth' => ['bournemouth', 'afc bournemouth'],
        'everton'     => ['everton'],
        'crystal palace' => ['palace', 'crystal palace'],
        'eintracht frankfurt' => ['frankfurt', 'eintracht'],
        'sevilla'     => ['sevilla'],
    ];

    $aliases = [];
    $clubKey = normalizeText($club);
    foreach ($map as $name => $list) {
        if (str_contains($clubKey, normalizeText($name))) {
            $aliases = array_merge($aliases, $list);
        }
    }
    // Manager nicknames.
    if (str_contains(normalizeText($manager), 'guardiola')) {
        $aliases[] = 'pep';
    }
    if (str_contains(normalizeText($manager), 'ferguson')) {
        $aliases[] = 'saf';
    }
    return array_values(array_unique($aliases));
}

/**
 * Build a single searchable haystack string for a tactic, including
 * player positions, roles, focuses and common club/manager aliases.
 */
function tacticHaystack(array $tactic): string
{
    $manager = (string) ($tactic['manager'] ?? '');
    $club    = (string) ($tactic['club'] ?? '');
    $parts = [
        $manager,
        $club,
        $tactic['season'] ?? '',
        $tactic['formation'] ?? '',
        $tactic['description'] ?? '',
        $tactic['philosophy'] ?? '',
        implode(' ', $tactic['style'] ?? []),
        implode(' ', tacticPlayerRoles($tactic)),
    ];
    // Add search aliases so "pep barca", "inter", "utd" etc. resolve.
    $parts = array_merge($parts, searchAliases($manager, $club));
    // Include tags and game modes so text search covers them too.
    $parts = array_merge($parts, tacticTags($tactic), tacticGameModes($tactic));
    return normalizeText(implode(' | ', $parts));
}

/**
 * Free-text search across all tactics. Every whitespace-separated token
 * must match somewhere (AND semantics). Supports formation shorthand.
 *
 * @return array<int, array<string, mixed>>
 */
function searchTactics(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return loadTactics();
    }

    $tokens = preg_split('/\s+/', normalizeText($query)) ?: [];
    $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));

    $results = [];
    foreach (loadTactics() as $tactic) {
        $haystack = tacticHaystack($tactic);
        $match = true;
        foreach ($tokens as $token) {
            $found = str_contains($haystack, $token);
            if (!$found) {
                foreach (formationSearchVariants($token) as $variant) {
                    if (str_contains($haystack, $variant)) {
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                $match = false;
                break;
            }
        }
        if ($match) {
            $results[] = $tactic;
        }
    }
    return $results;
}

/**
 * Filter tactics by an associative array of criteria.
 * Empty values are ignored; all criteria are ANDed together.
 *
 * Supported keys: manager, club, season, formation, style, version, role, position.
 *
 * @param array<string, string> $filters
 * @return array<int, array<string, mixed>>
 */
function filterTactics(array $filters): array
{
    $manager   = normalizeText(trim((string) ($filters['manager'] ?? '')));
    $club      = normalizeText(trim((string) ($filters['club'] ?? '')));
    $season    = normalizeText(trim((string) ($filters['season'] ?? '')));
    $formation = normalizeText(trim((string) ($filters['formation'] ?? '')));
    $style     = normalizeText(trim((string) ($filters['style'] ?? '')));
    $role      = normalizeText(trim((string) ($filters['role'] ?? '')));
    $position  = strtoupper(trim((string) ($filters['position'] ?? '')));
    $version   = strtolower(trim((string) ($filters['version'] ?? '')));
    $tag       = strtolower(trim((string) ($filters['tag'] ?? '')));
    $mode      = strtolower(trim((string) ($filters['mode'] ?? '')));

    return array_values(array_filter(loadTactics(), static function (array $tactic) use ($manager, $club, $season, $formation, $style, $role, $position, $version, $tag, $mode): bool {
        if ($manager !== '' && !str_contains(normalizeText($tactic['manager'] ?? ''), $manager)) {
            return false;
        }
        if ($club !== '' && !str_contains(normalizeText($tactic['club'] ?? ''), $club)) {
            return false;
        }
        if ($season !== '' && !str_contains(normalizeText($tactic['season'] ?? ''), $season)) {
            return false;
        }
        if ($tag !== '') {
            $tags = array_map('strtolower', tacticTags($tactic));
            if (!in_array($tag, $tags, true)) {
                return false;
            }
        }
        if ($mode !== '') {
            $modes = array_map('strtolower', tacticGameModes($tactic));
            if (!in_array($mode, $modes, true)) {
                return false;
            }
        }
        if ($formation !== '') {
            $formationHaystack = normalizeText($tactic['formation'] ?? '');
            $found = str_contains($formationHaystack, $formation);
            if (!$found) {
                foreach (formationSearchVariants($formation) as $variant) {
                    if (str_contains($formationHaystack, $variant)) {
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                return false;
            }
        }
        if ($style !== '') {
            $styleHaystack = normalizeText(implode(' ', array_merge($tactic['style'] ?? [], [
                $tactic['description'] ?? '',
            ])));
            if (!str_contains($styleHaystack, $style)) {
                return false;
            }
        }
        if ($version !== '' && in_array($version, ['fc25', 'fc26', 'fc27'], true)) {
            if (!isset($tactic['versions'][$version])) {
                return false;
            }
        }
        if ($role !== '' || $position !== '') {
            $roleHaystack = normalizeText(implode(' ', tacticPlayerRoles($tactic)));
            if ($role !== '' && !str_contains($roleHaystack, $role)) {
                return false;
            }
            if ($position !== '' && !str_contains(normalizeText(implode(' ', tacticPlayerRoles($tactic))), normalizeText($position))) {
                return false;
            }
        }
        return true;
    }));
}

/**
 * Return tactics that use a specific role name.
 *
 * @return array<int, array<string, mixed>>
 */
function tacticsByRole(string $role): array
{
    $needle = normalizeText($role);
    return array_values(array_filter(loadTactics(), static function (array $tactic) use ($needle): bool {
        return str_contains(normalizeText(implode(' ', tacticPlayerRoles($tactic))), $needle);
    }));
}

/**
 * Return tactics that match any of the given style tags (loose match).
 *
 * @param array<int, string> $styles
 * @return array<int, array<string, mixed>>
 */
function tacticsByStyles(array $styles, int $limit = 6): array
{
    $needles = array_map('normalizeText', $styles);
    $results = [];
    foreach (loadTactics() as $tactic) {
        $haystack = normalizeText(implode(' ', $tactic['style'] ?? []));
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                $results[] = $tactic;
                break;
            }
        }
        if ($limit > 0 && count($results) >= $limit) {
            break;
        }
    }
    return $results;
}

/**
 * Return tactics belonging to a manager.
 *
 * @return array<int, array<string, mixed>>
 */
function tacticsByManager(string $managerName): array
{
    return array_values(array_filter(loadTactics(), static function (array $tactic) use ($managerName): bool {
        return strcasecmp($tactic['manager'] ?? '', $managerName) === 0;
    }));
}

/* -------------------------------------------------------------------------
 * Derived option lists (for filter dropdowns)
 * ---------------------------------------------------------------------- */

/** @return array<int, string> */
function tacticManagers(): array
{
    return uniqueSorted(array_map(static fn ($t) => (string) ($t['manager'] ?? ''), loadTactics()));
}

/** @return array<int, string> */
function tacticClubs(): array
{
    return uniqueSorted(array_map(static fn ($t) => (string) ($t['club'] ?? ''), loadTactics()));
}

/** @return array<int, string> */
function tacticSeasons(): array
{
    return uniqueSorted(array_map(static fn ($t) => (string) ($t['season'] ?? ''), loadTactics()));
}

/** @return array<int, string> */
function tacticFormations(): array
{
    return uniqueSorted(array_map(static fn ($t) => (string) ($t['formation'] ?? ''), loadTactics()));
}

/** @return array<int, string> */
function tacticStyles(): array
{
    $styles = [];
    foreach (loadTactics() as $tactic) {
        foreach ($tactic['style'] ?? [] as $style) {
            $styles[] = (string) $style;
        }
    }
    return uniqueSorted($styles);
}

/** @param array<int, string> $values @return array<int, string> */
function uniqueSorted(array $values): array
{
    $values = array_values(array_unique(array_filter(array_map('trim', $values), static fn ($v) => $v !== '')));
    natcasesort($values);
    return array_values($values);
}

/** @return array<int, string> */
function availableVersions(): array
{
    return roleVersions() ?: ['fc25', 'fc26', 'fc27'];
}

/** Human label for a version key. */
function versionLabel(string $key): string
{
    return match (strtolower($key)) {
        'fc25' => 'FC25',
        'fc26' => 'FC26',
        'fc27' => 'FC27',
        default => strtoupper($key),
    };
}

/* -------------------------------------------------------------------------
 * Rendering utilities
 * ---------------------------------------------------------------------- */

/** Escape output safely for HTML context. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Return the slot coordinate list for a tactic, merging any stored role
 * information with the formation's pitch coordinates.
 *
 * Tactics may store a "slots" array that carries position/role details but no
 * x/y coordinates. In that case (or when coordinates are missing), the
 * coordinates are resolved from the tactic's formation so players are placed
 * correctly on the pitch rather than stacking on the centre spot.
 *
 * @return array<int, array{pos: string, x: float, y: float, role: string, instructions: string, attacking: string, defending: string}>
 */
function tacticSlots(array $tactic): array
{
    $stored = (!empty($tactic['slots']) && is_array($tactic['slots'])) ? $tactic['slots'] : [];
    $formation = getFormationById((string) ($tactic['formation'] ?? ''));
    $formationSlots = ($formation !== null && !empty($formation['slots'])) ? $formation['slots'] : [];

    // If there is no stored slot data at all, render straight from the formation.
    if (!$stored) {
        if (!$formationSlots) {
            return [];
        }
        return array_map(static fn ($s) => [
            'pos' => (string) ($s['pos'] ?? ''),
            'x'   => (float) ($s['x'] ?? 50),
            'y'   => (float) ($s['y'] ?? 50),
            'role' => '',
            'instructions' => '',
            'attacking' => '',
            'defending' => '',
        ], $formationSlots);
    }

    // Does the stored data already carry usable coordinates?
    $hasCoords = false;
    foreach ($stored as $s) {
        if (isset($s['x']) && isset($s['y'])) {
            $hasCoords = true;
            break;
        }
    }

    // Build a lookup of formation coordinates by position label.
    $coordByPos = [];
    foreach ($formationSlots as $s) {
        $coordByPos[(string) ($s['pos'] ?? '')] = $s;
    }

    // Track which formation slots have been consumed so two players never
    // share the same coordinate.
    $usedFormationKeys = [];

    $out = [];
    foreach ($stored as $i => $s) {
        $pos = (string) ($s['pos'] ?? '');

        if ($hasCoords && isset($s['x'], $s['y'])) {
            $x = (float) $s['x'];
            $y = (float) $s['y'];
        } else {
            // 1) Match by position label, if that slot is still free.
            $matchedKey = null;
            if (isset($coordByPos[$pos]) && !isset($usedFormationKeys['pos:' . $pos])) {
                $matchedKey = 'pos:' . $pos;
                $x = (float) ($coordByPos[$pos]['x'] ?? 50);
                $y = (float) ($coordByPos[$pos]['y'] ?? 50);
            } else {
                // 2) Otherwise take the next unused formation slot (by index
                //    order), preferring one whose position group matches.
                $chosen = null;
                $indexOrder = range(0, count($formationSlots) - 1);
                foreach ($indexOrder as $fi) {
                    if (isset($usedFormationKeys['idx:' . $fi])) {
                        continue;
                    }
                    $fs = $formationSlots[$fi];
                    // Prefer slots in the same band as this player.
                    if (positionGroup((string) ($fs['pos'] ?? '')) === positionGroup($pos)) {
                        $chosen = $fi;
                        break;
                    }
                }
                if ($chosen === null) {
                    foreach ($indexOrder as $fi) {
                        if (!isset($usedFormationKeys['idx:' . $fi])) {
                            $chosen = $fi;
                            break;
                        }
                    }
                }
                if ($chosen === null) {
                    $x = 50.0;
                    $y = 50.0;
                } else {
                    $matchedKey = 'idx:' . $chosen;
                    // Also mark the position label as consumed so a later player
                    // does not claim the same physical slot by position.
                    $chosenPos = (string) ($formationSlots[$chosen]['pos'] ?? '');
                    if ($chosenPos !== '') {
                        $usedFormationKeys['pos:' . $chosenPos] = true;
                    }
                    $x = (float) ($formationSlots[$chosen]['x'] ?? 50);
                    $y = (float) ($formationSlots[$chosen]['y'] ?? 50);
                }
            }
            if ($matchedKey !== null) {
                $usedFormationKeys[$matchedKey] = true;
            }
        }

        $out[] = [
            'pos'          => $pos,
            'x'            => $x,
            'y'            => $y,
            'role'         => (string) ($s['role'] ?? ''),
            'instructions' => (string) ($s['instructions'] ?? ''),
            'attacking'    => (string) ($s['attacking'] ?? ''),
            'defending'    => (string) ($s['defending'] ?? ''),
        ];
    }

    return $out;
}

/**
 * Positions with coordinates for a formation id.
 *
 * @return array<int, array{pos: string, x: float, y: float}>
 */
function formationSlots(string $formationId): array
{
    $formation = getFormationById($formationId);
    if ($formation === null) {
        return [];
    }
    return array_map(static fn ($s) => [
        'pos' => (string) ($s['pos'] ?? ''),
        'x'   => (float) ($s['x'] ?? 50),
        'y'   => (float) ($s['y'] ?? 50),
    ], $formation['slots'] ?? []);
}

/**
 * Category definitions used on recommendations.php (style-based).
 *
 * @return array<int, array<string, mixed>>
 */
function recommendationCategories(): array
{
    return [
        [
            'id' => 'counter-attack',
            'title' => 'Counter Attack',
            'icon' => '⚡',
            'blurb' => 'These setups suit players who like to soak up pressure and strike with speed. They tend to pair a deep or mid block with direct, vertical passing.',
            'styles' => ['Counter Attack', 'Direct', 'Counter Press'],
        ],
        [
            'id' => 'possession',
            'title' => 'Possession',
            'icon' => '🎯',
            'blurb' => 'For managers who want to control games through the ball. Expect high short-passing values, central overloads and patient build-up.',
            'styles' => ['Possession', 'Technical', 'Positional Play'],
        ],
        [
            'id' => 'high-press',
            'title' => 'High Press',
            'icon' => '🔥',
            'blurb' => 'Aggressive, front-foot systems that win the ball back high. Best for players with a deep squad who enjoy sustained pressure.',
            'styles' => ['High Press', 'Counter Press'],
        ],
        [
            'id' => 'low-block',
            'title' => 'Low Block',
            'icon' => '🛡️',
            'blurb' => 'Deep, compact defensive shapes that frustrate opponents and invite mistakes. Great for underdog or defensive-minded players.',
            'styles' => ['Low Block', 'Defensive', 'Compact'],
        ],
        [
            'id' => 'attacking',
            'title' => 'Attacking',
            'icon' => '⚔️',
            'blurb' => 'Expansive, chance-creating systems with plenty of forward runners and width. Ideal if you score more than you concede.',
            'styles' => ['Attacking', 'Wide Play'],
        ],
        [
            'id' => 'defensive',
            'title' => 'Defensive',
            'icon' => '🧱',
            'blurb' => 'Solid, security-first setups designed to protect leads and grind out results. Prioritises clean sheets over flair.',
            'styles' => ['Defensive', 'Low Block', 'Compact'],
        ],
        [
            'id' => 'career-mode',
            'title' => 'Career Mode',
            'icon' => '🏆',
            'blurb' => 'Balanced, sustainable tactics that work over a long season without draining stamina. Good foundations for a save.',
            'styles' => ['Balanced', 'Possession', 'Counter Attack'],
        ],
        [
            'id' => 'ultimate-team',
            'title' => 'Ultimate Team',
            'icon' => '🃏',
            'blurb' => 'Meta-friendly shapes that get the most out of elite attackers and pacey wingers. Built for competitive online play.',
            'styles' => ['High Press', 'Attacking', 'Counter Attack'],
        ],
        [
            'id' => 'clubs',
            'title' => 'Clubs',
            'icon' => '👥',
            'blurb' => 'Team-oriented tactics that rely on positional discipline and shared responsibilities — good for Pro Clubs squads.',
            'styles' => ['Possession', 'Compact', 'High Press'],
        ],
        [
            'id' => 'beginner-friendly',
            'title' => 'Beginner Friendly',
            'icon' => '🌱',
            'blurb' => 'Simple, forgiving formations with clear roles. The easiest places to start if you are new to tactical settings.',
            'styles' => ['Balanced', 'Defensive', 'Direct'],
        ],
    ];
}

/**
 * Role-based recommendation groups used on recommendations.php.
 *
 * @return array<int, array<string, mixed>>
 */
function roleRecommendationGroups(): array
{
    return [
        [
            'id' => 'counter-st',
            'title' => 'Looking for a Counter-Attacking ST?',
            'blurb' => 'Strikers who stretch defences and punish teams in transition. These are the forward roles commonly used in counter-attacking systems — not a claim that any one is objectively best.',
            'roles' => ['Advanced Forward', 'Poacher', 'Target Forward'],
        ],
        [
            'id' => 'possession-cdm',
            'title' => 'Looking for a Possession CDM?',
            'blurb' => 'Midfielders who control tempo and provide reliable passing options. Popular choices for possession-oriented teams.',
            'roles' => ['Deep-Lying Playmaker', 'Holding'],
        ],
        [
            'id' => 'attacking-fb',
            'title' => 'Looking for an Attacking Full-Back?',
            'blurb' => 'Wide defenders who provide width and join attacks. Common in modern attacking and high-press shapes.',
            'roles' => ['Wing Back', 'Inverted Wingback', 'Inverted Full Back'],
        ],
        [
            'id' => 'creative-ten',
            'title' => 'Looking for a Creative Number 10?',
            'blurb' => 'Between-the-lines creators who unlock deep defences. Suited to possession and attacking systems.',
            'roles' => ['Playmaker', 'Advanced Playmaker', 'Shadow Striker'],
        ],
        [
            'id' => 'press-cm',
            'title' => 'Looking for a High-Press Midfielder?',
            'blurb' => 'Energetic midfielders who win the ball back high and drive transitions. Ideal for high-pressing teams.',
            'roles' => ['Ball Winner', 'Box to Box', 'Mezzala'],
        ],
        [
            'id' => 'ball-playing-cb',
            'title' => 'Looking for a Build-Up Centre-Back?',
            'blurb' => 'Defenders who step out and break lines with passes. Key to possession and build-from-the-back styles.',
            'roles' => ['Ball-Playing Defender', 'Stopper'],
        ],
    ];
}

/* =========================================================================
 * Tactical Intelligence — DNA, phases, shapes, adjustments, variations
 * ====================================================================== */

/** DNA categories in display order. @return array<int, string> */
function dnaCategories(): array
{
    return ['Possession', 'Pressing', 'Directness', 'Defensive Solidity', 'Width', 'Tempo', 'Counter-Attacking', 'Creativity'];
}

/**
 * Load the full tactical intelligence file (phases, how-to-play, adjustments).
 * Kept separate from tactics.json so list pages don't pay for it.
 *
 * @return array<string, mixed>
 */
function loadTacticalIntel(): array
{
    return loadJson('tactical_intel.json');
}

/**
 * Tactical DNA values (0-100) for a tactic. Stored on the tactic record.
 *
 * @return array<string, int>
 */
function tacticDna(array $tactic): array
{
    $dna = $tactic['dna'] ?? [];
    $out = [];
    foreach (dnaCategories() as $cat) {
        $out[$cat] = isset($dna[$cat]) ? (int) $dna[$cat] : 0;
    }
    return $out;
}

/**
 * Full intelligence payload for a tactic (phases, how-to-play, adjustments).
 *
 * @return array<string, mixed>
 */
function tacticIntel(string $id): array
{
    $all = loadTacticalIntel();
    return $all['tactics'][$id] ?? [];
}

/**
 * Base / with-ball / without-ball shape labels for a tactic.
 *
 * @return array<string, string>
 */
function tacticShapes(array $tactic): array
{
    $shapes = $tactic['shapes'] ?? [];
    $base = (string) ($tactic['formation'] ?? '');
    return [
        'base'        => (string) ($shapes['base'] ?? $base),
        'withBall'    => (string) ($shapes['withBall'] ?? $base),
        'withoutBall' => (string) ($shapes['withoutBall'] ?? $base),
    ];
}

/** Game modes a tactic is designed for. @return array<int, string> */
function tacticGameModes(array $tactic): array
{
    $modes = $tactic['gameModes'] ?? [];
    return array_values(array_filter(array_map('strval', is_array($modes) ? $modes : [])));
}

/** All game modes available across the database. @return array<int, string> */
function allGameModes(): array
{
    $modes = [];
    foreach (loadTactics() as $t) {
        foreach (tacticGameModes($t) as $m) {
            $modes[$m] = true;
        }
    }
    $order = ['Career Mode', 'Ultimate Team', 'Clubs', 'Kick-Off'];
    $out = [];
    foreach ($order as $m) {
        if (isset($modes[$m])) {
            $out[] = $m;
            unset($modes[$m]);
        }
    }
    foreach (array_keys($modes) as $m) {
        $out[] = $m;
    }
    return $out;
}

/** Tags for a tactic. @return array<int, string> */
function tacticTags(array $tactic): array
{
    $tags = $tactic['tags'] ?? ($tactic['style'] ?? []);
    return array_values(array_filter(array_map('strval', is_array($tags) ? $tags : [])));
}

/** All tags across the database. @return array<int, string> */
function allTags(): array
{
    $tags = [];
    foreach (loadTactics() as $t) {
        foreach (tacticTags($t) as $tag) {
            $tags[$tag] = true;
        }
    }
    $out = array_keys($tags);
    natcasesort($out);
    return array_values($out);
}

/** Alternate shapes (variations) for a tactic. @return array<int, string> */
function tacticVariations(array $tactic): array
{
    $v = $tactic['variations'] ?? [];
    return array_values(array_filter(array_map('strval', is_array($v) ? $v : [])));
}

/**
 * Difficulty level and a short explanation of what it requires.
 *
 * @return array{level: string, why: array<int, string>, blurb: string}
 */
function tacticDifficultyInfo(array $tactic): array
{
    $level = (string) ($tactic['difficulty'] ?? 'Intermediate');
    $map = [
        'Beginner' => [
            'blurb' => 'A forgiving setup with clear, simple roles. Good if you are still learning tactical systems.',
            'why'   => ['Straightforward formation', 'Few manual tweaks required', 'Roles are easy to understand'],
        ],
        'Intermediate' => [
            'blurb' => 'A balanced setup that rewards basic tactical understanding without demanding constant micro-management.',
            'why'   => ['Some role knowledge needed', 'Transition awareness helps', 'Occasional manual adjustments useful'],
        ],
        'Advanced' => [
            'blurb' => 'A demanding setup built on layered movement and precise roles. Best once you are comfortable with the tactical tools.',
            'why'   => ['Requires understanding of Player Roles', 'Rewards manual positioning and triggers', 'Depends on defensive transitions and build-up patterns'],
        ],
    ];
    $info = $map[$level] ?? $map['Intermediate'];
    return [
        'level' => $level,
        'why'   => $info['why'],
        'blurb' => $info['blurb'],
    ];
}

/** All difficulty levels in order. @return array<int, string> */
function allDifficulties(): array
{
    return ['Beginner', 'Intermediate', 'Advanced'];
}

/**
 * Tactical phases for a tactic, with graceful fallbacks.
 *
 * @return array<string, string>
 */
function tacticPhases(array $tactic): array
{
    $intel = tacticIntel((string) ($tactic['id'] ?? ''));
    $phases = $intel['phases'] ?? [];
    $labels = [
        'buildUp'             => 'Build Up',
        'progression'         => 'Progression',
        'finalThird'          => 'Final Third',
        'defensiveTransition' => 'Defensive Transition',
        'defensiveShape'      => 'Defensive Shape',
    ];
    $out = [];
    foreach ($labels as $key => $label) {
        $text = (string) ($phases[$key] ?? '');
        if ($text === '') {
            $text = 'Details for this phase are not yet available for this recreation.';
        }
        $out[$key] = $text;
    }
    return $out;
}

/** Phase labels in order. @return array<string, string> */
function tacticPhaseLabels(): array
{
    return [
        'buildUp'             => 'Build Up',
        'progression'         => 'Progression',
        'finalThird'          => 'Final Third',
        'defensiveTransition' => 'Defensive Transition',
        'defensiveShape'      => 'Defensive Shape',
    ];
}

/**
 * Practical "how to play" guidance for a tactic.
 *
 * @return array<string, array<int, string>>
 */
function tacticHowToPlay(array $tactic): array
{
    $intel = tacticIntel((string) ($tactic['id'] ?? ''));
    return $intel['howToPlay'] ?? [];
}

/** How-to-play section labels. @return array<string, string> */
function howToPlayLabels(): array
{
    return [
        'haveBall'     => 'When You Have The Ball',
        'loseBall'     => 'When You Lose The Ball',
        'opponentBall' => 'When The Opponent Has The Ball',
        'winning'      => 'When You\'re Winning',
        'losing'       => 'When You\'re Losing',
    ];
}

/**
 * Situational tactical adjustments for a tactic.
 *
 * @return array<int, array<string, mixed>>
 */
function tacticAdjustments(array $tactic): array
{
    $intel = tacticIntel((string) ($tactic['id'] ?? ''));
    return $intel['adjustments'] ?? [];
}

/**
 * Build a manager timeline: seasons ordered chronologically, each linking to
 * whichever tactic exists for that club+season.
 *
 * @return array<int, array<string, mixed>>
 */
function managerTimeline(string $managerName): array
{
    $tactics = tacticsByManager($managerName);
    $bySeason = [];
    foreach ($tactics as $t) {
        $season = (string) ($t['season'] ?? '');
        $sortKey = seasonSortKey($season);
        $bySeason[$sortKey . '|' . ($t['id'] ?? '')] = [
            'season'  => $season,
            'club'    => (string) ($t['club'] ?? ''),
            'tactic'  => $t,
            'sortKey' => $sortKey,
        ];
    }
    uasort($bySeason, static fn ($a, $b) => $a['sortKey'] <=> $b['sortKey']);
    return array_values($bySeason);
}

/** Sortable numeric key for a season string like "2009/10". */
function seasonSortKey(string $season): float
{
    if (preg_match('/(\d{4})/', $season, $m)) {
        return (float) $m[1];
    }
    return 0.0;
}

/**
 * Managers who have tactics for a given club (used on formation pages).
 *
 * @return array<int, string>
 */
function managersForFormation(string $formationName): array
{
    $managers = [];
    foreach (filterTactics(['formation' => $formationName]) as $t) {
        $m = (string) ($t['manager'] ?? '');
        if ($m !== '') {
            $managers[$m] = true;
        }
    }
    return array_keys($managers);
}

/**
 * Common roles used by tactics with a given formation, with counts.
 * Used on the formation detail page.
 *
 * @return array<int, array{role: string, position: string, count: int}>
 */
function commonRolesForFormation(string $formationName): array
{
    $tactics = filterTactics(['formation' => $formationName]);
    $counts = [];
    foreach ($tactics as $t) {
        $seen = [];
        foreach ($t['versions'] ?? [] as $vd) {
            foreach ($vd['players'] ?? [] as $p) {
                $role = (string) ($p['role'] ?? '');
                $pos  = (string) ($p['position'] ?? '');
                if ($role === '' || isset($seen[$role . '|' . $pos])) {
                    continue;
                }
                $seen[$role . '|' . $pos] = true;
                $key = $role . '|' . $pos;
                if (!isset($counts[$key])) {
                    $counts[$key] = ['role' => $role, 'position' => $pos, 'count' => 0];
                }
                $counts[$key]['count']++;
            }
        }
    }
    usort($counts, static fn ($a, $b) => $b['count'] <=> $a['count']);
    return array_slice(array_values($counts), 0, 8);
}

/**
 * Shape transforms: given base slot coordinates, return moved coordinates for
 * "with ball" and "without ball" views.
 *
 * The with/without-ball layout is driven by the tactic's declared shape label
 * (e.g. a 4-2-3-1 that becomes 3-2-5 with the ball and 4-4-2 without it).
 * When the label matches a known formation in the database we use that
 * formation's coordinates directly, so the picture genuinely matches the
 * label. Unlisted rest-defence shapes (3-2-5, 4-2-4, 5-4-1, 4-5-1, 4-4-1-1)
 * are built procedurally from their line structure.
 *
 * This is a visual interpretation, not a simulation of the game engine.
 *
 * @param array<int, array<string, mixed>> $players
 * @param string $shape  base | with | without
 * @param array<string, string>|null $labels  optional ['withBall'=>..,'withoutBall'=>..]
 * @return array<int, array<string, mixed>>
 */
function shapedSlots(array $players, string $shape, ?array $labels = null): array
{
    // Base view: return coordinates untouched.
    if ($shape === 'base') {
        return array_map(static function (array $p): array {
            return array_merge($p, [
                'x' => round((float) ($p['x'] ?? 50), 2),
                'y' => round((float) ($p['y'] ?? 50), 2),
            ]);
        }, $players);
    }

    $players = array_values($players);
    if (!$players) {
        return $players;
    }

    // Determine the target shape label for this view.
    $label = '';
    if (is_array($labels)) {
        $label = (string) ($labels[$shape === 'with' ? 'withBall' : 'withoutBall'] ?? '');
    }

    // Resolve target coordinates.
    $target = null;
    if ($label !== '') {
        // 1) A real formation with this exact name.
        $formationId = strtolower(str_replace(' ', '-', $label));
        $formationCoords = formationSlots($formationId);
        if ($formationCoords) {
            $target = $formationCoords;
        } else {
            // 2) A rest-defence label (3-2-5 etc.) mapped to a close formation.
            $mapped = restShapeToFormation($label);
            if ($mapped !== null) {
                $target = formationSlots($mapped);
            }
            // 3) Otherwise build it procedurally.
            if (!$target) {
                $target = proceduralShape($label);
            }
        }
    }

    // If we still have no usable target, fall back to the soft nudge so the
    // view at least differs from the base.
    if (!$target) {
        return softShapeNudge($players, $shape);
    }

    return assignToShape($players, $target);
}

/**
 * Assign the tactic's players to a set of target slots, line by line, so the
 * rendered shape matches the label without players teleporting across the
 * pitch.
 *
 * Strategy: split the target into its depth lines (e.g. 3-2-5 = three lines),
 * then fill each line from back to front using the players whose natural band
 * best matches that line, keeping left/centre/right order within the line.
 *
 * @param array<int, array<string, mixed>> $players
 * @param array<int, array{pos: string, x: float, y: float}> $target
 * @return array<int, array<string, mixed>>
 */
function assignToShape(array $players, array $target): array
{
    $players = array_values($players);

    // Group target slots into lines by their y coordinate.
    $byY = [];
    foreach ($target as $s) {
        $key = (string) round((float) ($s['y'] ?? 50));
        $byY[$key][] = $s;
    }
    // Order lines from deepest (largest y) to highest (smallest y).
    uksort($byY, static fn ($a, $b) => (float) $b <=> (float) $a);
    $lines = array_values($byY);
    foreach ($lines as &$line) {
        usort($line, static fn ($a, $b) => (float) $a['x'] <=> (float) $b['x']);
    }
    unset($line);

    if (!$lines) {
        return $players;
    }

    // Identify the goalkeeper player and the goalkeeper line (deepest, single GK).
    $gkPlayerIndex = null;
    foreach ($players as $i => $p) {
        if (strtoupper((string) ($p['position'] ?? $p['pos'] ?? '')) === 'GK') {
            $gkPlayerIndex = $i;
            break;
        }
    }

    // Sort players deepest-first (by base y) so defenders fill deep lines.
    $pool = [];
    foreach ($players as $i => $p) {
        if ($i === $gkPlayerIndex) {
            continue;
        }
        $pool[] = [
            'i'    => $i,
            'pos'  => strtoupper((string) ($p['position'] ?? $p['pos'] ?? '')),
            'x'    => (float) ($p['x'] ?? 50),
            'y'    => (float) ($p['y'] ?? 50),
        ];
    }
    usort($pool, static fn ($a, $b) => $b['y'] <=> $a['y']);

    $assigned = [];

    // Handle the goalkeeper: give them to the deepest line's GK slot.
    $gkPlaced = false;
    foreach ($lines as $li => $line) {
        foreach ($line as $s) {
            if (strtoupper((string) ($s['pos'] ?? '')) === 'GK' && $gkPlayerIndex !== null) {
                $assigned[$gkPlayerIndex] = $s;
                // Remove that slot from the line so it is not reused.
                $lines[$li] = array_values(array_filter($line, static fn ($x) => strtoupper((string) ($x['pos'] ?? '')) !== 'GK'));
                $gkPlaced = true;
                break 2;
            }
        }
    }
    if (!$gkPlaced && $gkPlayerIndex !== null) {
        $assigned[$gkPlayerIndex] = ['x' => 50.0, 'y' => 93.0];
    }

    // Fill the remaining lines, deepest line first.
    $remaining = $pool;
    foreach ($lines as $line) {
        $line = array_values($line);
        if (!$line) {
            continue;
        }
        $need = count($line);
        if (!$remaining) {
            break;
        }

        // Choose the $need players whose band best fits this line's band, with
        // a preference for the deepest available players.
        $lineBand = bandOfSlot($line[0]);
        usort($remaining, static function ($a, $b) use ($lineBand) {
            $ab = bandDistance(positionGroup($a['pos']), $lineBand);
            $bb = bandDistance(positionGroup($b['pos']), $lineBand);
            if ($ab !== $bb) {
                return $ab <=> $bb;
            }
            return $b['y'] <=> $a['y']; // deeper first
        });

        $chosen = array_splice($remaining, 0, $need);

        // Order chosen players left-to-right and map to the line slots.
        usort($chosen, static fn ($a, $b) => $a['x'] <=> $b['x']);
        foreach ($line as $k => $slot) {
            if (!isset($chosen[$k])) {
                break;
            }
            $assigned[$chosen[$k]['i']] = $slot;
        }
    }

    // Any unplaced players keep their base coordinates.
    $out = [];
    foreach ($players as $i => $p) {
        $coord = $assigned[$i] ?? ['x' => (float) ($p['x'] ?? 50), 'y' => (float) ($p['y'] ?? 50)];
        $out[] = array_merge($p, [
            'x' => round((float) $coord['x'], 2),
            'y' => round((float) $coord['y'], 2),
        ]);
    }
    return $out;
}

/** Distance between a player's position group and a target line band (0=def). */
function bandDistance(string $group, int $lineBand): int
{
    $playerBand = ['gk' => 0, 'def' => 0, 'mid' => 1, 'att' => 2][$group] ?? 1;
    return abs($playerBand - $lineBand);
}

/** Band index for a target slot based on its depth and label. */
function bandOfSlot(array $slot): int
{
    $pos = strtoupper((string) ($slot['pos'] ?? ''));
    $g = positionGroup($pos);
    if (in_array($g, ['def', 'gk'], true)) {
        return 0;
    }
    if ($g === 'mid') {
        return 1;
    }
    // Generic procedural labels ("DEF"/"MID"/"ATT") and unknown -> use depth.
    if ($pos === 'DEF') {
        return 0;
    }
    if ($pos === 'MID') {
        return 1;
    }
    if ($pos === 'ATT' || $pos === 'ST') {
        return 2;
    }
    $y = (float) ($slot['y'] ?? 50);
    if ($y >= 68) {
        return 0;
    }
    if ($y >= 40) {
        return 1;
    }
    return 2;
}

/**
 * Map a rest-defence / in-possession shape label (which may describe a modern
 * structure rather than a classic formation) to the closest formation in the
 * database, so the picture can use real, well-proportioned coordinates.
 *
 * Returns null when there is no sensible match (the caller then builds the
 * shape procedurally).
 */
function restShapeToFormation(string $label): ?string
{
    // Only map labels that ARE effectively a classic formation. Modern
    // rest-defence shapes (3-2-5, 4-2-4, 5-4-1, 4-5-1) are built procedurally
    // in proceduralShape() so their line counts are honoured exactly.
    $map = [
        '4-4-2'   => '4-4-2',
        '4-4-1-1' => '4-2-3-1',
        '4-1-4-1' => '4-1-4-1',
        '4-3-3'   => '4-3-3',
        '4-2-3-1' => '4-2-3-1',
        '3-5-2'   => '3-5-2',
        '5-3-2'   => '5-3-2',
    ];

    $key = trim($label);
    if (isset($map[$key]) && getFormationById($map[$key]) !== null) {
        return $map[$key];
    }
    return null;
}

/**
 * Build coordinates for a rest-defence shape label that is not a listed
 * formation, e.g. "3-2-5", "4-2-4", "5-4-1", "4-5-1", "4-4-1-1".
 *
 * @return array<int, array{pos: string, x: float, y: float}>
 */
function proceduralShape(string $label): array
{
    $parts = array_map('intval', explode('-', $label));
    $parts = array_values(array_filter($parts, static fn ($n) => $n > 0));
    if (!$parts || array_sum($parts) < 8 || array_sum($parts) > 10) {
        return [];
    }

    // Line y positions from deepest to highest (GK omitted from label).
    // Distribute lines evenly between y=88 (deep) and y=12 (high).
    $lineCount = count($parts);
    $yStart = 82.0;
    $yEnd = 16.0;
    $step = $lineCount > 1 ? ($yStart - $yEnd) / ($lineCount - 1) : 0;

    // Determine which line is the defensive line and which is the attacking
    // line, based on the first and last counts (standard convention).
    $slots = [];
    $slots[] = ['pos' => 'GK', 'x' => 50.0, 'y' => 93.0];

    foreach ($parts as $li => $count) {
        $y = $yStart - ($step * $li);
        // Spread $count players across the width. Keep the middle anchored.
        $xs = spreadXs($count);
        $isLast = ($li === $lineCount - 1);
        $isFirst = ($li === 0);
        foreach ($xs as $x) {
            if ($isLast && $count === 1) {
                $pos = 'ST';
            } elseif ($isFirst) {
                $pos = $count <= 3 ? 'CB' : 'DEF';
            } elseif ($isLast) {
                $pos = 'ATT';
            } else {
                $pos = 'MID';
            }
            $slots[] = ['pos' => $pos, 'x' => round($x, 1), 'y' => round($y, 1)];
        }
    }
    return $slots;
}

/**
 * Spread N players evenly across the pitch width (x 10..90), keeping centre.
 *
 * @return array<int, float>
 */
function spreadXs(int $count): array
{
    if ($count <= 1) {
        return [50.0];
    }
    $margin = $count >= 5 ? 9.0 : ($count >= 4 ? 12.0 : 18.0);
    $span = 100.0 - ($margin * 2);
    $xs = [];
    for ($i = 0; $i < $count; $i++) {
        $xs[] = $margin + ($span * ($i / ($count - 1)));
    }
    return $xs;
}

/**
 * Gentle fallback nudge used only when no shape label can be resolved.
 *
 * @param array<int, array<string, mixed>> $players
 * @return array<int, array<string, mixed>>
 */
function softShapeNudge(array $players, string $shape): array
{
    return array_map(static function (array $p) use ($shape): array {
        $pos = strtoupper((string) ($p['position'] ?? $p['pos'] ?? ''));
        $x = (float) ($p['x'] ?? 50);
        $y = (float) ($p['y'] ?? 50);
        if ($shape === 'with') {
            if (preg_match('/^(LW|LM|RW|RM|LWB|RWB)$/', $pos)) {
                $x = $x < 50 ? max(5, $x - 4) : min(95, $x + 4);
                $y = max(6, $y - 5);
            } elseif (preg_match('/^(LB|RB)$/', $pos)) {
                $y = max(20, $y - 8);
            } elseif (preg_match('/^(ST|LST|RST)$/', $pos)) {
                $y = max(7, $y - 4);
            }
        } else {
            $x = 50 + ($x - 50) * 0.8;
            if (preg_match('/^(ST|LST|RST|LW|RW|CAM|LAM|RAM)$/', $pos)) {
                $y = min(88, $y + 8);
            }
        }
        return array_merge($p, ['x' => round($x, 2), 'y' => round($y, 2)]);
    }, $players);
}

/**
 * Compute a rule-based "TactiFC Tactical Fit" between a player profile and a
 * role. Deliberately transparent: it scores a player's stats and preferred
 * position against the role's family. This is NOT an official EA rating.
 *
 * @param array<string, mixed> $player
 * @return array{score: int, label: string, reasons: array<int, string>}
 */
function tacticFitScore(array $player, string $position, string $role): array
{
    $stats = [
        'pace'      => (int) ($player['pace'] ?? 70),
        'shooting'  => (int) ($player['shooting'] ?? 70),
        'passing'   => (int) ($player['passing'] ?? 70),
        'dribbling' => (int) ($player['dribbling'] ?? 70),
        'defending' => (int) ($player['defending'] ?? 70),
        'physical'  => (int) ($player['physical'] ?? 70),
        'overall'   => (int) ($player['overall'] ?? 70),
    ];

    // Weights per role family (sums to 1.0 per entry where used).
    $role = strtolower($role);
    $weights = ['pace' => .1, 'shooting' => .1, 'passing' => .15, 'dribbling' => .15, 'defending' => .2, 'physical' => .1, 'overall' => .2];
    $reasons = [];

    if (str_contains($role, 'goalkeeper') || str_contains($role, 'keeper')) {
        $weights = ['pace' => .05, 'shooting' => .02, 'passing' => .13, 'dribbling' => .05, 'defending' => .4, 'physical' => .15, 'overall' => .2];
        $reasons[] = 'Goalkeeper role weighted heavily toward defensive and physical attributes.';
    } elseif (str_contains($role, 'poacher') || str_contains($role, 'advanced forward') || str_contains($role, 'complete forward')) {
        $weights = ['pace' => .2, 'shooting' => .3, 'passing' => .08, 'dribbling' => .17, 'defending' => .02, 'physical' => .08, 'overall' => .15];
        $reasons[] = 'Forward role weighted toward pace, shooting and dribbling.';
    } elseif (str_contains($role, 'target') || str_contains($role, 'false nine')) {
        $weights = ['pace' => .1, 'shooting' => .2, 'passing' => .18, 'dribbling' => .14, 'defending' => .03, 'physical' => .2, 'overall' => .15];
        $reasons[] = 'Hold-up role values physicality, passing and shooting.';
    } elseif (str_contains($role, 'playmaker') || str_contains($role, 'mezzala') || str_contains($role, 'box to box') || str_contains($role, 'box crasher')) {
        $weights = ['pace' => .1, 'shooting' => .1, 'passing' => .28, 'dribbling' => .22, 'defending' => .1, 'physical' => .07, 'overall' => .13];
        $reasons[] = 'Creative midfield role weighted toward passing and dribbling.';
    } elseif (str_contains($role, 'holding') || str_contains($role, 'ball winner') || str_contains($role, 'anchor')) {
        $weights = ['pace' => .07, 'shooting' => .04, 'passing' => .18, 'dribbling' => .1, 'defending' => .32, 'physical' => .17, 'overall' => .12];
        $reasons[] = 'Defensive midfield role weighted toward defending and physicality.';
    } elseif (str_contains($role, 'wing') || str_contains($role, 'winger') || str_contains($role, 'inside forward') || str_contains($role, 'wide playmaker')) {
        $weights = ['pace' => .26, 'shooting' => .13, 'passing' => .16, 'dribbling' => .25, 'defending' => .05, 'physical' => .05, 'overall' => .1];
        $reasons[] = 'Wide role weighted toward pace, dribbling and passing.';
    } elseif (str_contains($role, 'defender') || str_contains($role, 'stopper') || str_contains($role, 'back')) {
        $weights = ['pace' => .12, 'shooting' => .02, 'passing' => .13, 'dribbling' => .08, 'defending' => .35, 'physical' => .2, 'overall' => .1];
        $reasons[] = 'Defensive role weighted toward defending and physicality.';
    } else {
        $reasons[] = 'Balanced weighting applied for this role.';
    }

    $weightTotal = array_sum($weights);
    $score = 0.0;
    foreach ($weights as $stat => $w) {
        $score += ($stats[$stat] / 100) * ($w / $weightTotal) * 100;
    }
    $score = (int) round($score);

    // Position match bonus: preferred position vs the slot's base position.
    $preferred = strtoupper((string) ($player['preferredPosition'] ?? ''));
    $base = basePositionAlias(strtoupper($position));
    if ($preferred !== '') {
        if ($preferred === $base) {
            $score = min(100, $score + 10);
            $reasons[] = 'Player prefers this exact position (+10).';
        } elseif (positionGroup($preferred) === positionGroup($base)) {
            $score = min(100, $score + 4);
            $reasons[] = 'Player prefers a related position (+4).';
        } else {
            $score = max(0, $score - 6);
            $reasons[] = 'Player prefers a different position (-6).';
        }
    }

    $label = $score >= 85 ? 'Excellent' : ($score >= 72 ? 'Good' : ($score >= 58 ? 'Fair' : 'Poor'));
    return ['score' => $score, 'label' => $label, 'reasons' => $reasons];
}

/** Map an alias slot label to its base position. */
function basePositionAlias(string $pos): string
{
    $aliases = [
        'RWB' => 'RB', 'LWB' => 'LB', 'LCB' => 'CB', 'RCB' => 'CB',
        'RDM' => 'CDM', 'LDM' => 'CDM', 'LST' => 'ST', 'RST' => 'ST',
        'LAM' => 'CAM', 'RAM' => 'CAM', 'LCM' => 'CM', 'RCM' => 'CM',
    ];
    return $aliases[strtoupper($pos)] ?? strtoupper($pos);
}

/** Broad position group for compatibility checks. */
function positionGroup(string $pos): string
{
    $pos = basePositionAlias($pos);
    if ($pos === 'GK') {
        return 'gk';
    }
    if (in_array($pos, ['CB', 'LB', 'RB'], true)) {
        return 'def';
    }
    if (in_array($pos, ['CDM', 'CM', 'CAM', 'LM', 'RM'], true)) {
        return 'mid';
    }
    return 'att';
}

/**
 * Suggested player-fit table for a tactic. Given a squad (list of player
 * profiles), suggest a role per player and score the fit.
 *
 * @param array<int, array<string, mixed>> $squad
 * @param array<string, mixed>             $tactic
 * @return array<int, array<string, mixed>>
 */
function playerFitForTactic(array $squad, array $tactic): array
{
    $version = array_key_first($tactic['versions'] ?? []) ?: 'fc25';
    $players = $tactic['versions'][$version]['players'] ?? [];
    $results = [];

    foreach ($squad as $player) {
        $pref = strtoupper((string) ($player['preferredPosition'] ?? ''));
        // Find the tactic slot that best matches the player's preferred position.
        $best = null;
        $bestGroupMatch = -1;
        foreach ($players as $slot) {
            $slotPos = (string) ($slot['position'] ?? '');
            $base = basePositionAlias($slotPos);
            $match = 0;
            if ($pref !== '' && $base === $pref) {
                $match = 3;
            } elseif ($pref !== '' && positionGroup($pref) === positionGroup($slotPos)) {
                $match = 2;
            }
            if ($match > $bestGroupMatch) {
                $bestGroupMatch = $match;
                $best = $slot;
            }
        }
        if ($best === null) {
            continue;
        }
        $fit = tacticFitScore($player, (string) $best['position'], (string) $best['role']);
        $results[] = [
            'player'   => (string) ($player['name'] ?? 'Player'),
            'position' => (string) $best['position'],
            'role'     => (string) $best['role'],
            'focus'    => (string) ($best['focus'] ?? ''),
            'fit'      => $fit['label'],
            'score'    => $fit['score'],
            'reasons'  => $fit['reasons'],
        ];
    }
    return $results;
}

/**
 * Rule-based tactic generator. Produces a tactical setup using the existing
 * database and role data. No external API.
 *
 * @param array<string, string> $input style, formation, strength, inspiration
 * @return array<string, mixed>
 */
function generateTactic(array $input): array
{
    $style       = trim((string) ($input['style'] ?? 'Balanced'));
    $formation   = trim((string) ($input['formation'] ?? '4-3-3'));
    $strength    = trim((string) ($input['strength'] ?? 'Balanced Squad'));
    $inspiration = trim((string) ($input['inspiration'] ?? ''));
    $version     = strtolower(trim((string) ($input['version'] ?? 'fc26')));

    // Style → tactical settings.
    $styleMap = [
        'Possession'     => ['buildUp' => 'Short Passing', 'defensiveApproach' => 'High Press', 'width' => 7, 'depth' => 8, 'attackingFocus' => 'Possession', 'stance' => 'Possession', 'blurb' => 'Control games through the ball with patient, structured build-up.'],
        'Counter Attack' => ['buildUp' => 'Long Ball', 'defensiveApproach' => 'Drop Back', 'width' => 4, 'depth' => 3, 'attackingFocus' => 'Counter Attack', 'stance' => 'Counter Attack', 'blurb' => 'Absorb pressure in a compact block and strike with speed in transition.'],
        'High Press'     => ['buildUp' => 'Fast Build Up', 'defensiveApproach' => 'Constant Press', 'width' => 6, 'depth' => 9, 'attackingFocus' => 'Fast Build Up', 'stance' => 'High Press', 'blurb' => 'Win the ball back high and attack before the opponent can set.'],
        'Low Block'      => ['buildUp' => 'Long Ball', 'defensiveApproach' => 'Low Block', 'width' => 3, 'depth' => 2, 'attackingFocus' => 'Counter', 'stance' => 'Low Block', 'blurb' => 'Defend deep and compact, then break fast through the front players.'],
        'Direct'         => ['buildUp' => 'Long Ball', 'defensiveApproach' => 'Mid Block', 'width' => 6, 'depth' => 6, 'attackingFocus' => 'Direct Passing', 'stance' => 'Direct', 'blurb' => 'Move the ball forward quickly and attack with purpose.'],
        'Balanced'       => ['buildUp' => 'Balanced', 'defensiveApproach' => 'Balanced', 'width' => 5, 'depth' => 5, 'attackingFocus' => 'Balanced', 'stance' => 'Balanced', 'blurb' => 'A balanced setup that adapts to the flow of the match.'],
    ];
    $settings = $styleMap[$style] ?? $styleMap['Balanced'];

    // Strength → role emphasis per position group.
    $strengthMap = [
        'Fast Wingers'        => ['RW' => 'Winger', 'LW' => 'Winger', 'RM' => 'Winger', 'LM' => 'Winger'],
        'Strong Striker'      => ['ST' => 'Target Forward', 'LST' => 'Target Forward', 'RST' => 'Target Forward'],
        'Creative CAM'        => ['CAM' => 'Playmaker', 'LAM' => 'Advanced Playmaker', 'RAM' => 'Advanced Playmaker'],
        'Defensive Midfield'  => ['CDM' => 'Holding', 'LDM' => 'Holding', 'RDM' => 'Holding'],
        'Attacking Fullbacks' => ['RB' => 'Wing Back', 'LB' => 'Wing Back', 'RWB' => 'Wing Back', 'LWB' => 'Wing Back'],
        'Strong Centre Backs' => ['CB' => 'Stopper', 'LCB' => 'Stopper', 'RCB' => 'Stopper'],
    ];
    $emphasis = $strengthMap[$strength] ?? [];

    // Formation slots.
    $slots = formationSlots($formation);
    if (!$slots) {
        $slots = formationSlots('4-3-3');
        $formation = '4-3-3';
    }

    $players = [];
    $occurrences = [];
    foreach ($slots as $i => $slot) {
        $pos = (string) $slot['pos'];
        $base = basePositionAlias($pos);
        $roles = getRolesForPosition($base, $version);
        $available = array_map(static fn ($e) => (string) $e['role'], $roles);
        if (!$available) {
            continue;
        }

        // 1) Strength emphasis override 2) style preference 3) first available.
        $chosen = null;
        if (isset($emphasis[$pos]) && in_array($emphasis[$pos], $available, true)) {
            $chosen = $emphasis[$pos];
        }
        if ($chosen === null) {
            $prefs = rolePreferenceForStyle($base, $style);
            foreach ($prefs as $candidate) {
                if (in_array($candidate, $available, true)) {
                    $chosen = $candidate;
                    break;
                }
            }
        }
        if ($chosen === null) {
            $occ = $occurrences[$base] ?? 0;
            $chosen = $available[$occ % count($available)];
        }
        $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;

        $focuses = getFocusesForRole($base, $chosen, $version);
        $players[] = [
            'position'    => $pos,
            'role'        => $chosen,
            'focus'       => $focuses[0] ?? '',
            'familiarity' => $i < 4 ? 'Role++' : ($i < 8 ? 'Role+' : 'Role'),
            'x'           => (float) $slot['x'],
            'y'           => (float) $slot['y'],
        ];
    }

    // Optional manager inspiration: blend in that manager's preferred roles.
    if ($inspiration !== '') {
        $source = null;
        foreach (tacticsByManager($inspiration) as $t) {
            if (($t['formation'] ?? '') === $formation) {
                $source = $t;
                break;
            }
        }
        if ($source === null) {
            $managerTactics = tacticsByManager($inspiration);
            $source = $managerTactics[0] ?? null;
        }
        if ($source !== null) {
            $srcPlayers = $source['versions'][array_key_first($source['versions'])]['players'] ?? [];
            foreach ($players as $i => &$p) {
                foreach ($srcPlayers as $sp) {
                    if (basePositionAlias((string) ($sp['position'] ?? '')) === basePositionAlias((string) $p['position'])) {
                        $valid = array_map(static fn ($e) => (string) $e['role'], getRolesForPosition(basePositionAlias($p['position']), $version));
                        if (in_array((string) $sp['role'], $valid, true)) {
                            $p['role'] = (string) $sp['role'];
                            $focuses = getFocusesForRole(basePositionAlias($p['position']), $p['role'], $version);
                            $p['focus'] = $focuses[0] ?? $p['focus'];
                        }
                        break;
                    }
                }
            }
            unset($p);
        }
    }

    return [
        'style'      => $style,
        'formation'  => $formation,
        'strength'   => $strength,
        'inspiration'=> $inspiration,
        'version'    => $version,
        'settings'   => $settings,
        'players'    => $players,
        'shapeWith'  => guessShapeLabel($players, 'with'),
        'shapeWithout' => guessShapeLabel($players, 'without'),
    ];
}

/**
 * Style-driven role preference order for a base position.
 *
 * @return array<int, string>
 */
function rolePreferenceForStyle(string $base, string $style): array
{
    $common = [
        'GK'  => ['Sweeper Keeper', 'Ball-Playing Keeper', 'Goalkeeper'],
        'CB'  => ['Ball-Playing Defender', 'Defender', 'Stopper'],
        'RB'  => ['Wing Back', 'Inverted Wingback', 'Full Back', 'Inverted Full Back'],
        'LB'  => ['Wing Back', 'Inverted Wingback', 'Full Back', 'Inverted Full Back'],
        'CDM' => ['Deep-Lying Playmaker', 'Holding', 'Ball Winner'],
        'CM'  => ['Box to Box', 'Mezzala', 'Playmaker'],
        'CAM' => ['Playmaker', 'Advanced Playmaker', 'Shadow Striker'],
        'RM'  => ['Winger', 'Wide Playmaker', 'Inside Forward'],
        'LM'  => ['Winger', 'Wide Playmaker', 'Inside Forward'],
        'RW'  => ['Winger', 'Inside Forward'],
        'LW'  => ['Winger', 'Inside Forward'],
        'ST'  => ['Advanced Forward', 'Complete Forward', 'Poacher', 'Target Forward'],
    ];
    $byStyle = [
        'Possession'     => ['GK' => ['Sweeper Keeper', 'Ball-Playing Keeper'], 'CB' => ['Ball-Playing Defender'], 'CDM' => ['Deep-Lying Playmaker'], 'CM' => ['Mezzala', 'Playmaker'], 'ST' => ['False Nine', 'Complete Forward']],
        'Counter Attack' => ['GK' => ['Goalkeeper'], 'CB' => ['Defender', 'Stopper'], 'CDM' => ['Holding'], 'ST' => ['Poacher', 'Advanced Forward']],
        'High Press'     => ['GK' => ['Sweeper Keeper'], 'CB' => ['Stopper'], 'CDM' => ['Ball Winner'], 'CM' => ['Box to Box'], 'ST' => ['Advanced Forward']],
        'Low Block'      => ['GK' => ['Goalkeeper'], 'CB' => ['Defender', 'Stopper'], 'CDM' => ['Holding'], 'ST' => ['Target Forward']],
        'Direct'         => ['GK' => ['Goalkeeper'], 'CM' => ['Box to Box'], 'ST' => ['Target Forward', 'Advanced Forward']],
        'Balanced'       => [],
    ];
    $specific = $byStyle[$style][$base] ?? [];
    return array_values(array_unique(array_merge($specific, $common[$base] ?? [])));
}

/** Guess a shape label from generated players (very rough, transparent). */
function guessShapeLabel(array $players, string $shape): string
{
    $def = 0; $mid = 0; $att = 0;
    foreach ($players as $p) {
        $g = positionGroup((string) $p['position']);
        if ($g === 'gk') continue;
        if ($g === 'def') $def++;
        elseif ($g === 'mid') $mid++;
        else $att++;
    }
    if ($shape === 'without') {
        return ($def + 1) . '-' . max(3, $mid) . '-' . max(1, $att);
    }
    return max(3, $def) . '-' . max(2, $mid) . '-' . max(2, $att);
}

/**
 * Explain which of a tactic's attributes match a set of user preferences.
 *
 * @param array<string, mixed>  $tactic
 * @param array<string, string> $prefs playstyle, formation, strength, defensive, gameMode, version
 * @return array<int, string>
 */
function recommendationReasons(array $tactic, array $prefs): array
{
    $reasons = [];
    $styles = array_map('strtolower', $tactic['style'] ?? []);
    $tags   = array_map('strtolower', tacticTags($tactic));

    if (!empty($prefs['playstyle'])) {
        $want = strtolower($prefs['playstyle']);
        if (in_array($want, $styles, true) || in_array($want, $tags, true)) {
            $reasons[] = 'Matches your preferred style: ' . $prefs['playstyle'];
        } elseif (stripos(implode(' ', $styles), $want) !== false) {
            $reasons[] = 'Related to your preferred style: ' . $prefs['playstyle'];
        }
    }
    if (!empty($prefs['formation']) && strcasecmp((string) ($tactic['formation'] ?? ''), $prefs['formation']) === 0) {
        $reasons[] = 'Uses your preferred formation: ' . $prefs['formation'];
    }
    if (!empty($prefs['defensive'])) {
        $want = strtolower($prefs['defensive']);
        $hay = strtolower(implode(' ', array_merge($styles, $tags, [$tactic['description'] ?? ''])));
        if (str_contains($hay, $want)) {
            $reasons[] = ucfirst($prefs['defensive']) . ' defensive structure';
        }
    }
    if (!empty($prefs['strength'])) {
        $want = strtolower($prefs['strength']);
        if (in_array($want, $tags, true)) {
            $reasons[] = 'Suits your strength: ' . $prefs['strength'];
        }
    }
    if (!empty($prefs['gameMode'])) {
        if (in_array($prefs['gameMode'], tacticGameModes($tactic), true)) {
            $reasons[] = 'Designed for ' . $prefs['gameMode'];
        }
    }
    if (!empty($prefs['version']) && isset($tactic['versions'][strtolower($prefs['version'])])) {
        $reasons[] = 'Available in ' . versionLabel($prefs['version']);
    }

    // Always add one or two descriptive reasons from the tactic itself.
    foreach (array_slice(tacticTags($tactic), 0, 2) as $tag) {
        $reasons[] = 'Tagged: ' . $tag;
    }
    return array_values(array_unique($reasons));
}

/**
 * Score a tactic against user preferences for the recommendation engine.
 *
 * @param array<string, mixed>  $tactic
 * @param array<string, string> $prefs
 */
function recommendationScore(array $tactic, array $prefs): int
{
    $score = 0;
    $styles = array_map('strtolower', $tactic['style'] ?? []);
    $tags   = array_map('strtolower', tacticTags($tactic));

    if (!empty($prefs['playstyle'])) {
        $want = strtolower($prefs['playstyle']);
        if (in_array($want, $styles, true)) $score += 40;
        elseif (in_array($want, $tags, true)) $score += 25;
        elseif (stripos(implode(' ', $styles), $want) !== false) $score += 15;
    }
    if (!empty($prefs['formation']) && strcasecmp((string) ($tactic['formation'] ?? ''), $prefs['formation']) === 0) {
        $score += 30;
    }
    if (!empty($prefs['defensive'])) {
        $want = strtolower($prefs['defensive']);
        $hay = strtolower(implode(' ', array_merge($styles, $tags, [$tactic['description'] ?? ''])));
        if (str_contains($hay, $want)) $score += 15;
    }
    if (!empty($prefs['strength'])) {
        $want = strtolower($prefs['strength']);
        if (in_array($want, $tags, true)) $score += 20;
    }
    if (!empty($prefs['gameMode']) && in_array($prefs['gameMode'], tacticGameModes($tactic), true)) {
        $score += 10;
    }
    if (!empty($prefs['version']) && isset($tactic['versions'][strtolower($prefs['version'])])) {
        $score += 5;
    }
    return $score;
}

/**
 * Rank tactics by preference score.
 *
 * @param array<string, string> $prefs
 * @return array<int, array{tactic: array<string, mixed>, score: int, reasons: array<int, string>}>
 */
function recommendTactics(array $prefs, int $limit = 6): array
{
    $scored = [];
    foreach (loadTactics() as $t) {
        $score = recommendationScore($t, $prefs);
        if ($score <= 0) {
            continue;
        }
        $scored[] = [
            'tactic'  => $t,
            'score'   => $score,
            'reasons' => recommendationReasons($t, $prefs),
        ];
    }
    usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($scored, 0, $limit);
}

/**
 * Broad tactical characteristics for a manager, derived from the styles that
 * appear across their tactics. These are general descriptions, not absolute
 * statements about every team they coached.
 *
 * @return array<int, string>
 */
function managerCharacteristics(array $manager): array
{
    $name = (string) ($manager['name'] ?? '');
    $tactics = tacticsByManager($name);

    $styleCounts = [];
    foreach ($tactics as $t) {
        foreach (($t['style'] ?? []) as $style) {
            $style = (string) $style;
            $styleCounts[$style] = ($styleCounts[$style] ?? 0) + 1;
        }
        foreach (tacticTags($t) as $tag) {
            $styleCounts[$tag] = ($styleCounts[$tag] ?? 0) + 1;
        }
    }
    arsort($styleCounts);

    $phrases = [
        'Counter Attack'  => 'Transition-focused football with fast breaks',
        'Low Block'       => 'Deep, compact defensive structures',
        'Compact'         => 'Compact defensive organisation',
        'Defensive'       => 'Defensive solidity as a foundation',
        'Possession'      => 'Possession-dominant build-up',
        'High Press'      => 'Aggressive high pressing',
        'Counter Press'   => 'Immediate counter-pressing',
        'Attacking'       => 'Front-foot, attacking intent',
        'Direct'          => 'Direct, vertical progression',
        'Wide'            => 'Attacking width from wide players',
        'Wide Play'       => 'Attacking width from wide players',
        'Fast Wingers'    => 'Reliance on fast wide attackers',
        'Strong Striker'  => 'A focal-point striker',
        'Creative CAM'    => 'A creative number ten',
        'Attacking Fullbacks' => 'Attacking full-backs providing width',
        'Build Up'        => 'Structured build-up play',
        'Balanced'        => 'Flexible, balanced structures',
        'Technical'       => 'Technical, combination football',
        'Positional Play' => 'Positional-play principles',
    ];

    $out = [];
    foreach (array_keys($styleCounts) as $style) {
        if (isset($phrases[$style])) {
            $out[$phrases[$style]] = true;
        }
        if (count($out) >= 5) {
            break;
        }
    }
    // Fall back to the manager's curated styles if we derived too few.
    if (count($out) < 3) {
        foreach (($manager['styles'] ?? []) as $style) {
            $out[ucfirst((string) $style) . ' approach'] = true;
        }
    }
    return array_slice(array_keys($out), 0, 5);
}

/**
 * Formations a manager's tactics use, in frequency order.
 *
 * @return array<int, string>
 */
function managerFormations(array $manager): array
{
    $counts = [];
    foreach (tacticsByManager((string) ($manager['name'] ?? '')) as $t) {
        $f = (string) ($t['formation'] ?? '');
        if ($f !== '') {
            $counts[$f] = ($counts[$f] ?? 0) + 1;
        }
    }
    arsort($counts);
    return array_keys($counts);
}

/**
 * Aggregate Tactical DNA across a manager's tactics (average per category).
 *
 * @return array<string, int>
 */
function managerAverageDna(array $manager): array
{
    $tactics = tacticsByManager((string) ($manager['name'] ?? ''));
    $totals = array_fill_keys(dnaCategories(), 0);
    $count = 0;
    foreach ($tactics as $t) {
        $dna = tacticDna($t);
        foreach ($dna as $cat => $val) {
            $totals[$cat] += $val;
        }
        $count++;
    }
    if ($count === 0) {
        return array_fill_keys(dnaCategories(), 0);
    }
    $out = [];
    foreach ($totals as $cat => $sum) {
        $out[$cat] = (int) round($sum / $count);
    }
    return $out;
}

/**
 * Get a manager by their id (fall back to name lookup for compatibility).
 *
 * @return array<string, mixed>|null
 */
function getManagerById(string $id): ?array
{
    foreach (loadManagers() as $manager) {
        if (($manager['id'] ?? '') === $id) {
            return $manager;
        }
    }
    // Fall back: treat the id as a slugified name.
    foreach (loadManagers() as $manager) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) ($manager['name'] ?? '')));
        if ($slug === strtolower($id)) {
            return $manager;
        }
    }
    return null;
}
