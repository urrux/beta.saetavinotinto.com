CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'member' CHECK(role IN ('member','admin')),
  is_superadmin INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('invited','active','suspended')),
  member_number TEXT, is_founder INTEGER NOT NULL DEFAULT 0, is_board_member INTEGER NOT NULL DEFAULT 0,
  joined_at TEXT, birth_date TEXT, birth_city TEXT, birth_country TEXT,
  residence_city TEXT, residence_country TEXT, phone TEXT, bio TEXT, photo_url TEXT,
  reset_token_hash TEXT, reset_expires_at TEXT, last_login_at TEXT, admin_notified_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email_hash TEXT NOT NULL,
  ip_hash TEXT NOT NULL,
  successful INTEGER NOT NULL DEFAULT 0,
  attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_login_attempt_email_time ON login_attempts(email_hash, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempt_ip_time ON login_attempts(ip_hash, attempted_at);
CREATE TABLE IF NOT EXISTS request_limits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  action TEXT NOT NULL,
  key_hash TEXT NOT NULL,
  ip_hash TEXT NOT NULL,
  attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_request_limit_action_time ON request_limits(action, attempted_at);
CREATE INDEX IF NOT EXISTS idx_request_limit_key_time ON request_limits(key_hash, attempted_at);
CREATE INDEX IF NOT EXISTS idx_request_limit_ip_time ON request_limits(ip_hash, attempted_at);
CREATE TABLE IF NOT EXISTS member_records (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE SET NULL,
  name TEXT NOT NULL, email TEXT, is_portal_admin INTEGER NOT NULL DEFAULT 0, is_founder INTEGER NOT NULL DEFAULT 0, is_board_member INTEGER NOT NULL DEFAULT 0,
  joined_at TEXT, birth_date TEXT, birth_city TEXT, birth_country TEXT,
  residence_city TEXT, residence_country TEXT, photo_url TEXT,
  source TEXT NOT NULL DEFAULT 'import',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS profile_settings (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  show_publicly INTEGER NOT NULL DEFAULT 1, show_photo INTEGER NOT NULL DEFAULT 0,
  show_birthplace INTEGER NOT NULL DEFAULT 0, show_residence INTEGER NOT NULL DEFAULT 1,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS schema_migrations (
  version TEXT PRIMARY KEY,
  applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS admin_audit_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL, target_type TEXT, target_id TEXT, details TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_audit_created ON admin_audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_action ON admin_audit_log(action);
CREATE TABLE IF NOT EXISTS announcements (
  id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT NOT NULL,
  link_url TEXT, link_label TEXT, is_published INTEGER NOT NULL DEFAULT 1,
  is_featured INTEGER NOT NULL DEFAULT 0, created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS email_delivery_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT, recipient TEXT NOT NULL, subject TEXT NOT NULL,
  message_type TEXT NOT NULL DEFAULT 'general', sent INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL,
  updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS ticket_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  match_name TEXT NOT NULL, match_date TEXT, quantity INTEGER NOT NULL DEFAULT 1,
  competition TEXT, ticket_type TEXT, budget_range TEXT, companion_names TEXT,
  availability_notes TEXT,
  notes TEXT, status TEXT NOT NULL DEFAULT 'received',
  admin_notes TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS resources (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL, description TEXT, url TEXT NOT NULL,
  category TEXT NOT NULL DEFAULT 'General', is_active INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS imported_ticket_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  requester_name TEXT, requester_email TEXT, match_name TEXT NOT NULL, match_date TEXT,
  quantity INTEGER NOT NULL DEFAULT 1, status TEXT NOT NULL DEFAULT 'Histórica', notes TEXT,
  requested_at TEXT, source TEXT NOT NULL DEFAULT 'Google Forms', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS governance_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, document_type TEXT NOT NULL DEFAULT 'other',
  summary TEXT, content TEXT, url TEXT, version TEXT, is_active INTEGER NOT NULL DEFAULT 1,
  created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT, price REAL NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'USD', image_url TEXT, stock INTEGER, is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS product_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE RESTRICT, quantity INTEGER NOT NULL DEFAULT 1,
  total REAL NOT NULL DEFAULT 0, currency TEXT NOT NULL DEFAULT 'USD', delivery_notes TEXT,
  status TEXT NOT NULL DEFAULT 'requested', admin_notes TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
