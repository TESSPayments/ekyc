<?php
return <<<'SQL'
CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_no VARCHAR(32) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('sales','self_service') NOT NULL,
  merchant_type VARCHAR(64) NOT NULL,
  legal_name VARCHAR(255) NOT NULL,
  country CHAR(2) NOT NULL,
  city VARCHAR(128) NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(64) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  submitted_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_applications_no (application_no),
  KEY ix_applications_creator (created_by_user_id),
  KEY ix_applications_status (status),
  KEY ix_applications_created_at (created_at),
  CONSTRAINT fk_applications_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS workflow_states (
  application_id BIGINT UNSIGNED NOT NULL,
  state VARCHAR(32) NOT NULL,
  assigned_role VARCHAR(64) NULL,
  assigned_user_id BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (application_id),
  KEY ix_workflow_states_state (state),
  KEY ix_workflow_states_assigned_user (assigned_user_id),
  CONSTRAINT fk_workflow_states_app FOREIGN KEY (application_id) REFERENCES applications(id),
  CONSTRAINT fk_workflow_states_user FOREIGN KEY (assigned_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS workflow_transitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  from_state VARCHAR(32) NOT NULL,
  to_state VARCHAR(32) NOT NULL,
  action VARCHAR(64) NOT NULL,
  reason TEXT NULL,
  performed_by_user_id BIGINT UNSIGNED NULL,
  correlation_id CHAR(36) NOT NULL,
  idempotency_key VARCHAR(128) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_wt_app (application_id),
  KEY ix_wt_created (created_at),
  KEY ix_wt_correlation (correlation_id),
  KEY ix_wt_idempotency (idempotency_key),
  CONSTRAINT fk_wt_app FOREIGN KEY (application_id) REFERENCES applications(id),
  CONSTRAINT fk_wt_user FOREIGN KEY (performed_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS application_parties (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  party_type ENUM('merchant','ubo','director','signatory') NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  dob DATE NULL,
  nationality CHAR(2) NULL,
  id_number VARCHAR(64) NULL,
  ownership_percent DECIMAL(5,2) NULL,
  details JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_parties_app (application_id),
  KEY ix_parties_type (party_type),
  CONSTRAINT fk_parties_app FOREIGN KEY (application_id) REFERENCES applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  doc_type VARCHAR(64) NOT NULL,
  status ENUM('pending','received','verified','rejected') NOT NULL DEFAULT 'pending',
  verified_by_user_id BIGINT UNSIGNED NULL,
  verified_at TIMESTAMP NULL,
  rejection_reason TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_docs_app (application_id),
  KEY ix_docs_type (doc_type),
  KEY ix_docs_status (status),
  CONSTRAINT fk_docs_app FOREIGN KEY (application_id) REFERENCES applications(id),
  CONSTRAINT fk_docs_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id BIGINT UNSIGNED NOT NULL,
  storage_provider VARCHAR(32) NOT NULL,
  object_key VARCHAR(512) NOT NULL,
  mime_type VARCHAR(128) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_df_doc (document_id),
  KEY ix_df_object (object_key(191)),
  KEY ix_df_sha (sha256),
  CONSTRAINT fk_df_doc FOREIGN KEY (document_id) REFERENCES documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aml_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(64) NOT NULL,
  mode VARCHAR(32) NOT NULL DEFAULT 'full',
  status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  request_payload JSON NULL,
  response_payload JSON NULL,
  correlation_id CHAR(36) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY ix_aml_app (application_id),
  KEY ix_aml_status (status),
  KEY ix_aml_created (created_at),
  KEY ix_aml_correlation (correlation_id),
  CONSTRAINT fk_aml_app FOREIGN KEY (application_id) REFERENCES applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS aml_matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  aml_run_id BIGINT UNSIGNED NOT NULL,
  entity_name VARCHAR(255) NOT NULL,
  match_type ENUM('pep','sanction','adverse_media','other') NOT NULL,
  score DECIMAL(6,3) NOT NULL DEFAULT 0,
  source_ref VARCHAR(128) NULL,
  details JSON NULL,
  disposition ENUM('open','true_positive','false_positive','needs_review') NOT NULL DEFAULT 'open',
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolved_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_am_run (aml_run_id),
  KEY ix_am_type (match_type),
  KEY ix_am_disp (disposition),
  CONSTRAINT fk_am_run FOREIGN KEY (aml_run_id) REFERENCES aml_runs(id),
  CONSTRAINT fk_am_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS risk_scores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  total_score DECIMAL(6,2) NOT NULL DEFAULT 0,
  risk_band ENUM('low','medium','high') NOT NULL,
  components JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_risk_app (application_id),
  KEY ix_risk_band (risk_band),
  CONSTRAINT fk_risk_app FOREIGN KEY (application_id) REFERENCES applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS merchants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  gateway_platform VARCHAR(64) NOT NULL,
  gateway_merchant_id VARCHAR(128) NULL,
  enabled_services JSON NULL,
  settlement_currency CHAR(3) NULL,
  status ENUM('provisioned','live','suspended') NOT NULL DEFAULT 'provisioned',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_merchants_app (application_id),
  KEY ix_merchants_gateway_id (gateway_merchant_id),
  CONSTRAINT fk_merchants_app FOREIGN KEY (application_id) REFERENCES applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(64) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  correlation_id CHAR(36) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_actor (actor_user_id),
  KEY ix_audit_created (created_at),
  KEY ix_audit_correlation (correlation_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(64) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  payload JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY ix_notif_user (user_id, is_read),
  KEY ix_notif_created (created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('in_app','email','sms','webhook') NOT NULL DEFAULT 'in_app',
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_nd_notif (notification_id),
  KEY ix_nd_status (status),
  CONSTRAINT fk_nd_notif FOREIGN KEY (notification_id) REFERENCES notifications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;