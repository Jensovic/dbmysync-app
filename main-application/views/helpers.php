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
     * Placeholder for the difference count - only filled in once both endpoints are green.
     */
    function diffBadge(int $pairId): string
    {
        return sprintf('<span class="diff-badge" data-diff-pair="%d" hidden></span>', $pairId);
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
