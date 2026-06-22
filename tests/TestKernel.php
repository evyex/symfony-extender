<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Configuration as ORMConfiguration;
use Evyex\SymfonyExtender\SymfonyExtenderBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new SymfonyExtenderBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(
            function (ContainerBuilder $container) {
                $container->loadFromExtension(
                    'framework',
                    [
                        'test' => true,
                        'http_method_override' => false,
                        'handle_all_throwables' => true,
                        'router' => [
                            'resource' => false,
                            'type' => 'service',
                        ],
                        'validation' => [
                            'email_validation_mode' => 'html5',
                        ],
                        'php_errors' => [
                            'log' => true,
                        ],
                        'property_info' => [
                            'with_constructor_extractor' => false,
                        ],
                    ]
                );

                $container->loadFromExtension(
                    'doctrine',
                    [
                        'dbal' => [
                            'driver' => 'pdo_sqlite',
                            'path' => ':memory:',
                        ],
                        'orm' => [
                            'auto_mapping' => false,
                            'controller_resolver' => ['auto_mapping' => false],
                            ...(
                                method_exists(ORMConfiguration::class, 'enableNativeLazyObjects')
                            ? ['enable_native_lazy_objects' => PHP_VERSION_ID >= 80400] : []
                            ),
                        ],
                    ]
                );

                $container->loadFromExtension(
                    'security',
                    [
                        'providers' => [
                            'test_provider' => [
                                'memory' => [
                                    'users' => [],
                                ],
                            ],
                        ],
                        'firewalls' => [
                            'main' => [
                                'provider' => 'test_provider',
                            ],
                        ],
                    ]
                );
            }
        );
    }
}
