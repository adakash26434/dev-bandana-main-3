<?php
/**
 * 🔗 Cross-Panel Uniformity Helpers
 * ─────────────────────────────────────────────────────────────
 * Public, Member, Admin तीनै panel मा consistent UI helpers।
 *
 * Auto-loaded via header.php / _bootstrap.php / admin-header.php।
 * सबैले एउटै flash message, breadcrumb, alert markup प्रयोग गर्छन्।
 */

if (!function_exists('coopAlert')) {
    /**
     * Universal alert box — public/member/admin तीनै ठाउँमा एउटै style।
     */
    function coopAlert(string $type, string $message, bool $dismissible = true): string {
        $map = [
            'success' => ['bg' => 'var(--color-success)',  'icon' => 'fa-check-circle'],
            'error'   => ['bg' => 'var(--color-danger)',   'icon' => 'fa-exclamation-circle'],
            'danger'  => ['bg' => 'var(--color-danger)',   'icon' => 'fa-exclamation-circle'],
            'warning' => ['bg' => 'var(--color-warning)',  'icon' => 'fa-exclamation-triangle'],
            'info'    => ['bg' => 'var(--color-info)',     'icon' => 'fa-info-circle'],
        ];
        $m   = $map[$type] ?? $map['info'];
        $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $btn = $dismissible
            ? '<button type="button" class="coop-alert-close" onclick="this.parentElement.remove()" aria-label="Close">×</button>'
            : '';
        return <<<HTML
<div class="coop-alert" style="background:{$m['bg']};color:var(--text-on-primary,white);padding:12px 16px;border-radius:var(--radius-md,10px);display:flex;align-items:center;gap:10px;margin:12px 0;box-shadow:var(--shadow-sm,0 1px 4px rgba(var(--primary-rgb,26,95,42),.12));font-family:var(--font-primary);">
    <i class="fas {$m['icon']}"></i>
    <span style="flex:1;">{$msg}</span>
    {$btn}
</div>
HTML;
    }
}

if (!function_exists('coopFlash')) {
    /**
     * Echo flash message in unified style.
     */
    function coopFlash(): void {
        if (function_exists('getFlash')) {
            $f = getFlash();
            if ($f) echo coopAlert($f['type'] ?? 'info', $f['message'] ?? '');
        }
    }
}

if (!function_exists('coopPanelType')) {
    /**
     * Detect current panel: 'admin' | 'member' | 'public'.
     */
    function coopPanelType(): string {
        $script = $_SERVER['PHP_SELF'] ?? '';
        if (defined('IS_ADMIN_PAGE') && IS_ADMIN_PAGE) return 'admin';
        if (str_contains($script, '/admin/')) return 'admin';
        if (str_contains($script, '/member/')) return 'member';
        return 'public';
    }
}

if (!function_exists('coopCurrentUser')) {
    /**
     * Universal current-user info across panels.
     * Returns: ['type' => 'admin|member|guest', 'id' => int, 'name' => string, 'role' => string]
     */
    function coopCurrentUser(): array {
        if (!empty($_SESSION['admin_id'])) {
            return [
                'type' => 'admin',
                'id'   => (int)$_SESSION['admin_id'],
                'name' => $_SESSION['admin_name'] ?? 'Admin',
                'role' => $_SESSION['admin_role'] ?? 'admin',
            ];
        }
        if (!empty($_SESSION['member_id'])) {
            return [
                'type' => 'member',
                'id'   => (int)$_SESSION['member_id'],
                'name' => $_SESSION['member_name'] ?? 'Member',
                'role' => 'member',
            ];
        }
        return ['type' => 'guest', 'id' => 0, 'name' => '', 'role' => ''];
    }
}

if (!function_exists('coopBreadcrumb')) {
    /**
     * Universal breadcrumb — public/member/admin तीनै ठाउँमा एउटै style।
     * @param array $items [['label' => 'Home', 'url' => '/'], ['label' => 'Current']]
     */
    function coopBreadcrumb(array $items): string {
        $html = '<nav class="coop-breadcrumb" style="font-family:var(--font-primary);font-size:.85rem;color:var(--text-muted);margin-bottom:14px;">';
        $last = count($items) - 1;
        foreach ($items as $i => $it) {
            $label = htmlspecialchars($it['label'] ?? '', ENT_QUOTES);
            if ($i === $last || empty($it['url'])) {
                $html .= "<span style='color:var(--text-primary);font-weight:500;'>{$label}</span>";
            } else {
                $url = htmlspecialchars($it['url'], ENT_QUOTES);
                $html .= "<a href='{$url}' style='color:var(--primary-color);text-decoration:none;'>{$label}</a>";
            }
            if ($i !== $last) $html .= ' <span style="margin:0 6px;color:var(--border-color);">›</span> ';
        }
        $html .= '</nav>';
        return $html;
    }
}
