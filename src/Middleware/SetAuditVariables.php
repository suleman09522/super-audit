<?php

namespace SuperAudit\SuperAudit\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SetAuditVariables Middleware
 * 
 * Sets MySQL session variables for the current user and URL.
 * These variables are used by database triggers to track who made changes.
 */
class SetAuditVariables
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Set the current user ID (null if not authenticated)
            DB::statement("SET @current_user_id = ?", [Auth::id()]);
            
            // Set the current URL
            DB::statement("SET @current_url = ?", [$request->fullUrl()]);
        } catch (\Exception $e) {
            // Log the error but don't prevent the request from continuing
            logger()->error('Super Audit: Failed to set audit variables', [
                'error' => $e->getMessage()
            ]);
        }

        return $next($request);
    }
}
