-- Active: 1756119651519@@127.0.0.1@5432@amp
BEGIN;

INSERT INTO roles (role_name, permissions) VALUES
  ('Requester', '{}'::jsonb)
ON CONFLICT (role_name) DO NOTHING;

INSERT INTO roles (role_name, permissions) VALUES
  ('COS', '{}'::jsonb),
  ('Manager', '{}'::jsonb),
  ('HR', '{}'::jsonb),
  ('ICT Admin', '{}'::jsonb),
  ('Auditor', '{}'::jsonb)
ON CONFLICT (role_name) DO NOTHING;

INSERT INTO systems (system_name, description) VALUES
  ('EHR', 'Electronic Health Records'),
  ('PeopleSoft', 'HR and Finance'),
  ('PACS', 'Imaging')
ON CONFLICT (system_name) DO NOTHING;

COMMIT;

SELECT * FROM roles;
