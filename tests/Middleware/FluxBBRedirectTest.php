<?php

namespace ArchLinux\RedirectFluxBB\Test\Middleware;

use ArchLinux\RedirectFluxBB\Middleware\FluxBBRedirect;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\UrlGenerator;
use Flarum\Post\PostRepository;
use Flarum\Tags\TagRepository;
use Flarum\User\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FluxBBRedirectTest extends TestCase
{
    /** @var RouteCollectionUrlGenerator|MockObject */
    private RouteCollectionUrlGenerator|MockObject $routeCollectionUrlGenerator;

    /** @var UriInterface|MockObject */
    private UriInterface|MockObject $requestUri;

    private FluxBBRedirect $fluxBBRedirect;

    /** @var ServerRequestInterface|MockObject */
    private ServerRequestInterface|MockObject $request;

    /** @var RequestHandlerInterface|MockObject */
    private RequestHandlerInterface|MockObject $requestHandler;

    public function setUp(): void
    {
        $urlGenerator = $this->createMock(UrlGenerator::class);
        $postRepository = $this->createMock(PostRepository::class);
        $tagRepository = $this->createMock(TagRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $discussionRepository = $this->createMock(DiscussionRepository::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
        $this->requestUri = $this->createMock(UriInterface::class);
        $this->routeCollectionUrlGenerator = $this->createMock(RouteCollectionUrlGenerator::class);

        $discussionRepository
            ->expects($this->any())
            ->method('findOrFail')
            ->willReturn(
                new class {
                    public int $id = 123;
                }
            );

        $postRepository
            ->expects($this->any())
            ->method('findOrFail')
            ->willReturn(
                new class {
                    public int $discussion_id = 456;
                    public int $number = 789;
                }
            );

        $tagRepository
            ->expects($this->any())
            ->method('findOrFail')
            ->willReturn(
                new class {
                    public string $slug = 'foo-tag';
                }
            );

        $userRepository
            ->expects($this->any())
            ->method('findOrFail')
            ->willReturn(
                new class {
                    public string $username = 'foo-username';
                }
            );

        $urlGenerator
            ->expects($this->any())
            ->method('to')
            ->willReturn($this->routeCollectionUrlGenerator);

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn('GET');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getUri')
            ->willReturn($this->requestUri);

        $this->fluxBBRedirect = new FluxBBRedirect(
            $urlGenerator,
            $postRepository,
            $tagRepository,
            $userRepository,
            $discussionRepository
        );
    }

    private function assertRedirect(ResponseInterface $response, string $expectedUrl, int $code = 301): void
    {
        $this->assertEquals($code, $response->getStatusCode());
        $this->assertCount(1, $response->getHeader('Location'));
        $this->assertEquals($expectedUrl, $response->getHeader('Location')[0]);
    }

    public function testRedirectForums(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->atLeastOnce())
            ->method('route')
            ->with('tag', ['slug' => 'foo-tag'])
            ->willReturn('/new-url');

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/viewforum.php');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getQueryParams')
            ->willReturn(['id' => 123]);

        $response = $this->fluxBBRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    public function testRedirectTopics(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->atLeastOnce())
            ->method('route')
            ->with('discussion', ['id' => 123])
            ->willReturn('/new-url');

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/viewtopic.php');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getQueryParams')
            ->willReturn(['id' => 123]);

        $response = $this->fluxBBRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    public function testRedirectPosts(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->atLeastOnce())
            ->method('route')
            ->with('discussion', ['id' => 456, 'near' => 789])
            ->willReturn('/new-url');

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/viewtopic.php');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getQueryParams')
            ->willReturn(['pid' => 789]);

        $response = $this->fluxBBRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    public function testRedirectProfiles(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->atLeastOnce())
            ->method('route')
            ->with('user', ['username' => 'foo-username'])
            ->willReturn('/new-url');

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/profile.php');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getQueryParams')
            ->willReturn(['id' => 1011]);

        $response = $this->fluxBBRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    public function testRedirectSearches(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->atLeastOnce())
            ->method('route')
            ->with('default')
            ->willReturn('/new-url');

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/search.php');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getQueryParams')
            ->willReturn(['action' => 'search', 'keywords' => 'bar']);

        $response = $this->fluxBBRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url?q=bar');
    }

    public function testRedirectFallback(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->atLeastOnce())
            ->method('route')
            ->with('default')
            ->willReturn('/new-url');

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/foo.php');
        $response = $this->fluxBBRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url', 302);
    }

    public function testSendNotFoundForFeeds(): void
    {
        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/extern.php');
        $response = $this->fluxBBRedirect->process($this->request, $this->requestHandler);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testIgnoreUnknownRequests(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->requestHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($response);

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/foo');
        $handledResponse = $this->fluxBBRedirect->process($this->request, $this->requestHandler);

        $this->assertSame($response, $handledResponse);
    }
}
