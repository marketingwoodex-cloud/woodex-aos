<?php
// Email settings helpers (SMTP) stored in DB.
// This keeps credentials out of source files.

function ensure_email_settings_table($conn) {
    // Note: Using a single-row settings table (id=1).
    $sql = "CREATE TABLE IF NOT EXISTS email_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        smtp_host VARCHAR(255) NOT NULL DEFAULT '',
        smtp_port INT UNSIGNED NOT NULL DEFAULT 465,
        smtp_secure VARCHAR(10) NOT NULL DEFAULT 'ssl',
        smtp_username VARCHAR(255) NOT NULL DEFAULT '',
        smtp_password VARCHAR(255) NOT NULL DEFAULT '',
        from_email VARCHAR(255) NOT NULL DEFAULT '',
        from_name VARCHAR(120) NOT NULL DEFAULT 'Woodex',
        reply_to_email VARCHAR(255) NOT NULL DEFAULT '',
        reply_to_name VARCHAR(120) NOT NULL DEFAULT 'Woodex',
        order_notify_email VARCHAR(255) NOT NULL DEFAULT '',
        contact_team_email VARCHAR(255) NOT NULL DEFAULT '',
        support_email VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->query($sql);
    // Ensure row exists
    $conn->query("INSERT IGNORE INTO email_settings (id) VALUES (1)");
}

function get_email_settings($conn) {
    ensure_email_settings_table($conn);
    $row = null;
    if ($st = $conn->prepare("SELECT * FROM email_settings WHERE id=1 LIMIT 1")) {
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
    }
    if (!is_array($row)) $row = [];
    // Normalize
    $row['enabled'] = !empty($row['enabled']) ? 1 : 0;
    $row['smtp_port'] = isset($row['smtp_port']) ? (int)$row['smtp_port'] : 465;
    $row['smtp_secure'] = isset($row['smtp_secure']) ? (string)$row['smtp_secure'] : 'ssl';
    return $row;
}

function update_email_settings($conn, $data) {
    ensure_email_settings_table($conn);

    $enabled = !empty($data['enabled']) ? 1 : 0;
    $smtp_host = trim((string)($data['smtp_host'] ?? ''));
    $smtp_port = (int)($data['smtp_port'] ?? 465);
    $smtp_secure = trim((string)($data['smtp_secure'] ?? 'ssl'));
    if (!in_array($smtp_secure, ['ssl','tls',''], true)) $smtp_secure = 'ssl';
    $smtp_username = trim((string)($data['smtp_username'] ?? ''));
    $smtp_password = (string)($data['smtp_password'] ?? '');
    $from_email = trim((string)($data['from_email'] ?? ''));
    $from_name  = trim((string)($data['from_name'] ?? 'Woodex'));
    $reply_to_email = trim((string)($data['reply_to_email'] ?? ''));
    $reply_to_name  = trim((string)($data['reply_to_name'] ?? 'Woodex'));
    $order_notify_email = trim((string)($data['order_notify_email'] ?? ''));
    $contact_team_email = trim((string)($data['contact_team_email'] ?? ''));
    $support_email = trim((string)($data['support_email'] ?? ''));

    // Keep existing password if empty
    if ($smtp_password === '') {
        $cur = get_email_settings($conn);
        $smtp_password = (string)($cur['smtp_password'] ?? '');
    }

    if ($st = $conn->prepare("UPDATE email_settings SET enabled=?, smtp_host=?, smtp_port=?, smtp_secure=?, smtp_username=?, smtp_password=?, from_email=?, from_name=?, reply_to_email=?, reply_to_name=?, order_notify_email=?, contact_team_email=?, support_email=? WHERE id=1")) {
        $st->bind_param(
            'isissssssssss',
            $enabled,
            $smtp_host,
            $smtp_port,
            $smtp_secure,
            $smtp_username,
            $smtp_password,
            $from_email,
            $from_name,
            $reply_to_email,
            $reply_to_name,
            $order_notify_email,
            $contact_team_email,
            $support_email
        );
        $ok = $st->execute();
        $st->close();
        return $ok;
    }
    return false;
}
