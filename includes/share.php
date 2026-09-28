<?php
/**
 * TactiFC — Reusable action bar components (share, print, duplicate).
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Render the share / print / duplicate action bar for a tactic.
 *
 * @param array<string, mixed> $tactic
 */
function renderTacticActions(array $tactic): string
{
    $id = (string) ($tactic['id'] ?? '');
    $url = url('tactic.php?id=' . rawurlencode($id));

    $keyRoles = array_map(
        static fn ($r) => trim($r['role'] . ' ' . $r['position']),
        tacticKeyRoles($tactic, 5)
    );
    $shapes = tacticShapes($tactic);

    $summary = [
        'manager'     => (string) ($tactic['manager'] ?? ''),
        'club'        => (string) ($tactic['club'] ?? ''),
        'season'      => (string) ($tactic['season'] ?? ''),
        'formation'   => (string) ($tactic['formation'] ?? ''),
        'style'       => array_map('strval', $tactic['style'] ?? []),
        'withBall'    => $shapes['withBall'],
        'withoutBall' => $shapes['withoutBall'],
        'keyRoles'    => $keyRoles,
        'url'         => $url,
    ];

    $html  = '<div class="action-bar">';
    $html .= '<button type="button" class="btn btn-ghost btn-sm" data-copy-link="' . e($url) . '">🔗 Copy Link</button>';
    $html .= '<button type="button" class="btn btn-ghost btn-sm" data-copy-tactic="' . e(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">📋 Copy Tactic</button>';
    $html .= '<button type="button" class="btn btn-ghost btn-sm" data-print>🖨️ Print</button>';
    $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url('builder.php?from=' . rawurlencode($id))) . '">✏️ Duplicate &amp; Edit</a>';
    $html .= '<a class="btn btn-ghost btn-sm" href="' . e(url('compare.php?add=' . rawurlencode($id))) . '">⚖️ Compare</a>';
    $html .= '</div>';
    return $html;
}
