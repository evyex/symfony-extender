<?php

namespace Evyex\SymfonyExtender\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class PhoneNumber extends Constraint
{
    public const INVALID_FORMAT_ERROR = 'c6486fd0-3ab9-439a-b089-979096fa50d0';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
    ];

    public string $message = 'This value is not a valid phone number.';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(groups: $groups, payload: $payload);

        $this->message = $message ?? $this->message;
    }
}
