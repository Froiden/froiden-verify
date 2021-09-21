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
        
        $IGNORE_REQUESTS = config('froiden_envato.xss_ignore_index',['widget_code']);

        if (!in_array(strtolower($request->method()), ['put', 'post'])) {
            return $next($request);
        }

        $input = $request->all();

        array_walk_recursive($input, function (&$input, $index) use ($IGNORE_REQUESTS) {
            // Ignore for widget code
            if (!empty($input) && !in_array($index, $IGNORE_REQUESTS)) {
                $input = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $input);
            }
        });

        $request->merge($input);
        return $next($request);
    }

}
