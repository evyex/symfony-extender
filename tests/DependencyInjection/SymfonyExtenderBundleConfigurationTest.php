<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\DependencyInjection;

use Evyex\SymfonyExtender\SymfonyExtenderBundle;
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

    public function testIsGrantedListenerEnabledByDefault(): void
    {
        $config = $this->processConfig([]);

        $this->assertTrue($config['is_granted_listener']['enabled']);
    }

    public function testIsGrantedListenerCanBeDisabled(): void
    {
        $config = $this->processConfig([
            'is_granted_listener' => ['enabled' => false],
        ]);

        $this->assertFalse($config['is_granted_listener']['enabled']);
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
