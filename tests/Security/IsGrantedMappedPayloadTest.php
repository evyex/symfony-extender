<?php

declare(strict_types=1);

namespace Evyex\SymfonyExtender\Tests\Security;

use Evyex\SymfonyExtender\Security\IsGrantedAttributeListenerDecorator;
use Evyex\SymfonyExtender\Tests\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @internal
 */
#[CoversClass(IsGrantedAttributeListenerDecorator::class)]
class IsGrantedMappedPayloadTest extends KernelTestCase
{
    #[TestWith([IsGrantedMappedPayloadController::class])]
    #[TestWith([IsGrantedMappedPayloadExpressionController::class])]
    public function testVoterReceivesTheMappedPayload(string $controller): void
    {
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"title": "allowed"}');
        $request->attributes->set('_controller', $controller);

        $response = self::bootKernel()->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('allowed', $response->getContent());
    }

    protected static function getKernelClass(): string
    {
        return MappedPayloadKernel::class;
    }
}

final class MappedPayloadKernel extends TestKernel
{
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/symfony_extender_mapped_payload_test/cache/'.$this->environment;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->register(IsGrantedMappedPayloadVoter::class)->addTag('security.voter');
        });
    }
}

final class IsGrantedMappedPayload
{
    public function __construct(
        public string $title,
    ) {}
}

final class IsGrantedMappedPayloadController
{
    #[IsGranted('POST_CREATE', 'post')]
    public function __invoke(#[MapRequestPayload] IsGrantedMappedPayload $post): Response
    {
        return new Response($post->title);
    }
}

final class IsGrantedMappedPayloadExpressionController
{
    #[IsGranted('POST_CREATE', new Expression('args["post"]'))]
    public function __invoke(#[MapRequestPayload] IsGrantedMappedPayload $post): Response
    {
        return new Response($post->title);
    }
}

/**
 * @extends Voter<string, mixed>
 */
final class IsGrantedMappedPayloadVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return 'POST_CREATE' === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $subject instanceof IsGrantedMappedPayload && 'allowed' === $subject->title;
    }
}
