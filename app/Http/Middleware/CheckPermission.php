<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Http\Controllers\Api\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    use ApiResponseTrait;
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!Auth::check()) {
            return $this->errorResponse(
                'Unauthenticated - Retry with valid credentials',
                [],
                401
            );
        }

        $user = Auth::user();
        
        if (!($user instanceof User) || !$user->hasPermission($permission)) {
            return $this->errorResponse(
                'Forbidden - You do not have permission to access this resource',
                [
                    'required_permission' => $permission
                ],
                403
            );
        }

        return $next($request);
    }
}