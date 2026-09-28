<?php
/**
 * TactiFC — JSON search index endpoint.
 *
 * Returns a compact array used by assets/js/search.js for live suggestions.
 * Each item: { type, title, meta, url, haystack }
 *
 * Grouped by type so the front-end can show "Managers", "Clubs", "Tactics"
 * style sections in the autocomplete dropdown.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/data.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$only = isset($_GET['type']) ? trim((string) $_GET['type']) : '';

function haystack(array $parts): string
{
    return normalizeText(implode(' ', array_filter(array_map(static fn ($p) => (string) $p, $parts), static fn ($p) => $p !== '')));
}

$index = [];

/* Tactics (also include tags, game modes and role/focus terms). */
if ($only === '' || $only === 'tactics') {
    foreach (loadTactics() as $t) {
        $id = (string) ($t['id'] ?? '');
        $title = trim(($t['manager'] ?? '') . ' — ' . ($t['club'] ?? '') . ' ' . ($t['season'] ?? ''));
        $meta = ($t['formation'] ?? '') . ' · ' . implode(', ', $t['style'] ?? []);
        $index[] = [
            'type'     => 'tactic',
            'title'    => $title,
            'meta'     => $meta,
            'url'      => 'tactic.php?id=' . rawurlencode($id),
            'haystack' => haystack([
                $t['manager'] ?? '', $t['club'] ?? '', $t['season'] ?? '', $t['formation'] ?? '',
                implode(' ', $t['style'] ?? []), implode(' ', tacticPlayerRoles($t)),
                implode(' ', tacticTags($t)), implode(' ', tacticGameModes($t)),
            ]),
        ];
    }
}

/* Clubs (with their leagues). */
if ($only === '' || $only === 'clubs') {
    foreach (loadClubs() as $c) {
        $name = (string) ($c['name'] ?? '');
        $index[] = [
            'type'     => 'club',
            'title'    => $name,
            'meta'     => 'Club · ' . (string) ($c['league'] ?? ''),
            'url'      => 'search.php?q=' . rawurlencode($name),
            'haystack' => haystack([$name, $c['league'] ?? '', $c['country'] ?? '']),
        ];
    }
}

/* Managers. */
if ($only === '' || $only === 'managers') {
    foreach (loadManagers() as $m) {
        $index[] = [
            'type'     => 'manager',
            'title'    => (string) ($m['name'] ?? ''),
            'meta'     => 'Manager · ' . implode(', ', array_slice($m['clubs'] ?? [], 0, 3)),
            'url'      => 'manager.php?id=' . rawurlencode((string) ($m['id'] ?? '')),
            'haystack' => haystack([$m['name'] ?? '', implode(' ', $m['clubs'] ?? []), implode(' ', $m['styles'] ?? [])]),
        ];
    }
}

/* Formations. */
if ($only === '' || $only === 'formations') {
    foreach (loadFormations() as $f) {
        $index[] = [
            'type'     => 'formation',
            'title'    => (string) ($f['name'] ?? ''),
            'meta'     => 'Formation · ' . (string) ($f['label'] ?? ''),
            'url'      => 'formation.php?id=' . rawurlencode((string) ($f['id'] ?? '')),
            'haystack' => haystack([$f['name'] ?? '', $f['label'] ?? '', $f['description'] ?? '']),
        ];
    }
}

/* Roles (with focuses in the haystack so "defend" finds roles). */
if ($only === '' || $only === 'roles') {
    foreach (roleCatalogue() as $role) {
        $index[] = [
            'type'     => 'role',
            'title'    => (string) ($role['name'] ?? ''),
            'meta'     => 'Role · ' . implode(', ', $role['positions'] ?? []),
            'url'      => 'role.php?name=' . rawurlencode((string) ($role['name'] ?? '')),
            'haystack' => haystack([
                $role['name'] ?? '', implode(' ', $role['positions'] ?? []),
                implode(' ', $role['focuses'] ?? []), $role['description'] ?? '',
            ]),
        ];
    }
}

/* Tags. */
if ($only === '' || $only === 'tags') {
    foreach (allTags() as $tag) {
        $index[] = [
            'type'     => 'tag',
            'title'    => $tag,
            'meta'     => 'Tag',
            'url'      => 'tactics.php?tag=' . rawurlencode($tag),
            'haystack' => normalizeText($tag),
        ];
    }
}

echo json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
