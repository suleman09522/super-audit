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

            // Set the current request payload (if enabled)
            if (config('super-audit.store_request_payload', true)) {
                $hiddenFields = config('super-audit.hidden_payload_fields', ['password', 'password_confirmation', 'secret', '_token']);
                $input = $request->all();

                foreach ($hiddenFields as $field) {
                    if (is_array($input) && array_key_exists($field, $input)) {
                        $input[$field] = '********';
                    }
                }

                array_walk_recursive($input, function (&$value) {
                    if ($value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                        $value = '[File: ' . $value->getClientOriginalName() . ']';
                    }
                });

                $payloadJson = !empty($input) ? json_encode($input, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
                DB::statement("SET @current_request_payload = ?", [$payloadJson]);
            } else {
                DB::statement("SET @current_request_payload = NULL");
            }
        } catch (\Exception $e) {
            // Log the error but don't prevent the request from continuing
            logger()->error('Super Audit: Failed to set audit variables', [
                'error' => $e->getMessage()
            ]);
        }

        return $next($request);
    }
}
