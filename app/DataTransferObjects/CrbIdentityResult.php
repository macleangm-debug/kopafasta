<?php

namespace App\DataTransferObjects;

final class CrbIdentityResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?string $fullName = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $gender = null,
        public readonly ?string $nationalId = null,
        public readonly ?string $mobile = null,
        public readonly ?string $address = null,
        public readonly ?string $searchScore = null,
        public readonly ?string $crbRuid = null,
        public readonly array $candidates = [],
        public readonly array $raw = [],
    ) {}

    public static function failed(string $message, string $status = 'failed', array $raw = []): self
    {
        return new self(
            success: false,
            status: $status,
            message: $message,
            raw: $raw,
        );
    }

    public static function verified(
        string $fullName,
        ?string $firstName,
        ?string $lastName,
        ?string $dateOfBirth,
        ?string $gender,
        ?string $nationalId,
        ?string $searchScore = null,
        ?string $crbRuid = null,
        array $raw = [],
    ): self {
        return new self(
            success: true,
            status: 'verified',
            fullName: $fullName,
            firstName: $firstName,
            lastName: $lastName,
            dateOfBirth: $dateOfBirth,
            gender: $gender,
            nationalId: $nationalId,
            searchScore: $searchScore,
            crbRuid: $crbRuid,
            raw: $raw,
        );
    }

    public function isMultihit(): bool
    {
        return $this->status === 'multihit' && count($this->candidates) > 0;
    }
}
