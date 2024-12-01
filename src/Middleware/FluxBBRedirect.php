<?php

namespace ArchLinux\RedirectFluxBB\Middleware;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Post\PostRepository;
use Flarum\Tags\TagRepository;
use Flarum\User\User;
use Flarum\User\UserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FluxBBRedirect implements MiddlewareInterface
{
    public function __construct(
        private readonly UrlGenerator $urlGenerator,
        private readonly PostRepository $postRepository,
        private readonly TagRepository $tagRepository,
        private readonly UserRepository $userRepository,
        private readonly DiscussionRepository $discussionRepository,
        private readonly SlugManager $slugManager
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            in_array($request->getMethod(), ['GET', 'HEAD'])
            && preg_match('#^/\w+\.php$#', $request->getUri()->getPath())
        ) {
            return $this->handleRequest($request, $handler);
        }

        return $handler->handle($request);
    }

    private function handleRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $query = $request->getQueryParams();
        $path = $this->urlGenerator->to('forum')->route('default');
        $status = 302;

        switch ($request->getUri()->getPath()) {
            case '/extern.php':
                $status = 404;
                break;
            case '/index.php':
                $status = 301;
                break;
            case '/profile.php':
                try {
                    if (isset($query['id']) && is_string($query['id'])) {
                        $user = $this->userRepository->findOrFail(intval($query['id']));
                        $path = $this->urlGenerator->to('forum')->route(
                            'user',
                            ['username' => $this->slugManager->forResource(User::class)->toSlug($user)]
                        );
                        $status = 301;
                    }
                } catch (ModelNotFoundException $e) {
                    $status = 404;
                }
                break;
            case '/search.php':
                if (isset($query['action']) && is_string($query['action'])) {
                    switch ($query['action']) {
                        case 'search':
                            if (isset($query['keywords']) && is_string($query['keywords']) && $query['keywords']) {
                                $path .= '?q=' . urlencode($query['keywords']);
                                $status = 301;
                            }
                            break;
                        case 'show_replies':
                            $status = 301;
                            break;
                        case 'show_new':
                        case 'show_recent':
                        case 'show_unanswered':
                        case 'show_24h':
                            $path .= '?sort=newest';
                            $status = 301;
                            break;
                    }
                }
                break;
            case '/viewforum.php':
                if (isset($query['id']) && is_string($query['id'])) {
                    try {
                        $tag = $this->tagRepository->findOrFail(intval($query['id']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('tag', ['slug' => $tag->slug]);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        // Implicitly redirect to forum index
                    }
                }
                break;
            case '/viewtopic.php':
                if (isset($query['id']) && is_string($query['id'])) {
                    try {
                        $discussion = $this->discussionRepository->findOrFail(intval($query['id']));
                        $parameters = ['id' => $this->slugManager->forResource(Discussion::class)->toSlug($discussion)];
                        if (isset($query['p']) && is_string($query['p']) && intval($query['p']) > 1) {
                            // FluxBB's default page size is 25 posts
                            $parameters['near'] = (string) (((intval($query['p']) - 1) * 25) + 1);
                        }
                        $path = $this->urlGenerator->to('forum')->route('discussion', $parameters);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                } elseif (isset($query['pid']) && is_string($query['pid'])) {
                    try {
                        $post = $this->postRepository->findOrFail(intval($query['pid']));
                        $discussion = $this->discussionRepository->findOrFail($post->discussion_id);
                        $path = $this->urlGenerator->to('forum')
                            ->route(
                                'discussion',
                                [
                                    'id' => $this->slugManager->forResource(Discussion::class)->toSlug($discussion),
                                    'near' => (string) $post->number
                                ]
                            );
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                }
                break;
            case '/post.php':
                if (isset($query['qid']) && is_string($query['qid'])) {
                    try {
                        $post = $this->postRepository->findOrFail(intval($query['qid']));
                        $discussion = $this->discussionRepository->findOrFail($post->discussion_id);
                        $path = $this->urlGenerator->to('forum')
                            ->route(
                                'discussion',
                                [
                                    'id' => $this->slugManager->forResource(Discussion::class)->toSlug($discussion),
                                    'near' => (string) $post->number
                                ]
                            );
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                } elseif (isset($query['tid']) && is_string($query['tid'])) {
                    try {
                        $discussion = $this->discussionRepository->findOrFail(intval($query['tid']));
                        $path = $this->urlGenerator->to('forum')
                            ->route(
                                'discussion',
                                [
                                    'id' => $this->slugManager->forResource(Discussion::class)->toSlug($discussion)
                                ]
                            );
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                } elseif (isset($query['fid']) && is_string($query['fid'])) {
                    try {
                        $tag = $this->tagRepository->findOrFail(intval($query['fid']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('tag', ['slug' => $tag->slug]);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        // Implicitly redirect to forum index
                    }
                }
                break;
            case '/edit.php':
                if (isset($query['id']) && is_string($query['id'])) {
                    try {
                        $post = $this->postRepository->findOrFail(intval($query['id']));
                        $discussion = $this->discussionRepository->findOrFail($post->discussion_id);
                        $path = $this->urlGenerator->to('forum')
                            ->route(
                                'discussion',
                                [
                                    'id' => $this->slugManager->forResource(Discussion::class)->toSlug($discussion),
                                    'near' => (string) $post->number
                                ]
                            );
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                }
                break;
            case '/moderate.php':
                if (isset($query['tid']) && is_string($query['tid'])) {
                    try {
                        $discussion = $this->discussionRepository->findOrFail(intval($query['tid']));
                        $path = $this->urlGenerator->to('forum')
                            ->route(
                                'discussion',
                                [
                                    'id' => $this->slugManager->forResource(Discussion::class)->toSlug($discussion)
                                ]
                            );
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                } elseif (isset($query['fid']) && is_string($query['fid'])) {
                    try {
                        $tag = $this->tagRepository->findOrFail(intval($query['fid']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('tag', ['slug' => $tag->slug]);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                }
                break;
            case '/misc.php':
                try {
                    if (isset($query['email']) && is_string($query['email'])) {
                        $user = $this->userRepository->findOrFail(intval($query['email']));
                        $path = $this->urlGenerator->to('forum')->route(
                            'user',
                            ['username' => $this->slugManager->forResource(User::class)->toSlug($user)]
                        );
                        $status = 301;
                    }
                } catch (ModelNotFoundException $e) {
                    $status = 404;
                }
                break;
        }

        if ($status === 404) {
            return new Response(status: 404);
        }

        return new RedirectResponse($path, $status);
    }
}
