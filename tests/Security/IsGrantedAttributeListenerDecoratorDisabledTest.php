<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\Security;

use Evyex\SymfonyExtender\Security\IsGrantedAttributeListenerDecorator;
use Evyex\SymfonyExtender\SymfonyExtenderBundle;
use Evyex\SymfonyExtender\Tests\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;

/**
 * @internal
 */
#[CoversClass(SymfonyExtenderBundle::class)]
class IsGrantedAttributeListenerDecoratorDisabledTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
    }

    public function testDecoratorIsNotRegisteredWhenDisabled(): void
    {
        $service = self::getContainer()->get('controller.is_granted_attribute_listener');

        $this->assertNotInstanceOf(IsGrantedAttributeListenerDecorator::class, $service);
        $this->assertInstanceOf(IsGrantedAttributeListener::class, $service);
    }

    public function testOriginalListenerPriorityIsRestoredWhenDecoratorDisabled(): void
    {
        $service = self::getContainer()->get('controller.is_granted_attribute_listener');

        $subscribedEvents = $service::getSubscribedEvents();
        $priority = $subscribedEvents[KernelEvents::CONTROLLER_ARGUMENTS][1] ?? 0;

        $this->assertSame(20, $priority);
    }

    protected static function getKernelClass(): string
    {
        return DisabledDecoratorKernel::class;
    }
}

final class DisabledDecoratorKernel extends TestKernel
{
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/symfony_extender_disabled_decorator_test/cache/'.$this->environment;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('symfony_extender', [
                SymfonyExtenderBundle::SECTION_IS_GRANTED_LISTENER => [
                    SymfonyExtenderBundle::KEY_ENABLED => false,
                ],
            ]);
        });
    }
}
