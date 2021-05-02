<?php

namespace ArchLinux\RedirectFluxBB\Middleware;

use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\UrlGenerator;
use Flarum\Post\PostRepository;
use Flarum\Tags\TagRepository;
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
    private UrlGenerator $urlGenerator;
    private PostRepository $postRepository;
    private DiscussionRepository $discussionRepository;
    private TagRepository $tagRepository;
    private UserRepository $userRepository;

    public function __construct(
        UrlGenerator $urlGenerator,
        PostRepository $postRepository,
        TagRepository $tagRepository,
        UserRepository $userRepository,
        DiscussionRepository $discussionRepository
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->postRepository = $postRepository;
        $this->tagRepository = $tagRepository;
        $this->userRepository = $userRepository;
        $this->discussionRepository = $discussionRepository;
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
        $status = 302;

        switch ($request->getUri()->getPath()) {
            case '/extern.php':
                return new Response(status: 404);
            case '/index.php':
                $status = 301;
                break;
            case '/profile.php':
                try {
                    if (isset($query['id'])) {
                        $user = $this->userRepository->findOrFail(intval($query['id']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('user', ['username' => $user->username]);
                        $status = 301;
                    }
                } catch (ModelNotFoundException $e) {
                }
                break;
            case '/search.php':
                if (isset($query['action'])) {
                    $path = $this->urlGenerator->to('forum')->route('default');
                    switch ($query['action']) {
                        case 'search':
                            if (isset($query['keywords']) && $query['keywords']) {
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
                            $path .= '?sort=newest';
                            $status = 301;
                            break;
                    }
                }
                break;
            case '/viewforum.php':
                if (isset($query['id'])) {
                    try {
                        $tag = $this->tagRepository->findOrFail(intval($query['id']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('tag', ['slug' => $tag->slug]);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                    }
                }
                break;
            case '/viewtopic.php':
                if (isset($query['id'])) {
                    try {
                        $discussion = $this->discussionRepository->findOrFail(intval($query['id']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('discussion', ['id' => $discussion->id]);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                    }
                } elseif (isset($query['pid'])) {
                    try {
                        $post = $this->postRepository->findOrFail(intval($query['pid']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('discussion', ['id' => $post->discussion_id, 'near' => $post->number]);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                    }
                }
                break;
            default:
                $path = $this->urlGenerator->to('forum')->route('default');
        }

        if (!isset($path)) {
            return new Response(status: 404);
        }

        return new RedirectResponse($path, $status);
    }
}
