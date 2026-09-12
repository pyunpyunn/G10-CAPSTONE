-- Additive table for Super Admin landing page inquiries.
-- Safe to run on shared DB because it only creates a new table when missing.

CREATE TABLE IF NOT EXISTS landing_inquiries (
  inquiry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  organization VARCHAR(150) NULL,
  email VARCHAR(255) NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'new',
  source_page VARCHAR(80) NOT NULL DEFAULT 'landing_page',
  ip_address VARCHAR(80) NULL,
  user_agent VARCHAR(255) NULL,
  responded_at TIMESTAMP NULL,
  handled_by_user_id VARCHAR(80) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (inquiry_id),
  INDEX idx_landing_inquiries_status (status),
  INDEX idx_landing_inquiries_created_at (created_at)
);
