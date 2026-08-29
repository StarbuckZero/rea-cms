<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Auth\PasswordResetDelivery;

final class CapturingPasswordResetDelivery implements PasswordResetDelivery
{
    /** @var list<array{email: string, url: string}> */
    public array $messages = [];

    public function send(string $email, string $resetUrl): bool
    {
        $this->messages[] = ['email' => $email, 'url' => $resetUrl];

        return true;
    }
}
