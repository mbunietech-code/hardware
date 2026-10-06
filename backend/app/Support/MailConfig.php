<?php

namespace App\Support;

/** Applies the SMTP settings saved in the web Settings page (when a host is set). */
class MailConfig
{
    public static function apply(): void
    {
        $s = Settings::all();
        if (empty($s['mail_host'])) {
            return; // keep .env mailer (log in development)
        }
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $s['mail_host'],
            'mail.mailers.smtp.port' => (int) $s['mail_port'],
            'mail.mailers.smtp.scheme' => $s['mail_encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $s['mail_username'] ?: null,
            'mail.mailers.smtp.password' => $s['mail_password'] ?: null,
            'mail.from.address' => $s['mail_from_address'] ?: $s['mail_username'],
            'mail.from.name' => $s['business_name'],
        ]);
    }
}
