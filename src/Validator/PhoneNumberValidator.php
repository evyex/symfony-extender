<?php

namespace Evyex\SymfonyExtender\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class PhoneNumberValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (null === $value) {
            return;
        }

        if (!$constraint instanceof PhoneNumber) {
            throw new UnexpectedTypeException($constraint, PhoneNumber::class);
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $clearValue = str_replace([' ', '-', '(', ')'], '', $value);
        if (!preg_match('/^\+?[1-9][0-9]{9,14}$/', $clearValue) || !$this->checkFirstChar($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setCode(PhoneNumber::INVALID_FORMAT_ERROR)
                ->addViolation()
            ;
        }
    }

    private function checkFirstChar(string $value): bool
    {
        $v = $value[0];

        return '+' === $v || is_numeric($v);
    }
}
