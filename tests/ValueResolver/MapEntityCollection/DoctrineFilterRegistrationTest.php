<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\ValueResolver\MapEntityCollection;

use Doctrine\ORM\QueryBuilder;
use Evyex\SymfonyExtender\Tests\TestKernel;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\DoctrineFilterInterface;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\EntityCollectionValueResolver;
use Evyex\SymfonyExtender\ValueResolver\MapEntityCollection\MapEntityCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityCollectionValueResolver::class)]
class DoctrineFilterRegistrationTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
    }

    public function testDoctrineFilterIsRegisteredInResolverLocator(): void
    {
        $resolver = self::getContainer()->get(EntityCollectionValueResolver::class);
        $filterLocator = $this->extractFilterLocator($resolver);

        $this->assertTrue($filterLocator->has(TestDoctrineFilter::class));
        $this->assertInstanceOf(TestDoctrineFilter::class, $filterLocator->get(TestDoctrineFilter::class));
    }

    protected static function getKernelClass(): string
    {
        return DoctrineFilterRegistrationKernel::class;
    }

    private function extractFilterLocator(EntityCollectionValueResolver $resolver): ContainerInterface
    {
        $reflection = new \ReflectionClass($resolver);
        $property = $reflection->getProperty('container');

        return $property->getValue($resolver);
    }
}

final class DoctrineFilterRegistrationKernel extends TestKernel
{
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/symfony_extender_filter_registration_test/cache/'.$this->environment;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->register(TestDoctrineFilter::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
            ;
        });
    }
}

final class TestDoctrineFilter implements DoctrineFilterInterface
{
    public function applyFilter(
        QueryBuilder $queryBuilder,
        MapEntityCollection $attribute,
        Request $request,
        ?object $queryStringObject = null
    ): void {}
}
