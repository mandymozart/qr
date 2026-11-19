<?php
declare(strict_types=1);

/**
 * Creates tables on first run and seeds the bootstrap admin (ADMIN_USER).
 * Cheap check on every request: one query against a 1-row table.
 */
const SCHEMA_VERSION = 1;

function migrate(PDO $pdo): void
{
    try {
        $v = (int) $pdo->query('SELECT version FROM schema_version LIMIT 1')->fetchColumn();
        if ($v >= SCHEMA_VERSION) return;
    } catch (PDOException) {
        // table missing → fresh install
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (version INT NOT NULL) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL DEFAULT '',
        role ENUM('admin','user') NOT NULL DEFAULT 'user',
        status ENUM('active','blocked') NOT NULL DEFAULT 'active',
        password_hash VARCHAR(255) NULL,
        token_hash CHAR(64) NULL,
        token_expires_at DATETIME NULL,
        token_created_at DATETIME NULL,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        token_hash CHAR(64) PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS campaigns (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(160) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS links (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        campaign_id INT UNSIGNED NULL,
        code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        title VARCHAR(160) NOT NULL,
        target_url TEXT NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        link_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        device VARCHAR(16) NOT NULL DEFAULT 'other',
        os VARCHAR(16) NOT NULL DEFAULT '',
        referer VARCHAR(190) NOT NULL DEFAULT '',
        KEY link_time (link_id, created_at),
        FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bootstrap admin from ADMIN_USER
    if (!(int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
        $email = (string) config('admin_user', 'admin');
        $st = $pdo->prepare("INSERT INTO users (email, name, role) VALUES (?, 'Admin', 'admin')");
        $st->execute([$email]);
    }

    $pdo->exec('DELETE FROM schema_version');
    $pdo->exec('INSERT INTO schema_version (version) VALUES (' . SCHEMA_VERSION . ')');
}

/** Local-dev schema for SQLite (same tables, SQLite types). */
function migrate_sqlite(PDO $pdo): void
{
    if ($pdo->query("SELECT 1 FROM sqlite_master WHERE name = 'users'")->fetchColumn()) return;
    $now = "DEFAULT (datetime('now','localtime'))";
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        name TEXT NOT NULL DEFAULT '', role TEXT NOT NULL DEFAULT 'user', status TEXT NOT NULL DEFAULT 'active',
        password_hash TEXT NULL, token_hash TEXT NULL, token_expires_at TEXT NULL, token_created_at TEXT NULL,
        last_login_at TEXT NULL, created_at TEXT NOT NULL $now)");
    $pdo->exec("CREATE TABLE sessions (token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, expires_at TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        name TEXT NOT NULL, created_at TEXT NOT NULL $now)");
    $pdo->exec("CREATE TABLE links (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        campaign_id INTEGER NULL REFERENCES campaigns(id) ON DELETE SET NULL, code TEXT NOT NULL UNIQUE, title TEXT NOT NULL,
        target_url TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL $now, updated_at TEXT NOT NULL $now)");
    $pdo->exec("CREATE TABLE hits (id INTEGER PRIMARY KEY AUTOINCREMENT, link_id INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE,
        created_at TEXT NOT NULL $now, device TEXT NOT NULL DEFAULT 'other', os TEXT NOT NULL DEFAULT '', referer TEXT NOT NULL DEFAULT '')");
    $pdo->exec("CREATE INDEX link_time ON hits (link_id, created_at)");
    $st = $pdo->prepare("INSERT INTO users (email, name, role) VALUES (?, 'Admin', 'admin')");
    $st->execute([(string) config('admin_user', 'admin')]);
}
