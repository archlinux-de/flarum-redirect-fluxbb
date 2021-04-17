<?php

use ArchLinux\RedirectFluxBB\Middleware\FluxBBRedirect;
use Flarum\Extend;
use Flarum\Http\Middleware\ResolveRoute;

return [
    (new Extend\Middleware('forum'))
        ->insertBefore(ResolveRoute::class, FluxBBRedirect::class)
];
