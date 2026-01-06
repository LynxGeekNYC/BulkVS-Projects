CREATE TABLE dids (
  id INT AUTO_INCREMENT PRIMARY KEY,
  did VARCHAR(32) NOT NULL UNIQUE,
  label VARCHAR(128) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_dids (
  user_id INT NOT NULL,
  did_id INT NOT NULL,
  can_send TINYINT(1) NOT NULL DEFAULT 1,
  can_view TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, did_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (did_id) REFERENCES dids(id) ON DELETE CASCADE
);

CREATE TABLE contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(32) NOT NULL UNIQUE,
  display_name VARCHAR(128) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE,
  color VARCHAR(16) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE contact_tags (
  contact_id INT NOT NULL,
  tag_id INT NOT NULL,
  PRIMARY KEY (contact_id, tag_id),
  FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE conversations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  did_id INT NOT NULL,
  remote_phone VARCHAR(32) NOT NULL,
  contact_id INT NULL,
  last_message_at DATETIME NULL,
  unread_count INT NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_convo (did_id, remote_phone),
  FOREIGN KEY (did_id) REFERENCES dids(id),
  FOREIGN KEY (contact_id) REFERENCES contacts(id)
);

CREATE TABLE conversation_tags (
  conversation_id BIGINT NOT NULL,
  tag_id INT NOT NULL,
  PRIMARY KEY (conversation_id, tag_id),
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT NOT NULL,
  direction ENUM('in','out') NOT NULL,
  body TEXT NULL,
  status ENUM('queued','sent','delivered','failed','received') NOT NULL DEFAULT 'received',
  provider_ref VARCHAR(128) NULL,
  error_text VARCHAR(255) NULL,
  created_by_user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  INDEX idx_convo_time (conversation_id, created_at)
);

CREATE TABLE media (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT NOT NULL,
  original_url TEXT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(128) NULL,
  byte_size BIGINT NULL,
  sha256 CHAR(64) NULL,
  access_token CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (message_id) REFERENCES messages(id),
  INDEX idx_message (message_id),
  INDEX idx_token (access_token)
);

CREATE TABLE send_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT NOT NULL,
  body TEXT NULL,
  media_local_paths TEXT NULL,
  tries INT NOT NULL DEFAULT 0,
  max_tries INT NOT NULL DEFAULT 8,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  last_http_code INT NULL,
  last_error VARCHAR(255) NULL,
  provider_ref VARCHAR(128) NULL,
  created_by_user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  INDEX idx_next_attempt (next_attempt_at, locked_at)
);

CREATE TABLE conversation_user_state (
  conversation_id BIGINT NOT NULL,
  user_id INT NOT NULL,
  last_seen_message_id BIGINT NULL,
  unread_count INT NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id),
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NOT NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
