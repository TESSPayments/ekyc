<?php
return <<<'SQL'
CREATE TABLE IF NOT EXISTS refresh_tokens (
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
user_id BIGINT UNSIGNED NOT NULL,
token_hash CHAR(64) NOT NULL,
expires_at TIMESTAMP NOT NULL,
revoked_at TIMESTAMP NULL,
last_used_at TIMESTAMP NULL,
ip_address VARCHAR(64) NULL,
user_agent VARCHAR(255) NULL,
correlation_id CHAR(36) NOT NULL,
created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
UNIQUE KEY uk_refresh_token_hash (token_hash),
KEY ix_refresh_user (user_id),
KEY ix_refresh_expires (expires_at),
KEY ix_refresh_revoked (revoked_at),
CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;