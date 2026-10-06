<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\Security;

use Evyex\SymfonyExtender\Security\IsGrantedAttributeListenerDecorator;
use Evyex\SymfonyExtender\Tests\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(IsGrantedAttributeListenerDecorator::class)]
class IsGrantedAttributeListenerDecoratorTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
    }

    public function testDecorates(): void
    {
        $service = self::getContainer()->get('controller.is_granted_attribute_listener');
        $this->assertInstanceOf(IsGrantedAttributeListenerDecorator::class, $service);
        $subscribedEvent = $service::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::CONTROLLER_ARGUMENTS, $subscribedEvent);
        $this->assertSame(-10200, $subscribedEvent[KernelEvents::CONTROLLER_ARGUMENTS][1] ?? 0);
    }

    public function testRunsAfterTheRequestPayloadIsMapped(): void
    {
        $priority = IsGrantedAttributeListenerDecorator::getSubscribedEvents()[KernelEvents::CONTROLLER_ARGUMENTS][1];
        $requestPayloadPriority = RequestPayloadValueResolver::getSubscribedEvents()[KernelEvents::CONTROLLER_ARGUMENTS][1];

        $this->assertLessThan($requestPayloadPriority, $priority);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }
}
