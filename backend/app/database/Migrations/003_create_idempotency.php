<?php
return <<<'SQL'
CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  idem_key VARCHAR(128) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  route_name VARCHAR(128) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  response_body JSON NOT NULL,
  response_status SMALLINT UNSIGNED NOT NULL,
  correlation_id CHAR(36) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_idem (idem_key, route_name),
  KEY ix_idem_user (user_id),
  KEY ix_idem_expires (expires_at),
  CONSTRAINT fk_idem_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;