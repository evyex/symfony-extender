<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\DependencyInjection;

use Evyex\SymfonyExtender\SymfonyExtenderBundle;
use Evyex\SymfonyExtender\Validator\PhoneNumberValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(SymfonyExtenderBundle::class)]
class SymfonyExtenderBundleConfigurationTest extends TestCase
{
    public function testDefaultConfigurationHasDefaultLimit20(): void
    {
        $config = $this->processConfig([]);

        $this->assertSame(20, $config['entity_collection']['default_limit']);
    }

    public function testCustomDefaultLimitIsApplied(): void
    {
        $config = $this->processConfig([
            'entity_collection' => ['default_limit' => 50],
        ]);

        $this->assertSame(50, $config['entity_collection']['default_limit']);
    }

    public function testDefaultLimitMustBeAtLeastOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'entity_collection' => ['default_limit' => 0],
        ]);
    }

    public function testPhoneNumberDefaultsAreApplied(): void
    {
        $config = $this->processConfig([]);

        $this->assertTrue($config['phone_number']['clean_string']);
        $this->assertSame(PhoneNumberValidator::DEFAULT_PATTERN, $config['phone_number']['pattern']);
    }

    public function testPhoneNumberCleanStringCanBeDisabled(): void
    {
        $config = $this->processConfig([
            'phone_number' => ['clean_string' => false],
        ]);

        $this->assertFalse($config['phone_number']['clean_string']);
    }

    public function testPhoneNumberCustomPatternIsApplied(): void
    {
        $pattern = '/^\+380[0-9]{9}$/';
        $config = $this->processConfig([
            'phone_number' => ['pattern' => $pattern],
        ]);

        $this->assertSame($pattern, $config['phone_number']['pattern']);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function processConfig(array $values): array
    {
        $bundle = new SymfonyExtenderBundle();
        $extension = $bundle->getContainerExtension();
        \assert(null !== $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        \assert(null !== $configuration);

        return (new Processor())->processConfiguration($configuration, [$values]);
    }
}
