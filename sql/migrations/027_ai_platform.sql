-- Platform AI settings, request queue, and per-key rate limiting
CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_request_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(64) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
    payload JSON NOT NULL,
    prompt_preview VARCHAR(500) DEFAULT NULL,
    result JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    assigned_key_index TINYINT UNSIGNED DEFAULT NULL,
    requested_by INT UNSIGNED DEFAULT NULL,
    school_id INT UNSIGNED DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ai_queue_status (status, priority, created_at),
    KEY idx_ai_queue_user (requested_by),
    KEY idx_ai_queue_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_key_usage (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_index TINYINT UNSIGNED NOT NULL,
    window_start DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ai_key_window (key_index, window_start),
    KEY idx_ai_key_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
