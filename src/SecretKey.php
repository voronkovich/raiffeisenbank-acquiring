<?php

declare(strict_types=1);

namespace Voronkovich\RaiffeisenBankAcquiring;

use Voronkovich\RaiffeisenBankAcquiring\Exception\InvalidArgumentException;

class SecretKey
{
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromBase64(string $base64EncodedValue): self
    {
        $value = @\base64_decode($base64EncodedValue, true);

        if (false === $value) {
            throw new InvalidArgumentException('Provided key is not base64-encoded.');
        }

        return new self($value);
    }

    public static function fromHex(string $hexEncodedValue): self
    {
        $value = @\hex2bin($hexEncodedValue);

        if (false === $value) {
            throw new InvalidArgumentException('Provided key is not hex-encoded.');
        }

        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function toHex(): string
    {
        return \bin2hex($this->value);
    }

    public function toBase64(): string
    {
        return \bin2hex($this->value);
    }
}
