# Authentication and MFA Policy

## Login

- Credentials: email + password
- Storage: bcrypt hash (cost=12). Password policy: min length 10, complexity (add per hospital policy)
- Sessions: JWT (HS256) with 1h TTL, includes `sub`, `email`, `iat`, `exp`, `iss`, `aud`
- Logout: client discards token; server can support blacklist later

## MFA (OTP)

- Factors: Email/SMS OTP initially; TOTP app support planned
- OTP length: 6 digits; expiry: 5 minutes (login), 10 minutes (reset)
- Rate limiting: per user/IP for send/verify; lockout after N attempts
- Recovery: reset via OTP to registered channel after verification

## Reset/Update Password

- Reset: OTP verification then password update
- Update: authenticated user can update password; rehash if cost changes

## Security Controls

- TLS required; secure cookie settings if cookies are introduced
- Secrets managed via environment vars or secrets store; rotation process defined
- Audit all auth events (login attempt, OTP send/verify, reset, update)

## Endpoints

- POST /api/auth/login
- POST /api/auth/verify-otp
- POST /api/auth/reset-password
- PUT  /api/auth/update-password
