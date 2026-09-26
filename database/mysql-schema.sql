-- @loom-file release=0.15.60 revision=4 policy=package-priority
-- LOOM v0.12.00 MySQL / MariaDB persistence schema.
-- Designed for Hostinger MySQL/MariaDB with utf8mb4.

CREATE TABLE IF NOT EXISTS loom_meta (
  meta_key VARCHAR(96) PRIMARY KEY,
  value_text TEXT NULL,
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_clients (
  client_id VARCHAR(96) PRIMARY KEY,
  user_id VARCHAR(96) NULL,
  user_label VARCHAR(191) NULL,
  first_seen DATETIME(3) NOT NULL,
  last_seen DATETIME(3) NOT NULL,
  metadata JSON NULL,
  INDEX idx_client_user (user_id),
  INDEX idx_client_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_client_profiles (
  client_id VARCHAR(96) PRIMARY KEY,
  profile_id VARCHAR(96) NOT NULL,
  username VARCHAR(64) NULL,
  username_norm VARCHAR(64) NULL,
  created_at DATETIME(3) NULL,
  updated_at DATETIME(3) NULL,
  payload_json JSON NULL,
  INDEX idx_client_profile_username_norm (username_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_users (
  user_id VARCHAR(96) PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  username_norm VARCHAR(64) NOT NULL,
  email VARCHAR(254) NOT NULL,
  email_norm VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  privilege ENUM('Admin','User') NOT NULL DEFAULT 'User',
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  UNIQUE KEY uq_users_username_norm (username_norm),
  UNIQUE KEY uq_users_email_norm (email_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_user_clients (
  client_id VARCHAR(96) PRIMARY KEY,
  user_id VARCHAR(96) NOT NULL,
  linked_at DATETIME(3) NOT NULL,
  INDEX idx_user_clients_user (user_id),
  CONSTRAINT fk_user_clients_user FOREIGN KEY (user_id) REFERENCES loom_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_username_registry (
  username_norm VARCHAR(64) PRIMARY KEY,
  username VARCHAR(64) NOT NULL,
  owner_type ENUM('client','user') NOT NULL,
  owner_id VARCHAR(96) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  INDEX idx_username_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS loom_global_profiles (
  owner_type ENUM('client','user') NOT NULL, owner_id VARCHAR(96) NOT NULL, profile_id VARCHAR(96) NOT NULL,
  username VARCHAR(64) NOT NULL, username_norm VARCHAR(64) NOT NULL, avatar_mode VARCHAR(32) NOT NULL DEFAULT 'preset-01',
  custom_ext VARCHAR(12) NULL, mime_type VARCHAR(64) NULL, byte_size INT UNSIGNED NULL, created_at DATETIME(3) NOT NULL, updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY(owner_type,owner_id), UNIQUE KEY uq_global_profile_username(username_norm), UNIQUE KEY uq_global_profile_id(profile_id), INDEX idx_global_profile_updated(updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_project_module_state (
  project_slug VARCHAR(96) NOT NULL, module_id VARCHAR(160) NOT NULL, owner_type ENUM('client','user') NOT NULL, owner_id VARCHAR(96) NOT NULL,
  state_json LONGTEXT NOT NULL, updated_at DATETIME(3) NOT NULL, PRIMARY KEY(project_slug,module_id,owner_type,owner_id),
  INDEX idx_project_module_state_owner(owner_type,owner_id), INDEX idx_project_module_state_updated(updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_project_identities (
  project_slug VARCHAR(96) NOT NULL,
  owner_type ENUM('client','user') NOT NULL,
  owner_id VARCHAR(96) NOT NULL,
  identity_id VARCHAR(96) NOT NULL,
  username_mode VARCHAR(16) NOT NULL DEFAULT 'global',
  username VARCHAR(64) NULL,
  username_norm VARCHAR(64) NULL,
  avatar_mode VARCHAR(32) NOT NULL DEFAULT 'auto',
  custom_ext VARCHAR(12) NULL,
  mime_type VARCHAR(64) NULL,
  byte_size INT UNSIGNED NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY(project_slug,owner_type,owner_id),
  UNIQUE KEY uq_project_identity_username(project_slug,username_norm),
  UNIQUE KEY uq_project_identity_id(identity_id),
  INDEX idx_project_identity_owner(owner_type,owner_id),
  INDEX idx_project_identity_project(project_slug,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS loom_user_avatar_profiles (
  user_id VARCHAR(96) PRIMARY KEY,
  mode VARCHAR(32) NOT NULL DEFAULT 'auto',
  custom_ext VARCHAR(12) NULL,
  mime_type VARCHAR(64) NULL,
  byte_size INT UNSIGNED NULL,
  updated_at DATETIME(3) NOT NULL,
  INDEX idx_avatar_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_auth_sessions (
  token_hash CHAR(64) PRIMARY KEY,
  user_id VARCHAR(96) NOT NULL,
  client_id VARCHAR(96) NOT NULL,
  created_at DATETIME(3) NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  last_seen DATETIME(3) NOT NULL,
  INDEX idx_auth_user (user_id),
  INDEX idx_auth_expiry (expires_at),
  CONSTRAINT fk_auth_user FOREIGN KEY (user_id) REFERENCES loom_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_admin_state (
  state_id TINYINT UNSIGNED PRIMARY KEY,
  client_id VARCHAR(96) NULL,
  user_id VARCHAR(96) NULL,
  token_hash VARCHAR(255) NULL,
  created_at DATETIME(3) NOT NULL,
  bootstrap_method VARCHAR(64) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_module_settings (
  project_slug VARCHAR(96) NOT NULL,
  action_id VARCHAR(191) NOT NULL,
  config_json JSON NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY(project_slug, action_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_global_settings (
  action_id VARCHAR(191) PRIMARY KEY,
  config_json JSON NOT NULL,
  updated_at DATETIME(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_sessions (
  session_id VARCHAR(96) PRIMARY KEY,
  client_id VARCHAR(96) NOT NULL,
  user_id VARCHAR(96) NULL,
  user_label VARCHAR(191) NULL,
  project_slug VARCHAR(96) NOT NULL,
  runtime_id VARCHAR(96) NULL,
  started_at DATETIME(3) NULL,
  ended_at DATETIME(3) NULL,
  last_seen DATETIME(3) NOT NULL,
  lease_expires_at DATETIME(3) NULL,
  status ENUM('live','stale','closed','expired','historical') NOT NULL DEFAULT 'live',
  end_reason VARCHAR(191) NULL,
  active_actions JSON NULL,
  metadata JSON NULL,
  INDEX idx_client_project (client_id, project_slug, last_seen),
  INDEX idx_project_status (project_slug, status, last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_events (
  event_id VARCHAR(96) PRIMARY KEY,
  session_id VARCHAR(96) NULL,
  client_id VARCHAR(96) NULL,
  user_id VARCHAR(96) NULL,
  project_slug VARCHAR(96) NOT NULL,
  action_id VARCHAR(191) NULL,
  event_type VARCHAR(96) NOT NULL,
  state VARCHAR(32) NULL,
  inferred TINYINT(1) NOT NULL DEFAULT 0,
  client_timestamp DATETIME(3) NULL,
  server_timestamp DATETIME(3) NOT NULL,
  payload JSON NULL,
  INDEX idx_session_time (session_id, server_timestamp),
  INDEX idx_client_time (client_id, server_timestamp),
  INDEX idx_user_time (user_id, server_timestamp),
  INDEX idx_action_time (action_id, server_timestamp),
  INDEX idx_project_type (project_slug, event_type, server_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS loom_identity_ips (
  owner_type ENUM('client','user') NOT NULL,
  owner_id VARCHAR(96) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  first_seen DATETIME(3) NOT NULL,
  last_seen DATETIME(3) NOT NULL,
  seen_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
  last_project_slug VARCHAR(96) NULL,
  source VARCHAR(64) NULL,
  PRIMARY KEY(owner_type,owner_id,ip_address),
  INDEX idx_identity_ip_last_seen(last_seen),
  INDEX idx_identity_ip_address(ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_project_user_state (
  project_slug VARCHAR(96) NOT NULL,
  subject_type ENUM('client','user') NOT NULL,
  subject_id VARCHAR(96) NOT NULL,
  banned TINYINT(1) NOT NULL DEFAULT 0,
  include_data TINYINT(1) NOT NULL DEFAULT 0,
  banned_at DATETIME(3) NULL,
  banned_by_user_id VARCHAR(96) NULL,
  updated_at DATETIME(3) NOT NULL,
  PRIMARY KEY(project_slug,subject_type,subject_id),
  INDEX idx_project_banned(project_slug,banned),
  INDEX idx_subject_state(subject_type,subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_admin_audit (
  audit_id VARCHAR(96) PRIMARY KEY,
  admin_user_id VARCHAR(96) NULL,
  admin_client_id VARCHAR(96) NULL,
  action_type VARCHAR(96) NOT NULL,
  target_type VARCHAR(32) NULL,
  target_id VARCHAR(96) NULL,
  project_slug VARCHAR(96) NULL,
  created_at DATETIME(3) NOT NULL,
  payload JSON NULL,
  INDEX idx_admin_audit_time(created_at),
  INDEX idx_admin_audit_target(target_type,target_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOOM v0.12.00 durable Guest Identity / attachment provenance
CREATE TABLE IF NOT EXISTS loom_guest_identities (
  guest_id VARCHAR(96) PRIMARY KEY,
  status VARCHAR(32) NOT NULL,
  primary_client_id VARCHAR(96) NOT NULL,
  attached_user_id VARCHAR(96) NULL,
  merged_into_guest_id VARCHAR(96) NULL,
  created_at DATETIME(3) NOT NULL,
  updated_at DATETIME(3) NOT NULL,
  attached_at DATETIME(3) NULL,
  recovery_hint VARCHAR(32) NULL,
  payload_json LONGTEXT NULL,
  INDEX idx_guest_status(status), INDEX idx_guest_user(attached_user_id), INDEX idx_guest_updated(updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_guest_clients (
  client_id VARCHAR(96) PRIMARY KEY,
  guest_id VARCHAR(96) NOT NULL,
  first_seen DATETIME(3) NOT NULL,
  last_seen DATETIME(3) NOT NULL,
  INDEX idx_guest_clients_guest(guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_identity_attachments (
  attachment_id VARCHAR(96) PRIMARY KEY,
  guest_id VARCHAR(96) NOT NULL,
  user_id VARCHAR(96) NOT NULL,
  source_client_id VARCHAR(96) NULL,
  actor_type VARCHAR(32) NOT NULL,
  actor_id VARCHAR(96) NULL,
  mode VARCHAR(48) NOT NULL,
  attached_at DATETIME(3) NOT NULL,
  summary_json LONGTEXT NULL,
  INDEX idx_attach_guest(guest_id), INDEX idx_attach_user(user_id), INDEX idx_attach_time(attached_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_guest_recovery (
  recovery_hash CHAR(64) PRIMARY KEY,
  guest_id VARCHAR(96) NOT NULL,
  created_at DATETIME(3) NOT NULL,
  last_used_at DATETIME(3) NULL,
  revoked_at DATETIME(3) NULL,
  INDEX idx_guest_recovery_guest(guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_identity_merge_conflicts (
  conflict_id VARCHAR(96) PRIMARY KEY,
  guest_id VARCHAR(96) NULL,
  user_id VARCHAR(96) NULL,
  project_slug VARCHAR(96) NULL,
  module_id VARCHAR(160) NULL,
  path_text VARCHAR(512) NULL,
  guest_value_json LONGTEXT NULL,
  user_value_json LONGTEXT NULL,
  resolution VARCHAR(64) NOT NULL,
  created_at DATETIME(3) NOT NULL,
  resolved_at DATETIME(3) NULL,
  INDEX idx_merge_guest(guest_id), INDEX idx_merge_user(user_id), INDEX idx_merge_project(project_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_identity_audit (
  event_id VARCHAR(96) PRIMARY KEY,
  event_type VARCHAR(96) NOT NULL,
  created_at DATETIME(3) NOT NULL,
  payload_json LONGTEXT NULL,
  INDEX idx_identity_audit_type(event_type), INDEX idx_identity_audit_time(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOOM v0.12.08 additive foundation tables
CREATE TABLE IF NOT EXISTS loom_schema_migrations (
  migration_id VARCHAR(128) PRIMARY KEY, version VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL,
  description VARCHAR(255) NULL, applied_at DATETIME(3) NULL, verified_at DATETIME(3) NULL, details_json LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_guest_profiles (
  guest_profile_id VARCHAR(96) PRIMARY KEY, display_name VARCHAR(64) NOT NULL, avatar_preset VARCHAR(32) NOT NULL,
  current_generation INT UNSIGNED NOT NULL DEFAULT 1, last_claimed_user_id VARCHAR(96) NULL,
  created_at DATETIME(3) NOT NULL, updated_at DATETIME(3) NOT NULL, payload_json LONGTEXT NULL,
  INDEX idx_guest_profile_updated(updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_guest_profile_generations (
  guest_profile_id VARCHAR(96) NOT NULL, generation INT UNSIGNED NOT NULL, client_id VARCHAR(96) NOT NULL,
  guest_id VARCHAR(96) NULL, status VARCHAR(32) NOT NULL, user_id VARCHAR(96) NULL,
  created_at DATETIME(3) NOT NULL, claimed_at DATETIME(3) NULL,
  PRIMARY KEY(guest_profile_id,generation), UNIQUE KEY uq_guest_generation_client(client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS loom_guest_profile_installations (
  installation_id VARCHAR(128) NOT NULL, guest_profile_id VARCHAR(96) NOT NULL, linked_at DATETIME(3) NOT NULL,
  PRIMARY KEY(installation_id,guest_profile_id), INDEX idx_guest_install_profile(guest_profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loom_interaction_events (
  replay_id VARCHAR(96) PRIMARY KEY, project_slug VARCHAR(96) NOT NULL, session_id VARCHAR(96) NOT NULL,
  client_id VARCHAR(96) NOT NULL, user_id VARCHAR(96) NULL, event_type VARCHAR(48) NOT NULL,
  server_timestamp DATETIME(3) NOT NULL, payload_json LONGTEXT NULL,
  INDEX idx_replay_session(session_id,server_timestamp), INDEX idx_replay_project(project_slug,server_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS loom_audit_events (
  audit_id VARCHAR(96) PRIMARY KEY, event_type VARCHAR(96) NOT NULL, message_text VARCHAR(512) NULL,
  client_id VARCHAR(96) NULL, user_id VARCHAR(96) NULL, guest_profile_id VARCHAR(96) NULL, project_slug VARCHAR(96) NULL,
  cause_id VARCHAR(96) NULL, correlation_id VARCHAR(96) NULL, created_at DATETIME(3) NOT NULL, details_json LONGTEXT NULL,
  INDEX idx_audit_project(project_slug,created_at), INDEX idx_audit_actor(user_id,created_at), INDEX idx_audit_corr(correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
