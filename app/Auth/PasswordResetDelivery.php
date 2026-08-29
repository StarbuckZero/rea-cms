<?php

declare(strict_types=1);

namespace ReaCms\Auth;

interface PasswordResetDelivery
{
    public function send(string $email, string $resetUrl): bool;
}
