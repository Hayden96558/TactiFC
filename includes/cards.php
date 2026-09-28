<?php
/**
 * TactiFC — Reusable card renderers (tactic cards, manager cards, formation cards).
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pitch.php';

/**
 * Render a tactic card.
 *
 * @param array<string, mixed> $tactic
 * @param array<string, mixed> $opts showRoles(bool), compact(bool)
 */
function renderTacticCard(array $tactic, array $opts = []): string
{
    $showRoles = $opts['showRoles'] ?? true;
    $id        = (string) ($tactic['id'] ?? '');
    $versions  = array_keys($tactic['versions'] ?? []);
    $styles    = array_slice($tactic['style'] ?? [], 0, 3);
    $keyRoles  = $showRoles ? tacticKeyRoles($tactic, 3) : [];
    $form      = (string) ($tactic['formation'] ?? '');
    $tags      = ['style' => implode(' ', $tactic['style'] ?? [])];

    $html  = '<article class="card tactic-card" data-tactic-id="' . e($id) . '"'
           . ' data-search="' . e(normalizeText(implode(' ', [
                $tactic['manager'] ?? '', $tactic['club'] ?? '', $tactic['season'] ?? '',
                $form, implode(' ', $tactic['style'] ?? []),
             ]))) . '"'
           . ' data-manager="' . e((string) ($tactic['manager'] ?? '')) . '"'
           . ' data-club="' . e((string) ($tactic['club'] ?? '')) . '"'
           . ' data-season="' . e((string) ($tactic['season'] ?? '')) . '"'
           . ' data-formation="' . e($form) . '"'
           . ' data-versions="' . e(implode(',', $versions)) . '">';

    $html .= '<div class="card-top">';
    $html .= '<div>';
    $html .= '<h3 class="card-title">' . e((string) ($tactic['manager'] ?? '')) . '</h3>';
    $html .= '<p class="card-club">' . e((string) ($tactic['club'] ?? '')) . '</p>';
    $html .= '<span class="card-season">' . e((string) ($tactic['season'] ?? '')) . '</span>';
    $html .= '</div>';
    $html .= '<span class="formation-badge">' . e($form) . '</span>';
    $html .= '</div>';

    $html .= '<div class="version-pills">';
    foreach (availableVersions() as $v) {
        $on = in_array($v, $versions, true);
        $html .= '<span class="version-pill' . ($on ? ' is-on' : '') . '">' . e(versionLabel($v)) . '</span>';
    }
    $html .= '</div>';

    if ($styles) {
        $html .= '<div class="tag-row">';
        foreach ($styles as $style) {
            $html .= '<span class="tag">' . e((string) $style) . '</span>';
        }
        $html .= '</div>';
    }

    $html .= '<p class="card-desc">' . e((string) ($tactic['description'] ?? '')) . '</p>';

    if ($keyRoles) {
        $html .= renderRoleBadges($keyRoles, $id);
    }

    $html .= '<div class="card-actions">';
    $html .= '<a class="btn btn-primary btn-sm" href="' . e(url('tactic.php?id=' . rawurlencode($id))) . '">View Tactic</a>';
    $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url('compare.php?add=' . rawurlencode($id))) . '" title="Add to comparison">Compare</a>';
    $html .= '<button type="button" class="fav-btn btn-sm" data-fav-toggle="' . e($id) . '" aria-pressed="false" title="Save to favorites">&#9825; Favorite</button>';
    $html .= '</div>';

    $html .= '</article>';
    return $html;
}

/**
 * Render a manager card.
 *
 * @param array<string, mixed> $manager
 */
function renderManagerCard(array $manager): string
{
    $name    = (string) ($manager['name'] ?? '');
    $clubs   = $manager['clubs'] ?? [];
    $styles  = $manager['styles'] ?? [];
    $tactics = tacticsByManager($name);

    $html  = '<article class="card manager-card"'
           . ' data-styles="' . e(implode(',', array_map('strval', $styles))) . '"'
           . ' data-clubs="' . e(implode(',', array_map('strval', $clubs))) . '"'
           . ' data-search="' . e(normalizeText(implode(' ', [$name, (string) ($manager['nationality'] ?? ''), implode(' ', array_map('strval', $clubs)), implode(' ', array_map('strval', $styles))]))) . '">';
    $html .= '<div class="card-top"><div>';
    $html .= '<h3 class="card-title">' . e($name) . '</h3>';
    $html .= '<p class="card-club">' . e((string) ($manager['nationality'] ?? '')) . '</p>';
    $html .= '</div>';
    $html .= '<span class="formation-badge">' . count($tactics) . ' tactic' . (count($tactics) === 1 ? '' : 's') . '</span>';
    $html .= '</div>';

    if (!empty($manager['bio'])) {
        $html .= '<p class="card-desc">' . e((string) $manager['bio']) . '</p>';
    }

    if ($clubs) {
        $html .= '<div class="key-roles"><div class="key-roles-label">Known Clubs</div><div class="tag-row">';
        foreach (array_slice($clubs, 0, 5) as $club) {
            $href = url('search.php?q=' . rawurlencode((string) $club));
            $html .= '<a class="tag" href="' . e($href) . '">' . e((string) $club) . '</a>';
        }
        $html .= '</div></div>';
    }

    if ($styles) {
        $html .= '<div class="tag-row">';
        foreach (array_slice($styles, 0, 4) as $style) {
            $html .= '<span class="tag tag-brand">' . e((string) $style) . '</span>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="card-actions">';
    $html .= '<a class="btn btn-primary btn-sm" href="' . e(url('manager.php?id=' . rawurlencode((string) ($manager['id'] ?? $name)))) . '">View Tactics</a>';
    $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url('search.php?manager=' . rawurlencode($name))) . '">Filter</a>';
    $html .= '</div>';
    $html .= '</article>';
    return $html;
}

/**
 * Render a formation card with a mini pitch preview.
 *
 * @param array<string, mixed> $formation
 */
function renderFormationCard(array $formation): string
{
    $id    = (string) ($formation['id'] ?? '');
    $name  = (string) ($formation['name'] ?? '');
    $slots = formationSlots($id);
    if (!$slots && !empty($formation['slots'])) {
        $slots = $formation['slots'];
    }
    $tacticCount = count(filterTactics(['formation' => $name]));

    $html  = '<article class="card formation-card">';
    $html .= '<div class="card-top"><div>';
    $html .= '<h3 class="card-title">' . e($name) . '</h3>';
    $html .= '<p class="card-club">' . e((string) ($formation['label'] ?? '')) . '</p>';
    $html .= '</div>';
    $html .= '<span class="formation-badge">' . $tacticCount . '</span>';
    $html .= '</div>';

    $html .= renderPitch($slots, ['id' => 'pf-' . preg_replace('/[^a-z0-9]/i', '', $id), 'showLabels' => false, 'interactive' => false]);

    $html .= '<p class="card-desc mt-2">' . e((string) ($formation['description'] ?? '')) . '</p>';

    $html .= '<div class="card-actions">';
    $html .= '<a class="btn btn-primary btn-sm" href="' . e(url('formation.php?id=' . rawurlencode($id))) . '">Explore</a>';
    $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url('tactics.php?formation=' . rawurlencode($name))) . '">Tactics</a>';
    $html .= '</div>';
    $html .= '</article>';
    return $html;
}
