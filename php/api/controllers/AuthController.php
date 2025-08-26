<?php

require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
require_once __DIR__ . '/../lib/JWT.php';
require_once __DIR__ . '/../lib/Audit.php';

class AuthController {
    private PDO $db;
    private array $config;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->config = require __DIR__ . '/../config.php';
    }

    private function readJson(): array {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    public function login(): void {
        $data = $this->readJson();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        if ($email === '' || $password === '') {
            Response::json(['error' => 'Email and password required'], 400);
            return;
        }
        $stmt = $this->db->prepare('SELECT user_id, email, full_name, password_hash, is_active, failed_attempts, locked_until FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            try { Audit::log(null, 'login_failed', ['email' => $email], $_SERVER['REMOTE_ADDR'] ?? null); } catch (Throwable $e) {}
            if ($user) {
                // increment failed attempts and lock if needed
                $this->db->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE user_id = :uid')
                    ->execute([':uid' => $user['user_id']]);
                $row = $this->db->prepare('SELECT failed_attempts FROM users WHERE user_id = :uid');
                $row->execute([':uid' => $user['user_id']]);
                $fa = (int)$row->fetchColumn();
                if ($fa >= 5) {
                    $this->db->prepare("UPDATE users SET locked_until = NOW() + INTERVAL '15 minutes', failed_attempts = 0 WHERE user_id = :uid")
                        ->execute([':uid' => $user['user_id']]);
                }
            }
            Response::json(['error' => 'Invalid credentials'], 401);
            return;
        }
        if (!$user['is_active']) {
            Response::json(['error' => 'Account inactive'], 403);
            return;
        }
        if (!empty($user['locked_until']) && new DateTimeImmutable($user['locked_until']) > new DateTimeImmutable('now')) {
            Response::json(['error' => 'Account locked. Try later.'], 423);
            return;
        }
        // Generate OTP (stub simple numeric)
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTimeImmutable('+5 minutes'))->format('c');
        // Rate limit OTP sends (max 3 per 5m, 10 per 1h)
        $lim5 = $this->db->prepare("SELECT COUNT(*) FROM otp_codes WHERE user_id = :u AND purpose = 'mfa' AND created_at >= NOW() - INTERVAL '5 minutes'");
        $lim5->execute([':u' => $user['user_id']]);
        $c5 = (int)$lim5->fetchColumn();
        $lim60 = $this->db->prepare("SELECT COUNT(*) FROM otp_codes WHERE user_id = :u AND purpose = 'mfa' AND created_at >= NOW() - INTERVAL '60 minutes'");
        $lim60->execute([':u' => $user['user_id']]);
        $c60 = (int)$lim60->fetchColumn();
        if ($c5 >= 3 || $c60 >= 10) { Response::json(['error' => 'Too many OTP requests. Try later.'], 429); return; }
        $ins = $this->db->prepare('INSERT INTO otp_codes (user_id, purpose, code, expires_at) VALUES (:uid, :purpose, :code, :exp)');
        $ins->execute([':uid' => $user['user_id'], ':purpose' => 'mfa', ':code' => $code, ':exp' => $expires]);
        try { Audit::log((int)$user['user_id'], 'login_challenge', [], $_SERVER['REMOTE_ADDR'] ?? null); } catch (Throwable $e) {}
        // Dev mode: surface OTP for testing only
        $dev = $this->config['dev']['mode'] === '1';
        $payload = ['message' => 'OTP sent'];
        if ($dev) { $payload['otp_dev'] = $code; }
        Response::json($payload);
    }

    public function verifyOtp(): void {
        $data = $this->readJson();
        $email = trim($data['email'] ?? '');
        $code = trim($data['code'] ?? '');
        if ($email === '' || $code === '') {
            Response::json(['error' => 'Email and code required'], 400);
            return;
        }
        $stmt = $this->db->prepare('SELECT user_id FROM users WHERE email = :email AND is_active = TRUE');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user) { Response::json(['error' => 'User not found'], 404); return; }

        $otp = $this->db->prepare('SELECT otp_id, code, expires_at, attempts, max_attempts FROM otp_codes WHERE user_id = :uid AND purpose = :p ORDER BY created_at DESC LIMIT 1');
        $otp->execute([':uid' => $user['user_id'], ':p' => 'mfa']);
        $row = $otp->fetch();
        if (!$row) { Response::json(['error' => 'No OTP pending'], 400); return; }
        if ($row['attempts'] >= $row['max_attempts']) { Response::json(['error' => 'Too many attempts'], 429); return; }
        if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable('now')) { Response::json(['error' => 'OTP expired'], 400); return; }
        if (!hash_equals($row['code'], $code)) {
            $this->db->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE otp_id = :id')->execute([':id' => $row['otp_id']]);
            Response::json(['error' => 'Invalid code'], 401);
            return;
        }
        $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE otp_id = :id')->execute([':id' => $row['otp_id']]);
        // Update last login and audit
        $this->db->prepare('UPDATE users SET last_login_at = NOW(), failed_attempts = 0, locked_until = NULL WHERE user_id = :uid')->execute([':uid' => $user['user_id']]);
        $now = time();
        $cfg = $this->config['jwt'];
        $payload = [
            'sub' => $user['user_id'],
            'email' => $email,
            'iat' => $now,
            'exp' => $now + $cfg['ttl_seconds'],
            'iss' => $cfg['issuer'],
            'aud' => $cfg['audience'],
        ];
        $token = JWT::sign($payload, $cfg['secret']);
        try { Audit::log((int)$user['user_id'], 'login_success', [], $_SERVER['REMOTE_ADDR'] ?? null); } catch (Throwable $e) {}
        Response::json(['token' => $token]);
    }

    public function resetPassword(): void {
        $data = $this->readJson();
        $email = trim($data['email'] ?? '');
        if ($email === '') { Response::json(['error' => 'Email required'], 400); return; }
        $stmt = $this->db->prepare('SELECT user_id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user) { Response::json(['message' => 'If account exists, OTP sent']); return; }
        // Rate limit reset OTP
        $lim5 = $this->db->prepare("SELECT COUNT(*) FROM otp_codes WHERE user_id = :u AND purpose = 'reset' AND created_at >= NOW() - INTERVAL '5 minutes'");
        $lim5->execute([':u' => $user['user_id']]);
        if ((int)$lim5->fetchColumn() >= 3) { Response::json(['message' => 'If account exists, OTP sent']); return; }
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTimeImmutable('+10 minutes'))->format('c');
        $ins = $this->db->prepare('INSERT INTO otp_codes (user_id, purpose, code, expires_at) VALUES (:uid, :purpose, :code, :exp)');
        $ins->execute([':uid' => $user['user_id'], ':purpose' => 'reset', ':code' => $code, ':exp' => $expires]);
        Response::json(['message' => 'OTP sent for reset']);
    }

    public function updatePassword(): void {
        $data = $this->readJson();
        $email = trim($data['email'] ?? '');
        $code = trim($data['code'] ?? '');
        $newPassword = $data['new_password'] ?? '';
        if ($email === '' || $code === '' || $newPassword === '') {
            Response::json(['error' => 'Email, code and new_password required'], 400);
            return;
        }
        $stmt = $this->db->prepare('SELECT user_id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if (!$user) { Response::json(['error' => 'User not found'], 404); return; }
        $otp = $this->db->prepare('SELECT otp_id, code, expires_at, attempts, max_attempts FROM otp_codes WHERE user_id = :uid AND purpose = :p ORDER BY created_at DESC LIMIT 1');
        $otp->execute([':uid' => $user['user_id'], ':p' => 'reset']);
        $row = $otp->fetch();
        if (!$row) { Response::json(['error' => 'No OTP pending'], 400); return; }
        if ($row['attempts'] >= $row['max_attempts']) { Response::json(['error' => 'Too many attempts'], 429); return; }
        if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable('now')) { Response::json(['error' => 'OTP expired'], 400); return; }
        if (!hash_equals($row['code'], $code)) {
            $this->db->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE otp_id = :id')->execute([':id' => $row['otp_id']]);
            Response::json(['error' => 'Invalid code'], 401);
            return;
        }
        // Password policy (basic); harden later per PRD
        if (strlen($newPassword) < 10) { Response::json(['error' => 'Password too short'], 400); return; }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare('UPDATE users SET password_hash = :ph, updated_at = NOW() WHERE user_id = :uid')
            ->execute([':ph' => $hash, ':uid' => $user['user_id']]);
        $this->db->prepare('UPDATE otp_codes SET consumed_at = NOW() WHERE otp_id = :id')->execute([':id' => $row['otp_id']]);
        Response::json(['message' => 'Password updated']);
    }
}


