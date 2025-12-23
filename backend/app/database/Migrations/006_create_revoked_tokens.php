<?php
return <<<'SQL'
CREATE TABLE IF NOT EXISTS revoked_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  jti VARCHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  token_type ENUM('access','refresh') NOT NULL,
  revoked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  reason VARCHAR(64) NULL,
  correlation_id CHAR(36) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_revoked_jti (jti),
  KEY idx_revoked_expires_at (expires_at),
  KEY idx_revoked_user_type (user_id, token_type),
  CONSTRAINT fk_revoked_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;