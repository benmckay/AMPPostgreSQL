# Encryption Plan (TLS + pgcrypto)

## In Transit (TLS)

- Terminate TLS at reverse proxy/load balancer (e.g., Nginx/ALB) with strong ciphers.
- Enforce HSTS and secure headers (baseline CSP and headers already added).

## At Rest (PostgreSQL with pgcrypto)

- Extension: `pgcrypto` (enabled in `001_init.sql`).
- Candidate columns for encryption:
  - `users.phone`
  - `users.email` (optional: prefer hashing for lookups + separate encrypted copy if raw email retrieval is required)
  - PII fields inside `access_requests.payload` (encrypt selectively).

## Approach

- Use `pgp_sym_encrypt` with a KMS-managed key passed via env (mounted as file or pulled from secret store).
- Provide views to decrypt for authorized roles only.
- For searchable fields, store a deterministic hash alongside the encrypted value to support lookups.

## Example (email and phone)

```sql
ALTER TABLE users
  ADD COLUMN email_enc bytea,
  ADD COLUMN phone_enc bytea;

UPDATE users
SET email_enc = pgp_sym_encrypt(email, current_setting('app.crypto_key')),
    phone_enc = pgp_sym_encrypt(coalesce(phone,''), current_setting('app.crypto_key'));

-- Optional: drop plaintext columns after validation
-- ALTER TABLE users DROP COLUMN email, DROP COLUMN phone;

CREATE OR REPLACE VIEW v_users_secure AS
SELECT user_id,
       full_name,
       pgp_sym_decrypt(email_enc, current_setting('app.crypto_key')) AS email,
       pgp_sym_decrypt(phone_enc, current_setting('app.crypto_key')) AS phone,
       department, role_id, is_active, last_login_at, created_at
FROM users;
```

## Key Management

- Use Secrets Manager/SSM (AWS) to inject `app.crypto_key` at app start.
- Rotate keys via re-encryption in batches during maintenance windows.

## Application Changes (planned)

- Restrict reads to secure views.
- For email search, store `email_hash = encode(digest(lower(email),'sha256'),'hex')` for lookups without decrypting.
