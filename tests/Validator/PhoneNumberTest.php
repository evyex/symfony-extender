<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\Validator;

use Evyex\SymfonyExtender\Validator\PhoneNumber;
use Evyex\SymfonyExtender\Validator\PhoneNumberValidator;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class PhoneNumberTest extends ConstraintValidatorTestCase
{
    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new PhoneNumber());
        $this->assertNoViolation();
    }

    #[TestWith(['359876355321'])]
    #[TestWith(['+38(098) 641-28-32'])]
    #[TestWith(['+420 778 420 000'])]
    #[TestWith(['+44 20 7183 8457'])]
    #[TestWith(['+1 650 253 0000'])]
    #[TestWith(['372 5556 0000'])]
    public function testValidPhoneNumber(string $data): void
    {
        $this->validator->validate($data, new PhoneNumber());
        $this->assertNoViolation();
    }

    #[TestWith(['+059876355321'])]
    #[TestWith(['0000'])]
    #[TestWith(['-380986412832'])]
    #[TestWith(['A380986412832'])]
    public function testInvalidPhoneNumber(string $data): void
    {
        $this->validator->validate($data, new PhoneNumber());
        $this->buildViolation('This value is not a valid phone number.')
            ->setParameter('{{ value }}', $data)
            ->setCode(PhoneNumber::INVALID_FORMAT_ERROR)
            ->assertRaised()
        ;
    }

    public function testEmptyString(): void
    {
        $this->validator->validate('', new PhoneNumber());
        $this->assertEquals($this->context->getViolations()->count(), 1);
    }

    #[TestWith([100])]
    #[TestWith([new \DateTimeImmutable()])]
    #[TestWith([true])]
    #[TestWith([[]])]
    public function testInvalidType(mixed $data): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate($data, new PhoneNumber());
    }

    protected function createValidator(): PhoneNumberValidator
    {
        return new PhoneNumberValidator();
    }
}
