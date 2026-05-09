<?php
declare(strict_types=1);

if (!function_exists('ensureRequestStatusHistoryTable')) {
    function ensureRequestStatusHistoryTable(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS request_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module VARCHAR(64) NOT NULL,
            request_id INT NOT NULL,
            old_status VARCHAR(64) DEFAULT NULL,
            new_status VARCHAR(64) DEFAULT NULL,
            admin_comment TEXT,
            notify_sent TINYINT(1) NOT NULL DEFAULT 0,
            actor_admin_id INT DEFAULT NULL,
            actor_name VARCHAR(120) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_module_request (module, request_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('logRequestStatusHistory')) {
    function logRequestStatusHistory(
        PDO $db,
        string $module,
        int $requestId,
        ?string $oldStatus,
        ?string $newStatus,
        string $comment = '',
        bool $notifySent = false,
        ?int $actorAdminId = null,
        ?string $actorName = null
    ): void {
        if ($requestId <= 0 || trim($module) === '') {
            return;
        }
        ensureRequestStatusHistoryTable($db);
        $st = $db->prepare("INSERT INTO request_status_history
            (module, request_id, old_status, new_status, admin_comment, notify_sent, actor_admin_id, actor_name)
            VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([
            trim($module),
            $requestId,
            $oldStatus !== null ? trim($oldStatus) : null,
            $newStatus !== null ? trim($newStatus) : null,
            trim($comment),
            $notifySent ? 1 : 0,
            $actorAdminId,
            $actorName !== null ? trim($actorName) : null,
        ]);
    }
}

if (!function_exists('fetchRequestStatusHistory')) {
    function fetchRequestStatusHistory(PDO $db, string $module, int $requestId, int $limit = 30): array
    {
        if ($requestId <= 0 || trim($module) === '') {
            return [];
        }
        ensureRequestStatusHistoryTable($db);
        $limit = max(1, min(200, $limit));
        $st = $db->prepare("SELECT * FROM request_status_history
            WHERE module = ? AND request_id = ?
            ORDER BY id DESC
            LIMIT {$limit}");
        $st->execute([trim($module), $requestId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
