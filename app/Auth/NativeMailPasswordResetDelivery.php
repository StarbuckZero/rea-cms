<?php

declare(strict_types=1);

namespace ReaCms\Auth;

final class NativeMailPasswordResetDelivery implements PasswordResetDelivery
{
    public function __construct(private readonly string $fromAddress)
    {
    }

    public function send(string $email, string $resetUrl): bool
    {
        if (
            filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL) === false
        ) {
            return false;
        }

        $subject = 'Reset your Rea CMS password';
        $message = "A password reset was requested for your Rea CMS account.\n\n"
            . "Use this single-use link within 30 minutes:\n"
            . $resetUrl
            . "\n\nIf you did not request this, no action is required.\n";
        $headers = [
            'From: ' . $this->fromAddress,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return mail($email, $subject, $message, implode("\r\n", $headers));
    }
}
