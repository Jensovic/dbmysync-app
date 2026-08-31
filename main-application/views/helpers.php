<?php
/**
 * Shared view helpers
 */

if (!function_exists('icon')) {
    /**
     * Render an icon from public/icons as an inline image.
     *
     * @param string $name Icon file name without extension
     * @param int    $size Rendered size in px (icons are drawn on a 24x24 grid)
     */
    function icon(string $name, int $size = 16): string
    {
        return sprintf(
            '<img src="icons/%s.svg" class="icon" width="%d" height="%d" alt="" aria-hidden="true">',
            htmlspecialchars($name),
            $size,
            $size
        );
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format a timestamp column for display.
     *
     * SQLite stores CURRENT_TIMESTAMP in UTC, so convert to the local timezone
     * before dropping the time part - otherwise late-evening edits show the wrong day.
     */
    function formatDate(?string $timestamp, string $format = 'd.m.Y'): string
    {
        if (empty($timestamp)) {
            return '-';
        }

        try {
            $date = new DateTime($timestamp, new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $date->format($format);
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('statusDot')) {
    /**
     * Placeholder for the endpoint smoke test - filled in by the dashboard script.
     *
     * @param int    $pairId Tool pair the dot belongs to
     * @param string $env    'env1' or 'env2'
     */
    function statusDot(int $pairId, string $env): string
    {
        return sprintf(
            '<span class="status-dot status-checking" data-smoke-pair="%d" data-smoke-env="%s"'
            . ' role="img" title="Status wird gepr&uuml;ft&hellip;" aria-label="Status wird gepr&uuml;ft"></span>',
            $pairId,
            htmlspecialchars($env)
        );
    }
}

if (!function_exists('diffBadge')) {
    /**
     * Difference count next to the pair name.
     *
     * Renders the last stored result so the dashboard does not have to compare
     * both schemas on page load; the refresh buttons update it in place via AJAX.
     *
     * @param array $pair Tool pair row including the last_diff_* columns
     */
    function diffBadge(array $pair): string
    {
        $state = diffBadgeState(
            $pair['last_diff_status'] ?? null,
            $pair['last_diff_count'] ?? null,
            $pair['last_diff_error'] ?? null,
            $pair['last_diff_checked_at'] ?? null
        );

        return sprintf(
            '<span class="diff-badge badge %s" data-diff-pair="%d" title="%s">%s</span>',
            $state['class'],
            (int) $pair['id'],
            htmlspecialchars($state['title']),
            htmlspecialchars($state['label'])
        );
    }
}

if (!function_exists('diffBadgeState')) {
    /**
     * Map a stored difference check onto badge class, label and tooltip.
     *
     * Kept separate from the markup so the AJAX response can describe a fresh
     * result with exactly the same wording the server-rendered badge uses.
     */
    function diffBadgeState(?string $status, $count, ?string $error, ?string $checkedAt): array
    {
        if ($status === 'error') {
            return [
                'class' => 'badge-danger',
                'label' => '!',
                'title' => 'Letzte Prüfung fehlgeschlagen'
                    . ($error ? ': ' . $error : '')
                    . diffCheckedSuffix($checkedAt)
            ];
        }

        if ($status !== 'ok' || $count === null || $count === '') {
            return [
                'class' => 'badge-unknown',
                'label' => '?',
                'title' => 'Noch nicht geprüft - auf Aktualisieren klicken'
            ];
        }

        $count = (int) $count;

        return [
            'class' => $count === 0 ? 'badge-success' : 'badge-warning',
            'label' => (string) $count,
            'title' => ($count === 1
                ? '1 Strukturunterschied zwischen beiden Umgebungen'
                : $count . ' Strukturunterschiede zwischen beiden Umgebungen')
                . diffCheckedSuffix($checkedAt)
        ];
    }
}

if (!function_exists('diffCheckedSuffix')) {
    /**
     * " (geprüft am 01.02.2026 14:30)" - empty when the timestamp is missing
     */
    function diffCheckedSuffix(?string $checkedAt): string
    {
        $formatted = formatDate($checkedAt, 'd.m.Y H:i');

        return $formatted === '-' ? '' : ' (geprüft am ' . $formatted . ')';
    }
}

if (!function_exists('groupDiffSummary')) {
    /**
     * Sum and average of the stored difference counts in a group.
     *
     * Only pairs with a successful last check contribute - an unchecked or
     * failed pair would otherwise silently read as zero differences.
     *
     * @param array $pairs Tool pair rows
     * @return array{sum: ?int, avg: ?float, checked: int, total: int}
     */
    function groupDiffSummary(array $pairs): array
    {
        $sum = 0;
        $checked = 0;

        foreach ($pairs as $pair) {
            if (($pair['last_diff_status'] ?? null) !== 'ok' || $pair['last_diff_count'] === null) {
                continue;
            }

            $sum += (int) $pair['last_diff_count'];
            $checked++;
        }

        return [
            'sum' => $checked > 0 ? $sum : null,
            'avg' => $checked > 0 ? $sum / $checked : null,
            'checked' => $checked,
            'total' => count($pairs)
        ];
    }
}

if (!function_exists('formatDiffAverage')) {
    /**
     * One decimal, German style - mirrored by the dashboard script so a
     * recalculated average reads exactly like the rendered one.
     */
    function formatDiffAverage(float $avg): string
    {
        return number_format($avg, 1, ',', '');
    }
}

if (!function_exists('groupSummaryBadges')) {
    /**
     * The two yellow boxes in the group header: total and average differences.
     *
     * Stays visible when the group is collapsed - that is the whole point of
     * the summary, so a collapsed group still says how far apart it is.
     */
    function groupSummaryBadges(array $pairs): string
    {
        $summary = groupDiffSummary($pairs);
        $scope = sprintf(' (%d von %d Tool Pairs geprüft)', $summary['checked'], $summary['total']);

        return sprintf(
            '<span class="group-summary">'
            . '<span class="badge badge-warning group-sum" title="%s">%s</span>'
            . '<span class="badge badge-warning group-avg" title="%s">Ø %s</span>'
            . '</span>',
            htmlspecialchars('Summe der Differenzen' . $scope),
            htmlspecialchars($summary['sum'] === null ? '-' : (string) $summary['sum']),
            htmlspecialchars('Durchschnittliche Differenzen pro Tool Pair' . $scope),
            htmlspecialchars($summary['avg'] === null ? '-' : formatDiffAverage($summary['avg']))
        );
    }
}

if (!function_exists('diffRefreshButton')) {
    /**
     * Refresh button for the difference count.
     *
     * @param string $scope 'all', 'group' or 'pair'
     * @param string $value Scope target: '' for all, group id (or 'none'), or pair id
     * @param string $label Accessible label / tooltip
     * @param string $class Extra CSS classes
     * @param string $text  Optional visible text next to the icon
     */
    function diffRefreshButton(string $scope, string $value, string $label, string $class = '', string $text = ''): string
    {
        return sprintf(
            '<button type="button" class="btn btn-refresh %s" data-refresh-scope="%s" data-refresh-value="%s" %s>%s%s</button>',
            htmlspecialchars($class),
            htmlspecialchars($scope),
            htmlspecialchars($value),
            iconButtonAttrs($label),
            icon('refresh'),
            $text === '' ? '' : '<span class="btn-refresh-text">' . htmlspecialchars($text) . '</span>'
        );
    }
}

if (!function_exists('iconButtonAttrs')) {
    /**
     * Accessible attributes for an icon-only button: visible tooltip + screen reader label.
     */
    function iconButtonAttrs(string $label): string
    {
        $escaped = htmlspecialchars($label);
        return sprintf('title="%s" aria-label="%s"', $escaped, $escaped);
    }
}
