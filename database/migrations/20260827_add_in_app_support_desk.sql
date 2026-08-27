-- Local-only in-app support desk. Requests, messages, assignments, statuses, and notifications remain inside the PHP/XAMPP application.
CREATE TABLE IF NOT EXISTS support_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    requester_user_id BIGINT UNSIGNED NOT NULL,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    category VARCHAR(60) NOT NULL,
    routed_role VARCHAR(40) NOT NULL,
    subject VARCHAR(160) NOT NULL,
    status ENUM('open','in_progress','waiting_on_requester','resolved','closed') NOT NULL DEFAULT 'open',
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_request_requester FOREIGN KEY (requester_user_id) REFERENCES users(id),
    CONSTRAINT fk_support_request_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_support_request_requester (requester_user_id, status, updated_at),
    INDEX idx_support_request_queue (routed_role, assigned_to_user_id, status, updated_at),
    INDEX idx_support_request_status (status, updated_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_request_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    support_request_id BIGINT UNSIGNED NOT NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_message_request FOREIGN KEY (support_request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_message_author FOREIGN KEY (author_user_id) REFERENCES users(id),
    INDEX idx_support_messages_request (support_request_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_request_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    support_request_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_event_request FOREIGN KEY (support_request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    INDEX idx_support_events_request (support_request_id, created_at)
) ENGINE=InnoDB;
