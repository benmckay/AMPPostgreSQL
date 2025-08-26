-- AMP PostgreSQL initial schema
-- Encoding: UTF-8

BEGIN;

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    role_id SERIAL PRIMARY KEY,
    role_name VARCHAR(100) UNIQUE NOT NULL,
    permissions JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Systems table (EHR, PeopleSoft, PACs, etc.)
CREATE TABLE IF NOT EXISTS systems (
    system_id SERIAL PRIMARY KEY,
    system_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id SERIAL PRIMARY KEY,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    department VARCHAR(120),
    password_hash TEXT NOT NULL,
    role_id INTEGER REFERENCES roles(role_id) ON DELETE SET NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role_id);

-- OTP codes for MFA and password reset
CREATE TABLE IF NOT EXISTS otp_codes (
    otp_id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    purpose VARCHAR(40) NOT NULL, -- 'mfa' | 'reset'
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 5,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    consumed_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_otp_user_purpose ON otp_codes(user_id, purpose);

-- Access Requests
DO $$ BEGIN
    CREATE TYPE request_type AS ENUM ('new', 'additional', 'reactivation', 'termination');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
    CREATE TYPE request_status AS ENUM (
      'draft','submitted','pending_manager','pending_hr','approved','rejected','in_fulfillment','completed','cancelled'
    );
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

CREATE TABLE IF NOT EXISTS access_requests (
    request_id BIGSERIAL PRIMARY KEY,
    requester_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    system_id INTEGER REFERENCES systems(system_id) ON DELETE SET NULL,
    type request_type NOT NULL,
    status request_status NOT NULL DEFAULT 'submitted',
    payload JSONB NOT NULL DEFAULT '{}'::jsonb, -- dynamic fields per type / COS
    attachments JSONB NOT NULL DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_requests_requester ON access_requests(requester_id);
CREATE INDEX IF NOT EXISTS idx_requests_status ON access_requests(status);
CREATE INDEX IF NOT EXISTS idx_requests_type ON access_requests(type);

-- Request events (comments/history)
CREATE TABLE IF NOT EXISTS request_events (
    event_id BIGSERIAL PRIMARY KEY,
    request_id BIGINT NOT NULL REFERENCES access_requests(request_id) ON DELETE CASCADE,
    actor_user_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL, -- submitted, approved_manager, approved_hr, rejected, fulfilled, etc.
    comment TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_request_events_req ON request_events(request_id);

-- Immutable audit logs (hash chain)
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id BIGSERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    action VARCHAR(120) NOT NULL,
    details JSONB NOT NULL DEFAULT '{}'::jsonb,
    ip_address INET,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    prev_hash TEXT,
    curr_hash TEXT
);

CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs(action);

-- Helper: compute hash
CREATE OR REPLACE FUNCTION compute_audit_hash(prev text, payload jsonb)
RETURNS text LANGUAGE plpgsql AS $$
DECLARE
  combined text;
BEGIN
  combined := coalesce(prev,'') || ':' || payload::text;
  RETURN encode(digest(combined, 'sha256'), 'hex');
END;
$$;

COMMIT;


