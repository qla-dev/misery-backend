<?php
namespace App\Http\Middleware; use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class Cors { public function handle(Request $request, Closure $next): Response { $response=$request->isMethod('OPTIONS')?response('',204):$next($request); $response->headers->set('Access-Control-Allow-Origin','*'); $response->headers->set('Access-Control-Allow-Headers','Content-Type, Accept'); $response->headers->set('Access-Control-Allow-Methods','GET, POST, PUT, PATCH, DELETE, OPTIONS'); return $response; } }
