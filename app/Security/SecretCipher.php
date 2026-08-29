<?php

declare(strict_types=1);

namespace ReaCms\Security;

use JsonException;
use RuntimeException;

final class SecretCipher
{
    private readonly string $key;

    public function __construct(string $applicationKey)
    {
        if (strlen($applicationKey) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters.');
        }

        $this->key = hash('sha256', $applicationKey, true);
    }

    /** @throws JsonException */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (!is_string($ciphertext)) {
            throw new RuntimeException('The secret could not be encrypted.');
        }

        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR));
    }

    /** @throws JsonException */
    public function decrypt(string $payload): string
    {
        $json = base64_decode($payload, true);
        $data = is_string($json) ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : null;

        if (!is_array($data) || ($data['v'] ?? null) !== 1) {
            throw new RuntimeException('The encrypted secret payload is invalid.');
        }

        $iv = is_string($data['iv'] ?? null) ? base64_decode($data['iv'], true) : false;
        $tag = is_string($data['tag'] ?? null) ? base64_decode($data['tag'], true) : false;
        $ciphertext = is_string($data['ciphertext'] ?? null)
            ? base64_decode($data['ciphertext'], true)
            : false;

        if (!is_string($iv) || !is_string($tag) || !is_string($ciphertext)) {
            throw new RuntimeException('The encrypted secret payload is invalid.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (!is_string($plaintext)) {
            throw new RuntimeException('The secret could not be decrypted.');
        }

        return $plaintext;
    }
}
