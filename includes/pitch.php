<?php
/**
 * TactiFC — Reusable pitch + player-card rendering helpers.
 *
 * All output is escaped. The pitch markings are pure inline SVG (no images).
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Render a full football pitch with an optional set of players.
 *
 * @param array<int, array<string, mixed>> $players Each: position, x, y, role, focus, familiarity, name?
 * @param array<string, mixed>             $opts    wide(bool), id(string), showLabels(bool)
 */
function renderPitch(array $players = [], array $opts = []): string
{
    $wide      = !empty($opts['wide']);
    $id        = (string) ($opts['id'] ?? 'pitch-' . substr(md5((string) mt_rand()), 0, 6));
    $showLabel = $opts['showLabels'] ?? true;
    $interactive = $opts['interactive'] ?? true;

    $classes = 'pitch' . ($wide ? ' pitch-wide' : '');
    $viewBox = $wide ? '0 0 105 68' : '0 0 68 105';

    $html  = '<div class="pitch-wrap">';
    $html .= '<div class="' . $classes . '" id="' . e($id) . '" role="img" aria-label="Football pitch with player positions">';

    /* Pitch markings SVG. Coordinates are in a 68x105 (portrait) space. */
    if (!$wide) {
        $html .= <<<SVG
<div class="pitch-markings" aria-hidden="true">
<svg viewBox="0 0 68 105" preserveAspectRatio="none">
  <rect x="1" y="1" width="66" height="103" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <line x1="1" y1="52.5" x2="67" y2="52.5" stroke="currentColor" stroke-width="0.5"/>
  <circle cx="34" cy="52.5" r="9.15" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <circle cx="34" cy="52.5" r="0.9" fill="currentColor"/>
  <!-- Top (attacking) box -->
  <rect x="13.84" y="1" width="40.32" height="16.5" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <rect x="24.84" y="1" width="18.32" height="5.5" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <rect x="30.34" y="0.2" width="7.32" height="1.6" fill="currentColor"/>
  <path d="M27.8 17.5 A 6.5 6.5 0 0 0 40.2 17.5" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <circle cx="34" cy="12" r="0.9" fill="currentColor"/>
  <!-- Bottom (own) box -->
  <rect x="13.84" y="87.5" width="40.32" height="16.5" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <rect x="24.84" y="98.5" width="18.32" height="5.5" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <rect x="30.34" y="103.2" width="7.32" height="1.6" fill="currentColor"/>
  <path d="M27.8 87.5 A 6.5 6.5 0 0 1 40.2 87.5" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <!-- Corner arcs -->
  <path d="M1 3 A 2 2 0 0 1 3 1" fill="none" stroke="currentColor" stroke-width="0.4"/>
  <path d="M65 1 A 2 2 0 0 1 67 3" fill="none" stroke="currentColor" stroke-width="0.4"/>
  <path d="M3 104 A 2 2 0 0 1 1 102" fill="none" stroke="currentColor" stroke-width="0.4"/>
  <path d="M67 102 A 2 2 0 0 1 65 104" fill="none" stroke="currentColor" stroke-width="0.4"/>
</svg>
</div>
SVG;
    } else {
        $html .= <<<SVG
<div class="pitch-markings" aria-hidden="true">
<svg viewBox="0 0 105 68" preserveAspectRatio="none">
  <rect x="1" y="1" width="103" height="66" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <line x1="52.5" y1="1" x2="52.5" y2="67" stroke="currentColor" stroke-width="0.5"/>
  <circle cx="52.5" cy="34" r="9.15" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <rect x="1" y="13.84" width="16.5" height="40.32" fill="none" stroke="currentColor" stroke-width="0.5"/>
  <rect x="87.5" y="13.84" width="16.5" height="40.32" fill="none" stroke="currentColor" stroke-width="0.5"/>
</svg>
</div>
SVG;
    }

    /* Player markers. */
    foreach ($players as $player) {
        $pos  = (string) ($player['position'] ?? $player['pos'] ?? '');
        $x    = (float) ($player['x'] ?? 50);
        $y    = (float) ($player['y'] ?? 50);
        $role = (string) ($player['role'] ?? '');
        $focus = (string) ($player['focus'] ?? '');
        $fam  = (string) ($player['familiarity'] ?? '');
        $name = (string) ($player['name'] ?? '');

        $tone = positionTone($pos);
        $tipId = $id . '-tip-' . preg_replace('/[^a-z0-9]/i', '', $pos) . '-' . (int) ($x * 10);

        $hover = e(trim($pos . ' ' . $role . ' ' . $focus . (!$fam ? '' : ' ' . $fam)));

        $html .= '<div class="player" style="left:' . number_format($x, 2, '.', '') . '%;top:' . number_format($y, 2, '.', '') . '%;" '
              . 'tabindex="0" role="button" aria-label="' . $hover . '"'
              . ($interactive ? ' data-player-pos="' . e($pos) . '" data-player-role="' . e($role) . '" data-player-focus="' . e($focus) . '" data-player-fam="' . e($fam) . '"' . ($name !== '' ? ' data-player-name="' . e($name) . '"' : '') : '')
              . '>';
        $html .= '<span class="player-dot ' . $tone . '">' . e($pos) . '</span>';
        if ($showLabel && $role !== '') {
            $html .= '<span class="player-role-label" title="' . e($role . ($focus ? ' · ' . $focus : '')) . '">' . e($role) . '</span>';
        }

        /* Hover tooltip with full role details. */
        $html .= '<span class="player-tip" id="' . e($tipId) . '">';
        if ($name !== '') {
            $html .= '<span class="tip-role">' . e($name) . '</span><br>';
        }
        $html .= '<span class="tip-pos">' . e($pos) . '</span><br>';
        if ($role !== '') {
            $html .= '<span class="tip-role">' . e($role) . '</span><br>';
        }
        if ($focus !== '') {
            $html .= '<span class="tip-focus">Focus: ' . e($focus) . '</span><br>';
        }
        if ($fam !== '') {
            $stars = str_repeat('★', familiarityStars($fam)) . str_repeat('☆', 3 - familiarityStars($fam));
            $html .= '<span class="tip-fam">' . e(familiarityLabel($fam)) . ' <span class="fam-stars">' . $stars . '</span></span>';
        }
        $html .= '</span>';

        $html .= '</div>';
    }

    $html .= '</div></div>';
    return $html;
}

/**
 * Colour tone class for a position marker.
 */
function positionTone(string $pos): string
{
    $pos = strtoupper($pos);
    if ($pos === 'GK') {
        return 'is-gk';
    }
    if (preg_match('/^(LB|RB|CB|LCB|RCB|LWB|RWB)$/', $pos)) {
        return 'is-def';
    }
    if (preg_match('/^(CDM|CM|CAM|LM|RM|LCM|RCM|LDM|RDM|LAM|RAM)$/', $pos)) {
        return 'is-mid';
    }
    return 'is-att';
}

/**
 * Render a compact set of role badges for a tactic card.
 *
 * @param array<int, array{position: string, role: string, focus: string}> $roles
 */
function renderRoleBadges(array $roles, string $tacticId = ''): string
{
    if (!$roles) {
        return '';
    }
    $html = '<div class="key-roles"><div class="key-roles-label">Key Roles</div><div class="pill-row">';
    foreach ($roles as $role) {
        $href = url('tactic.php?id=' . rawurlencode($tacticId) . '#roles');
        $html .= '<a class="role-badge" href="' . e($href) . '" title="View this role in the tactic">'
              . '<span class="rb-pos">' . e($role['position']) . '</span>'
              . '<span class="rb-sep">·</span>'
              . '<span>' . e($role['role']) . '</span>'
              . '</a>';
    }
    $html .= '</div></div>';
    return $html;
}

/**
 * Render a Tactical DNA bar list.
 *
 * @param array<string, int> $dna
 */
function renderDna(array $dna, array $opts = []): string
{
    $compact = !empty($opts['compact']);
    $html = '<div class="dna' . ($compact ? ' dna-compact' : '') . '">';
    foreach ($dna as $label => $value) {
        $value = max(0, min(100, (int) $value));
        $tier = $value >= 80 ? 'high' : ($value >= 55 ? 'mid' : 'low');
        $html .= '<div class="dna-row" title="' . e($label) . ': ' . $value . '/100 (fan recreation profile)">';
        $html .= '<span class="dna-label">' . e((string) $label) . '</span>';
        $html .= '<span class="dna-bar"><span class="dna-fill dna-' . $tier . '" style="width:' . $value . '%"></span></span>';
        $html .= '<span class="dna-value">' . $value . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Render pitch movement overlays (arrows / lanes) for a set of players.
 * Purely decorative SVG interpretation, toggled by JS.
 *
 * @param array<int, array<string, mixed>> $players
 */
function renderMovementOverlay(array $players): string
{
    $arrows = [];
    foreach ($players as $p) {
        $pos = strtoupper((string) ($p['position'] ?? ''));
        $x = (float) ($p['x'] ?? 50);
        $y = (float) ($p['y'] ?? 50);
        $dx = 0.0;
        $dy = 0.0;
        if (preg_match('/^(ST|LST|RST)$/', $pos)) { $dy = -10; }
        elseif (preg_match('/^(LW|RW)$/', $pos)) { $dy = -8; $dx = $x < 50 ? -3 : 3; }
        elseif (preg_match('/^(LM|RM)$/', $pos)) { $dy = -7; }
        elseif (preg_match('/^(LB|RB|LWB|RWB)$/', $pos)) { $dy = -8; }
        elseif (preg_match('/^(CAM|LAM|RAM)$/', $pos)) { $dy = -6; }
        elseif (preg_match('/^(CM|LCM|RCM)$/', $pos)) { $dy = -5; }
        elseif (preg_match('/^(CDM|LDM|RDM)$/', $pos)) { $dy = 3; }
        else { continue; }
        $x2 = $x + $dx;
        $y2 = $y + $dy;
        $arrows[] = ['x1' => $x, 'y1' => $y, 'x2' => $x2, 'y2' => $y2];
    }
    if (!$arrows) {
        return '';
    }
    $html = '<svg class="pitch-movement" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" hidden>';
    $html .= '<defs><marker id="mvArrow" markerWidth="6" markerHeight="6" refX="4" refY="3" orient="auto"><path d="M0,0 L6,3 L0,6 Z" fill="currentColor"/></marker></defs>';
    foreach ($arrows as $a) {
        $html .= '<line x1="' . number_format($a['x1'], 1) . '" y1="' . number_format($a['y1'], 1) . '" x2="' . number_format($a['x2'], 1) . '" y2="' . number_format($a['y2'], 1) . '" stroke="currentColor" stroke-width="0.7" stroke-dasharray="2 1.5" marker-end="url(#mvArrow)" opacity="0.85"/>';
    }
    $html .= '</svg>';
    return $html;
}

/**
 * Render a side-by-side pitch comparison block for two tactics.
 *
 * @param array<int, array<string, mixed>> $a
 * @param array<int, array<string, mixed>> $b
 */
function renderPitchComparison(array $a, array $b, array $opts = []): string
{
    $shape = (string) ($opts['shape'] ?? 'base');
    $labelA = (string) ($opts['labelA'] ?? 'A');
    $labelB = (string) ($opts['labelB'] ?? 'B');
    $labelsA = $opts['labelsA'] ?? null;
    $labelsB = $opts['labelsB'] ?? null;

    $html = '<div class="pitch-compare">';
    foreach ([[$labelA, $a, 'a', $labelsA], [$labelB, $b, 'b', $labelsB]] as [$label, $players, $key, $labels]) {
        $html .= '<div class="pitch-compare-col">';
        $html .= '<div class="pitch-compare-label">' . e($label) . '</div>';
        $html .= renderPitch(shapedSlots($players, $shape, $labels), [
            'id' => 'cmp-pitch-' . $key,
            'showLabels' => true,
            'interactive' => false,
        ]);
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Render the tactical overview strip for a tactic.
 *
 * @param array<string, mixed> $tactic
 */
function renderOverviewStrip(array $tactic): string
{
    $shapes = tacticShapes($tactic);
    $diff = tacticDifficultyInfo($tactic);
    $versions = array_keys($tactic['versions'] ?? []);
    $versionLabels = array_map('versionLabel', $versions);
    $modes = tacticGameModes($tactic);

    $items = [
        ['label' => 'Formation', 'value' => (string) ($tactic['formation'] ?? '')],
        ['label' => 'Style', 'value' => (string) (($tactic['style'][0] ?? '—'))],
        ['label' => 'With Ball', 'value' => $shapes['withBall']],
        ['label' => 'Without Ball', 'value' => $shapes['withoutBall']],
        ['label' => 'Difficulty', 'value' => $diff['level']],
        ['label' => 'Game', 'value' => implode(' / ', $versionLabels)],
    ];

    $html = '<div class="overview-strip">';
    foreach ($items as $item) {
        $html .= '<div class="overview-item">';
        $html .= '<div class="overview-label">' . e($item['label']) . '</div>';
        $html .= '<div class="overview-value">' . e($item['value']) . '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    if ($modes) {
        $html .= '<div class="overview-modes"><span class="overview-label">Game Modes</span>';
        foreach ($modes as $m) {
            $html .= '<span class="tag tag-brand">' . e($m) . '</span>';
        }
        $html .= '</div>';
    }
    return $html;
}
