<?php

class Notifications {
    public static function sendEmail(string $to, string $subject, string $body): bool {
        // Stub: integrate with provider (e.g., SES/SMTP) later
        error_log("[EMAIL] to=$to subject=$subject body=" . substr($body,0,120));
        return true;
    }

    public static function sendSms(string $to, string $message): bool {
        // Stub: integrate with provider (e.g., Twilio) later
        error_log("[SMS] to=$to msg=" . substr($message,0,120));
        return true;
    }
}


