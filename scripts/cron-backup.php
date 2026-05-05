<?php
/**
 * ════════════════════════════════════════════════════════════════════
 * AUTOMATED DAILY BACKUP — v4
 * ════════════════════════════════════════════════════════════════════
 * cPanel Cron Job ले यो script daily run गर्छ।
 * Database (mysqldump) + uploads/assets folder लाई gzipped tar मा pack
 * गर्छ, र पुराना ७ दिनभन्दा वर भएका backups auto-delete गर्छ।
 *
 * ── cPanel Cron setup (एकैचोटि एउटा line) ──
 *   0 2 * * *  /usr/bin/php -q /home/USERNAME/public_html/scripts/cron-backup.php
 *
 *   (राति 2 बजे, हरेक दिन — आवश्यक भए time बदल्नु)
 *
 * ── Backup destination ──
 *   public_html/../backups/   (web बाट inaccessible — public_html बाहिर)
 *   यदि त्यो create गर्न नमिल्ने hosting हो भने fallback:
 *   public_html/backups/      (.htaccess ले Deny गर्छ)
 *
 * ── Files generated ──
 *   db_YYYY-MM-DD_HHmmss.sql.gz       (database)
 *   files_YYYY-MM-DD_HHmmss.tar.gz    (uploads + assets/uploads)
 *   backup-log.txt                    (हरेक run को outcome)
 * ════════════════════════════════════════════════════════════════════
 */

/* CLI मात्र — web बाट call गरेमा 403 */
if (PHP_SAPI !== 'cli' && empty($_GET['__manual_test'])) {
    http_response_code(403);
    exit("This script is for CLI/cron only.\n");
}

require_once __DIR__ . '/../includes/config.php';

/* ── Backup directory resolve ── */
$publicHtml = realpath(__DIR__ . '/..');
$preferred  = realpath($publicHtml . '/..') . '/backups';   // outside web root (best)
$fallback   = $publicHtml . '/backups';                     // inside, .htaccess protected
$backupDir  = is_dir(dirname($preferred)) && is_writable(dirname($preferred)) ? $preferred : $fallback;

if (!is_dir($backupDir)) @mkdir($backupDir, 0750, true);

/* ── If fallback (inside public_html), drop .htaccess + index.html guard ── */
if ($backupDir === $fallback) {
    @file_put_contents($backupDir . '/.htaccess',
        "# Block all web access — backups are sensitive\nRequire all denied\nDeny from all\n");
    @file_put_contents($backupDir . '/index.html', "<!-- Forbidden -->");
}

$logFile  = $backupDir . '/backup-log.txt';
$stamp    = date('Y-m-d_His');
$dbFile   = $backupDir . "/db_{$stamp}.sql.gz";
$filesFile= $backupDir . "/files_{$stamp}.tar.gz";

function bk_log(string $msg, string $logFile) {
    $line = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

bk_log("=== Backup started ===", $logFile);
bk_log("Backup dir: $backupDir", $logFile);

/* ────────────────────────────────────────────────────────────────────
   1) DATABASE BACKUP — mysqldump preferred, PDO fallback
   ──────────────────────────────────────────────────────────────────── */
$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbName = defined('DB_NAME') ? DB_NAME : '';
$dbUser = defined('DB_USER') ? DB_USER : '';
$dbPass = defined('DB_PASS') ? DB_PASS : '';

$dumpOk = false;
$mysqldump = trim((string)@shell_exec('which mysqldump 2>/dev/null'));
if ($mysqldump && function_exists('shell_exec')) {
    $tmpSql = $backupDir . "/.tmp_db_{$stamp}.sql";
    $cmd = sprintf(
        '%s --host=%s --user=%s --password=%s --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 %s 2>&1',
        escapeshellcmd($mysqldump),
        escapeshellarg($dbHost), escapeshellarg($dbUser), escapeshellarg($dbPass), escapeshellarg($dbName)
    );
    $out = @shell_exec($cmd . ' > ' . escapeshellarg($tmpSql) . ' 2>&1');
    if (file_exists($tmpSql) && filesize($tmpSql) > 100) {
        /* gzip compress */
        $gz = gzopen($dbFile, 'wb9');
        $fp = fopen($tmpSql, 'rb');
        while (!feof($fp)) gzwrite($gz, fread($fp, 8192));
        fclose($fp); gzclose($gz);
        @unlink($tmpSql);
        $dumpOk = true;
        bk_log("✅ DB dump (mysqldump): " . basename($dbFile) . " (" . round(filesize($dbFile)/1024,1) . " KB)", $logFile);
    } else {
        bk_log("⚠️  mysqldump failed, falling back to PDO. Output: " . trim((string)$out), $logFile);
    }
}

if (!$dumpOk) {
    /* PDO fallback — slower but works without shell_exec */
    try {
        $pdo = getDB();
        $gz = gzopen($dbFile, 'wb9');
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $tr) {
            $tn = $tr[0]; $q = '`' . str_replace('`','``',$tn) . '`';
            $cr = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_ASSOC);
            $createSql = $cr['Create Table'] ?? array_values($cr)[1] ?? '';
            gzwrite($gz, "DROP TABLE IF EXISTS $q;\n$createSql;\n\n");
            $st = $pdo->query("SELECT * FROM $q");
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $cols = []; $vals = [];
                foreach ($row as $k=>$v) {
                    $cols[] = '`' . str_replace('`','``',$k) . '`';
                    $vals[] = $v === null ? 'NULL' : $pdo->quote((string)$v);
                }
                gzwrite($gz, "INSERT INTO $q (".implode(',',$cols).") VALUES (".implode(',',$vals).");\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        $dumpOk = true;
        bk_log("✅ DB dump (PDO fallback): " . basename($dbFile) . " (" . round(filesize($dbFile)/1024,1) . " KB)", $logFile);
    } catch (\Throwable $e) {
        bk_log("❌ DB backup FAILED: " . $e->getMessage(), $logFile);
    }
}

/* ────────────────────────────────────────────────────────────────────
   2) FILES BACKUP — uploads + assets/uploads (skip large files)
   ──────────────────────────────────────────────────────────────────── */
$candidates = [
    $publicHtml . '/uploads',
    $publicHtml . '/assets/uploads',
];
$existing = array_filter($candidates, 'is_dir');

if (empty($existing)) {
    bk_log("ℹ️  No upload folder found — skipping files backup.", $logFile);
} else {
    $tar = trim((string)@shell_exec('which tar 2>/dev/null'));
    if ($tar && function_exists('shell_exec')) {
        $rel = array_map(fn($d)=>basename($d), $existing);
        /* Run tar from public_html so paths are relative */
        $cmd = sprintf('cd %s && %s -czf %s %s 2>&1',
            escapeshellarg($publicHtml), escapeshellcmd($tar),
            escapeshellarg($filesFile), implode(' ', array_map('escapeshellarg', $rel)));
        $out = @shell_exec($cmd);
        if (file_exists($filesFile) && filesize($filesFile) > 100) {
            bk_log("✅ Files tar: " . basename($filesFile) . " (" . round(filesize($filesFile)/1024/1024,2) . " MB)", $logFile);
        } else {
            bk_log("⚠️  tar failed: " . trim((string)$out), $logFile);
        }
    } else {
        /* PHP-only fallback using PharData */
        try {
            $tarTmp = $backupDir . "/.tmp_files_{$stamp}.tar";
            $phar = new PharData($tarTmp);
            foreach ($existing as $d) $phar->buildFromDirectory(dirname($d), '/^' . preg_quote($d,'/') . '/');
            $phar->compress(Phar::GZ);
            @unlink($tarTmp);
            $produced = $tarTmp . '.gz';
            if (file_exists($produced)) @rename($produced, $filesFile);
            if (file_exists($filesFile)) bk_log("✅ Files tar (PHP fallback): " . basename($filesFile), $logFile);
        } catch (\Throwable $e) {
            bk_log("❌ Files backup FAILED: " . $e->getMessage(), $logFile);
        }
    }
}

/* ────────────────────────────────────────────────────────────────────
   3) ROTATION — delete files older than 7 days
   ──────────────────────────────────────────────────────────────────── */
$deleted = 0;
$cutoff  = time() - (7 * 86400);
foreach (glob($backupDir . '/{db_*.sql.gz,files_*.tar.gz}', GLOB_BRACE) as $f) {
    if (@filemtime($f) < $cutoff) {
        if (@unlink($f)) $deleted++;
    }
}
bk_log("🗑️  Rotation: deleted $deleted old backup file(s) (>7 days).", $logFile);
bk_log("=== Backup complete ===\n", $logFile);
