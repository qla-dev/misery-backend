<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = (string) config('cms.username');
        $expectedPassword = (string) config('cms.password');
        $valid = hash_equals($expectedUser, (string) $request->getUser())
            && hash_equals($expectedPassword, (string) $request->getPassword());

        if (! $valid) {
            return response('CMS authentication required.', 401)
                ->header('WWW-Authenticate', 'Basic realm="Misery Index CMS"');
        }

        return $next($request);
    }
}
