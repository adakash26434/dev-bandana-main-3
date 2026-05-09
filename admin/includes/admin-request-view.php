<?php
/**
 * admin/includes/admin-request-view.php
 * ══════════════════════════════════════════════════════════════
 * Unified Admin Request Detail View — single visual language
 * across job / account / kyc / loan / grievance / welfare /
 * appointment / vendor / message / feedback / etc.
 *
 * Renders:
 *   ┌──────────────────────────────────────────────┐
 *   │  Name + status badge          [← फिर्ता]      │
 *   ├──────────────────────────────────────────────┤
 *   │  Tabs: अवलोकन | कागजात | डेटा | गतिविधि लग    │
 *   ├──────────────────────────────────┬───────────┤
 *   │  Active tab content              │  Sidebar  │
 *   │                                  │  (status  │
 *   │                                  │  update + │
 *   │                                  │  notes)   │
 *   └──────────────────────────────────┴───────────┘
 *
 * Usage:
 *   require_once __DIR__ . '/admin-request-view.php';
 *   echo renderAdminRequestView([
 *     'title'      => $app['full_name'],
 *     'subtitle'   => 'पद: '.$app['job_title'],
 *     'status'     => $app['status'],
 *     'statusMap'  => [
 *        'pending'=>['warning','पेन्डिङ'],
 *        'selected'=>['success','चयन'],
 *        ...
 *     ],
 *     'backUrl'    => 'job-applications.php',
 *     'tabs'       => [
 *        ['id'=>'overview','label'=>'अवलोकन','icon'=>'fa-circle-info','html'=>...],
 *        ['id'=>'docs',    'label'=>'कागजात','icon'=>'fa-folder',     'html'=>...],
 *        ['id'=>'data',    'label'=>'आवेदक डेटा','icon'=>'fa-table',  'html'=>...],
 *        ['id'=>'log',     'label'=>'गतिविधि लग','icon'=>'fa-clock-rotate-left','html'=>...],
 *     ],
 *     'sidebar'    => $sidebarHtml,
 *   ]);
 * ══════════════════════════════════════════════════════════════
 */
if (!defined('IS_ADMIN_PAGE')) { http_response_code(403); exit('Access denied.'); }

if (!function_exists('arvStatusBadge')) {
    /**
     * Status badge with consistent color palette across all request pages.
     * @param string $status   Raw status key (e.g. 'pending', 'approved')
     * @param array  $map      Optional ['key' => ['bootstrap-color', 'Display Label']]
     */
    function arvStatusBadge(string $status, array $map = []): string {
        $defaultMap = [
            'pending'     => ['warning', 'पेन्डिङ'],
            'shortlisted' => ['info',    'छनोट'],
            'interviewed' => ['secondary','अन्तर्वार्ता'],
            'selected'    => ['success', 'चयन'],
            'approved'    => ['success', 'स्वीकृत'],
            'rejected'    => ['danger',  'अस्वीकृत'],
            'processing'  => ['info',    'प्रक्रियामा'],
            'in_review'   => ['info',    'समीक्षामा'],
            'review'      => ['info',    'समीक्षामा'],
            'forwarded'   => ['info',    'फरवार्ड'],
            'resolved'    => ['success', 'समाधान'],
            'closed'      => ['secondary','बन्द'],
            'unread'      => ['warning', 'नयाँ'],
            'read'        => ['secondary','पढिएको'],
            'replied'     => ['success', 'जवाफ दिइयो'],
            'cancelled'   => ['danger',  'रद्द'],
            'paid'        => ['success', 'भुक्तानी'],
            'unpaid'      => ['warning', 'अधुरो'],
        ];
        $merged = $map + $defaultMap;
        $key    = strtolower(trim($status));
        $entry  = $merged[$key] ?? ['secondary', $status !== '' ? ucfirst($status) : '—'];
        [$color, $label] = $entry;
        return '<span class="arv-status-badge arv-status-' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '">'
             . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('arvAssetsOnce')) {
    function arvAssetsOnce(): string {
        static $emitted = false;
        if ($emitted) return '';
        $emitted = true;
        return <<<'HTML'
<style>
.arv-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    box-shadow:0 1px 3px rgba(15,23,42,.04),0 8px 24px rgba(15,23,42,.05);
    overflow:hidden;
    margin-bottom:24px;
}
.arv-header{
    display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
    padding:18px 22px;
    border-bottom:1px solid #eef2f7;
    background:linear-gradient(180deg,#fbfdfa 0%,#ffffff 100%);
}
.arv-header-main{display:flex;align-items:center;gap:12px;flex-wrap:wrap;min-width:0;}
.arv-avatar{
    width:40px;height:40px;border-radius:50%;
    background:color-mix(in srgb,var(--primary-color,#1a5f2a) 14%,#fff);
    color:var(--primary-color,#1a5f2a);
    display:grid;place-items:center;font-size:1rem;flex-shrink:0;
}
.arv-title{
    margin:0;font-size:1.05rem;font-weight:700;color:#111827;
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
}
.arv-subtitle{font-size:.78rem;color:#6b7280;margin-top:2px;}
.arv-status-badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 12px;border-radius:999px;font-size:.72rem;font-weight:700;
    line-height:1.4;letter-spacing:.01em;
}
.arv-status-success{background:#dcfce7;color:#166534;}
.arv-status-warning{background:#fef9c3;color:#854d0e;}
.arv-status-danger{background:#fee2e2;color:#991b1b;}
.arv-status-info{background:#dbeafe;color:#1e40af;}
.arv-status-secondary{background:#f3f4f6;color:#374151;}
.arv-status-primary{background:#dcfce7;color:#166534;}
.arv-back-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 14px;border-radius:999px;
    background:#fff;border:1px solid #e5e7eb;
    color:#374151;font-size:.78rem;font-weight:600;
    text-decoration:none;transition:all .15s;flex-shrink:0;
}
.arv-back-btn:hover{background:#f9fafb;border-color:#d1d5db;color:#111827;}

.arv-tabs{
    display:flex;gap:4px;flex-wrap:wrap;
    padding:0 22px;
    border-bottom:1px solid #eef2f7;
    background:#fff;
    overflow-x:auto;-webkit-overflow-scrolling:touch;
}
.arv-tab{
    appearance:none;background:none;border:none;
    padding:14px 18px 12px;
    font-size:.85rem;font-weight:600;color:#6b7280;
    border-bottom:3px solid transparent;
    cursor:pointer;transition:color .15s,border-color .15s;
    display:inline-flex;align-items:center;gap:7px;white-space:nowrap;
    margin-bottom:-1px;
}
.arv-tab:hover{color:#111827;}
.arv-tab.is-active{
    color:var(--primary-color,#1a5f2a);
    border-bottom-color:var(--primary-color,#1a5f2a);
}
.arv-tab i{font-size:.82rem;}

.arv-body{
    display:grid;grid-template-columns:minmax(0,1fr) 320px;
    gap:0;align-items:stretch;
}
.arv-body.no-sidebar{grid-template-columns:1fr;}
.arv-main{padding:22px;min-width:0;}
.arv-sidebar{
    border-left:1px solid #eef2f7;
    background:#fafbfc;
    padding:18px 18px 22px;
}
@media (max-width:991.98px){
    .arv-body{grid-template-columns:1fr;}
    .arv-sidebar{border-left:none;border-top:1px solid #eef2f7;}
}

.arv-pane{display:none;}
.arv-pane.is-active{display:block;animation:arvFade .18s ease-out;}
@keyframes arvFade{from{opacity:0;transform:translateY(2px);}to{opacity:1;transform:none;}}

.arv-section-title{
    font-size:.78rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.04em;color:var(--primary-color,#1a5f2a);
    margin:0 0 10px;display:flex;align-items:center;gap:6px;
}
.arv-section-title i{font-size:.78rem;}
.arv-section + .arv-section{margin-top:22px;}

.arv-kv{
    width:100%;border-collapse:collapse;
}
.arv-kv td{
    padding:8px 12px;font-size:.85rem;vertical-align:top;
    border-bottom:1px solid #f3f4f6;
}
.arv-kv tr:last-child td{border-bottom:none;}
.arv-kv td:first-child{
    color:#6b7280;font-weight:600;width:38%;
}
.arv-kv td:last-child{color:#111827;word-break:break-word;}
.arv-kv a{color:var(--primary-color,#1a5f2a);text-decoration:none;}
.arv-kv a:hover{text-decoration:underline;}

.arv-doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;}
.arv-doc{
    display:flex;align-items:center;gap:10px;
    padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;
    background:#fff;color:#374151;text-decoration:none;
    font-size:.83rem;font-weight:600;transition:all .15s;
}
.arv-doc:hover{border-color:var(--primary-color,#1a5f2a);background:#fbfdfa;color:#111827;transform:translateY(-1px);}
.arv-doc i{font-size:1.1rem;color:var(--primary-color,#1a5f2a);flex-shrink:0;}
.arv-doc-empty{font-size:.85rem;color:#9ca3af;padding:14px 0;}

.arv-text-block{
    background:#f9fafb;border:1px solid #f3f4f6;border-radius:10px;
    padding:12px 14px;font-size:.85rem;line-height:1.65;color:#374151;
    white-space:pre-wrap;word-break:break-word;
}

.arv-log-list{display:flex;flex-direction:column;gap:8px;}
.arv-log-item{
    background:#fff;border:1px solid #e5e7eb;border-radius:10px;
    padding:10px 12px;
}
.arv-log-arrow{font-weight:700;color:#111827;font-size:.85rem;}
.arv-log-arrow .arv-log-from{color:#9ca3af;font-weight:600;}
.arv-log-arrow .arv-sep{color:#9ca3af;margin:0 4px;}
.arv-log-comment{font-size:.83rem;color:#374151;margin-top:4px;}
.arv-log-meta{font-size:.72rem;color:#9ca3af;margin-top:6px;display:flex;flex-wrap:wrap;gap:8px;}
.arv-log-meta .dot{color:#d1d5db;}
.arv-log-empty{font-size:.85rem;color:#9ca3af;padding:14px;text-align:center;background:#f9fafb;border-radius:10px;}
.arv-log-notify{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.arv-chip{
    display:inline-flex;align-items:center;gap:5px;
    padding:3px 10px;border-radius:999px;
    font-size:.7rem;font-weight:700;line-height:1.4;
    border:1px solid transparent;
}
.arv-chip i{font-size:.65rem;}
.arv-chip--ok{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
.arv-chip--err{background:#fee2e2;color:#991b1b;border-color:#fecaca;}
.arv-chip--skip{background:#f3f4f6;color:#374151;border-color:#e5e7eb;}
.arv-chip--none{background:#f9fafb;color:#9ca3af;border-color:#f3f4f6;}
.arv-chip--intent{background:#dbeafe;color:#1e40af;border-color:#bfdbfe;}
.arv-chip--intent-off{background:#fef9c3;color:#854d0e;border-color:#fde68a;}

/* Notify checkbox row in status update form */
.arv-notify-row{
    background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;
    padding:10px 12px;
}
.arv-notify-toggle{
    display:flex;align-items:center;gap:8px;cursor:pointer;
    font-size:.83rem;font-weight:600;color:#111827;margin-bottom:6px;
}
.arv-notify-toggle input{margin:0;cursor:pointer;}
.arv-notify-toggle i{color:var(--primary-color,#1a5f2a);}
.arv-notify-channels{display:flex;flex-wrap:wrap;gap:10px;font-size:.72rem;font-weight:600;}
.arv-notify-channels span{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;}
.arv-notify-channels .is-on{background:#ecfdf5;color:#047857;}
.arv-notify-channels .is-off{background:#f3f4f6;color:#9ca3af;}
.arv-notify-channels i{font-size:.7rem;}

.arv-action-card{
    background:#fff;border:1px solid #e5e7eb;border-radius:12px;
    padding:14px;
}
.arv-action-card + .arv-action-card{margin-top:14px;}
.arv-action-title{
    font-size:.78rem;font-weight:700;text-transform:uppercase;
    letter-spacing:.04em;color:#374151;margin:0 0 12px;
    display:flex;align-items:center;gap:6px;
}
.arv-action-title i{color:var(--primary-color,#1a5f2a);}
.arv-action-card .form-label{font-size:.78rem;font-weight:600;color:#374151;margin-bottom:5px;}
.arv-action-card .form-select,.arv-action-card .form-control{font-size:.85rem;}
.arv-action-card .btn{font-size:.85rem;font-weight:600;}

.arv-meta-list{font-size:.82rem;color:#374151;line-height:1.7;}
.arv-meta-list i{color:var(--primary-color,#1a5f2a);width:14px;text-align:center;margin-right:4px;}
.arv-meta-list b{color:#111827;font-weight:600;}
</style>
<script>
(function(){
    function bindOne(card){
        if (card.dataset.arvBound==='1') return;
        card.dataset.arvBound='1';
        var tabs=card.querySelectorAll('.arv-tab');
        var panes=card.querySelectorAll('.arv-pane');
        tabs.forEach(function(t){
            t.addEventListener('click',function(){
                var id=t.getAttribute('data-tab');
                tabs.forEach(function(x){x.classList.toggle('is-active',x===t);});
                panes.forEach(function(p){p.classList.toggle('is-active',p.getAttribute('data-pane')===id);});
                try{ var u=new URL(location.href); u.searchParams.set('tab',id); history.replaceState(null,'',u.toString()); }catch(e){}
            });
        });
        try{
            var u=new URL(location.href); var want=u.searchParams.get('tab');
            if(want){ var tgt=card.querySelector('.arv-tab[data-tab="'+want+'"]'); if(tgt) tgt.click(); }
        }catch(e){}
    }
    function init(){ document.querySelectorAll('.arv-card').forEach(bindOne); }
    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
</script>
HTML;
    }
}

if (!function_exists('renderAdminRequestView')) {
    /**
     * @param array $cfg {
     *   @var string  $title       Person/applicant name (heading)
     *   @var string  $subtitle    Optional muted line below title (HTML allowed)
     *   @var string  $status      Status key
     *   @var array   $statusMap   Per-page overrides for arvStatusBadge
     *   @var string  $backUrl     URL to list page
     *   @var string  $backLabel   Button label (default 'फिर्ता')
     *   @var string  $avatarIcon  FA icon class without 'fa-' prefix (default 'user')
     *   @var array   $tabs        list<['id','label','icon','html']>
     *   @var string  $sidebar     HTML for right column (status update form etc.)
     * }
     */
    function renderAdminRequestView(array $cfg): string {
        $title      = (string)($cfg['title']      ?? '—');
        $subtitle   = (string)($cfg['subtitle']   ?? '');
        $status     = (string)($cfg['status']     ?? '');
        $statusMap  = (array) ($cfg['statusMap']  ?? []);
        $backUrl    = (string)($cfg['backUrl']    ?? '#');
        $backLabel  = (string)($cfg['backLabel']  ?? 'फिर्ता');
        $avatarIcon = (string)($cfg['avatarIcon'] ?? 'user');
        $tabs       = (array) ($cfg['tabs']       ?? []);
        $sidebar    = (string)($cfg['sidebar']    ?? '');

        /* drop tabs whose html is empty/null so we never show a blank "Document" tab */
        $tabs = array_values(array_filter($tabs, static function ($t) {
            return is_array($t) && trim((string)($t['html'] ?? '')) !== '';
        }));
        if (!$tabs) {
            $tabs[] = ['id'=>'overview','label'=>'अवलोकन','icon'=>'fa-circle-info','html'=>'<p class="text-muted">कुनै विवरण उपलब्ध छैन।</p>'];
        }

        $assets = arvAssetsOnce();

        $tabBtns = '';
        $tabPanes = '';
        foreach ($tabs as $i => $t) {
            $id    = (string)($t['id']    ?? ('tab' . $i));
            $label = (string)($t['label'] ?? '—');
            $icon  = (string)($t['icon']  ?? 'fa-circle');
            $html  = (string)($t['html']  ?? '');
            $active = $i === 0 ? ' is-active' : '';
            $tabBtns  .= '<button type="button" class="arv-tab' . $active . '" data-tab="'
                       . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
                       . '<i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i> '
                       . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button>';
            $tabPanes .= '<div class="arv-pane' . $active . '" data-pane="'
                       . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . $html . '</div>';
        }

        $statusBadge = $status !== '' ? arvStatusBadge($status, $statusMap) : '';
        $sidebarHtml = trim($sidebar) !== ''
            ? '<aside class="arv-sidebar">' . $sidebar . '</aside>'
            : '';
        $bodyClass   = $sidebarHtml === '' ? 'arv-body no-sidebar' : 'arv-body';

        $subtitleHtml = trim($subtitle) !== ''
            ? '<div class="arv-subtitle">' . $subtitle . '</div>'
            : '';

        $back = '<a href="' . htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') . '" class="arv-back-btn">'
              . '<i class="fas fa-arrow-left"></i> '
              . htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8') . '</a>';

        $card = '<div class="arv-card">'
              . '<header class="arv-header">'
              . '<div class="arv-header-main">'
              . '<div class="arv-avatar"><i class="fas fa-' . htmlspecialchars($avatarIcon, ENT_QUOTES, 'UTF-8') . '"></i></div>'
              . '<div>'
              . '<h2 class="arv-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' ' . $statusBadge . '</h2>'
              . $subtitleHtml
              . '</div>'
              . '</div>'
              . '<div class="arv-header-actions">' . $back . '</div>'
              . '</header>'
              . '<nav class="arv-tabs" role="tablist">' . $tabBtns . '</nav>'
              . '<div class="' . $bodyClass . '">'
              . '<div class="arv-main">' . $tabPanes . '</div>'
              . $sidebarHtml
              . '</div>'
              . '</div>';

        return $assets . $card;
    }
}

/* ──────────────────────────────────────────────────────────────
   arvKvTable — quick key/value table for Overview tab.
   Pass associative array; null/'' values are auto-suppressed
   ────────────────────────────────────────────────────────────── */
if (!function_exists('arvKvTable')) {
    function arvKvTable(array $rows, bool $skipEmpty = true): string {
        $out = '<table class="arv-kv">';
        foreach ($rows as $label => $value) {
            if ($skipEmpty && (trim((string)$value) === '' || $value === null)) continue;
            $out .= '<tr><td>' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</td><td>' . $value . '</td></tr>';
        }
        $out .= '</table>';
        return $out;
    }
}

/* ──────────────────────────────────────────────────────────────
   arvDocsGrid — given list of [label, url, icon] document links
   ────────────────────────────────────────────────────────────── */
if (!function_exists('arvDocsGrid')) {
    function arvDocsGrid(array $docs): string {
        $docs = array_values(array_filter($docs, static function ($d) {
            return is_array($d) && trim((string)($d['url'] ?? '')) !== '';
        }));
        if (!$docs) {
            return '<div class="arv-doc-empty">कुनै कागजात attach गरिएको छैन।</div>';
        }
        $out = '<div class="arv-doc-grid">';
        foreach ($docs as $d) {
            $url   = (string)($d['url']   ?? '#');
            $label = (string)($d['label'] ?? 'Document');
            $icon  = (string)($d['icon']  ?? 'fa-file');
            $out  .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="arv-doc" target="_blank" rel="noopener">'
                   . '<i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i> '
                   . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        $out .= '</div>';
        return $out;
    }
}

/* ──────────────────────────────────────────────────────────────
   arvLogList — render request_status_history rows in a unified style
   Expects each row as ['old_status','new_status','admin_comment','actor_name','created_at','notify_sent']
   ────────────────────────────────────────────────────────────── */
if (!function_exists('arvLogList')) {
    function arvLogList(array $rows): string {
        if (!$rows) {
            return '<div class="arv-log-empty">अहिलेसम्म कुनै गतिविधि लग छैन।</div>';
        }
        /* channel-status → display chip class + label + tooltip */
        $chip = static function (string $channel, string $status, string $reason = '', string $to = ''): string {
            $statusMap = [
                'sent'          => ['ok',   'पठाइयो'],
                'failed'        => ['err',  'असफल'],
                'skipped'       => ['skip', 'पठाइएन'],
                'not_attempted' => ['none', 'प्रयास भएन'],
            ];
            $info = $statusMap[$status] ?? ['none', '—'];
            [$cls, $label] = $info;
            $icon = $channel === 'email' ? 'fa-envelope' : 'fa-mobile-screen';
            $chLbl = $channel === 'email' ? 'Email' : 'SMS';
            $title = $chLbl . ': ' . $label;
            if ($to !== '')     $title .= ' → ' . $to;
            if ($reason !== '') $title .= ' (' . $reason . ')';
            return '<span class="arv-chip arv-chip--' . $cls . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
                 . '<i class="fas ' . $icon . '"></i> ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
        };

        $out = '<div class="arv-log-list">';
        foreach ($rows as $h) {
            $from   = htmlspecialchars((string)($h['old_status']    ?? '') ?: '—', ENT_QUOTES, 'UTF-8');
            $to     = htmlspecialchars((string)($h['new_status']    ?? '') ?: '—', ENT_QUOTES, 'UTF-8');
            $cmt    = (string)($h['admin_comment'] ?? '');
            $actor  = htmlspecialchars((string)($h['actor_name']    ?? 'Admin'), ENT_QUOTES, 'UTF-8');
            $when   = (string)($h['created_at']    ?? '');
            $whenH  = function_exists('formatNepaliDate') ? formatNepaliDate($when, true) : htmlspecialchars($when, ENT_QUOTES, 'UTF-8');

            /* Per-channel audit (v2 schema) — fall back to legacy notify_sent */
            $hasV2 = isset($h['notify_email_status']) || isset($h['notify_sms_status']);
            if ($hasV2) {
                $emailChip = $chip('email',
                    (string)($h['notify_email_status'] ?? 'not_attempted'),
                    (string)($h['notify_email_reason'] ?? ''),
                    (string)($h['notify_email_to']     ?? ''));
                $smsChip   = $chip('sms',
                    (string)($h['notify_sms_status'] ?? 'not_attempted'),
                    (string)($h['notify_sms_reason'] ?? ''),
                    (string)($h['notify_sms_to']     ?? ''));
                $intent = !empty($h['admin_chose_to_notify'])
                    ? '<span class="arv-chip arv-chip--intent" title="Admin ले notify पठाउने तय गरेका थिए"><i class="fas fa-paper-plane"></i> Notify</span>'
                    : '<span class="arv-chip arv-chip--intent-off" title="Admin ले notify नचुन्ने तय गरे"><i class="fas fa-bell-slash"></i> No-notify</span>';
                $notifyHtml = $intent . ' ' . $emailChip . ' ' . $smsChip;
            } else {
                $sent = !empty($h['notify_sent']);
                $notifyHtml = $sent
                    ? '<span class="arv-chip arv-chip--ok"><i class="fas fa-bell"></i> Sent</span>'
                    : '<span class="arv-chip arv-chip--none"><i class="fas fa-bell-slash"></i> Not sent</span>';
            }

            $out .= '<div class="arv-log-item">'
                  . '<div class="arv-log-arrow"><span class="arv-log-from">' . $from . '</span>'
                  . '<span class="arv-sep">→</span>' . $to . '</div>';
            if (trim($cmt) !== '') {
                $out .= '<div class="arv-log-comment">' . nl2br(htmlspecialchars($cmt, ENT_QUOTES, 'UTF-8')) . '</div>';
            }
            $out .= '<div class="arv-log-meta">'
                  . '<span><i class="fas fa-user-shield"></i> ' . $actor . '</span>'
                  . '<span class="dot">·</span><span><i class="fas fa-clock"></i> ' . $whenH . '</span>'
                  . '</div>'
                  . '<div class="arv-log-notify">' . $notifyHtml . '</div>'
                  . '</div>';
        }
        $out .= '</div>';
        return $out;
    }
}
