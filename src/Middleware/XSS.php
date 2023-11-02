<?php

namespace Froiden\Envato\Middleware;

use Closure;
use Illuminate\Http\Request;

class XSS
{

    /**
     * Handle incoming request.
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

        $IGNORE_REQUESTS = config('xss_ignore', ['widget_code']);

        // Check this class \Nwidart\Modules\Facades\Module  if calss exists then only run this code
        if (class_exists('\Nwidart\Modules\Facades\Module')) {
            $currentModule = \Nwidart\Modules\Facades\Module::allEnabled();

            // Get all module xss_ignore config and merge it
            foreach ($currentModule as $module) {
                $moduleConfig = config(strtolower($module->getName()) . '::xss_ignore', []);
                // Merge all module config and remove duplicate
                $IGNORE_REQUESTS = array_unique(array_merge($IGNORE_REQUESTS, $moduleConfig));
            }
        }

        array_walk_recursive($input, function (&$input, $index) use ($IGNORE_REQUESTS) {
            // Ignore for widget code
            if (!empty($input) && !in_array($index, $IGNORE_REQUESTS)) {
                $input = preg_replace('#<script(.*?)>(.*?)</script>#is', '', strip_tags($input));
            }
        });

        $request->merge($input);
        return $next($request);
    }

}
