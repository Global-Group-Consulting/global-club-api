<?php

namespace App\Http\Middleware;

use App\Models\CronUser;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class AuthenticateWithCronUser extends Middleware {
  
  /**
   * Handle an incoming request.
   *
   * @param  Request                                                                                           $request
   * @param  Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
   *
   * @return mixed
   * @throws AuthenticationException
   */
  public function handle($request, Closure $next, ...$guards) {
    $userName     = $_SERVER['PHP_AUTH_USER'] ?? '';
    $userPassword = $_SERVER['PHP_AUTH_PW'] ?? '';
    
    $user = CronUser::where(["username" => $userName])->first();
    
    if ( !$user || !Hash::check($userPassword, $user->password)) {
      throw new AuthenticationException('Unauthorized.');
    }
    
    Auth::login($user);
    
    return $next($request);
  }
}
