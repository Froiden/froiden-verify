<?php

namespace Froiden\Envato\Middleware;

use Closure;
use Illuminate\Http\Request;

class XSS
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!in_array(strtolower($request->method()), ['put', 'post'])) {
            return $next($request);
        }

        $input = $request->all();

        array_walk_recursive($input, function(&$input) {
            if(!empty($input)){
                $input = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $input);
            }
        });

        $request->merge($input);
        return $next($request);
    }

}
