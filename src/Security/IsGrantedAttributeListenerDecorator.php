<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Security;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;

#[AsDecorator('controller.is_granted_attribute_listener')]
final class IsGrantedAttributeListenerDecorator extends IsGrantedAttributeListener
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', -5]];
    }
}
