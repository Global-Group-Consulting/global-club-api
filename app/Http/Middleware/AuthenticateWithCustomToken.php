<?php

namespace App\Http\Middleware;

use App\Models\App;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateWithCustomToken {
  /**
   * Handle an incoming request.
   *
   * @param  Request                                        $request
   * @param  Closure(Request): (Response|RedirectResponse)  $next
   *
   * @return Response|RedirectResponse
   */
  public function handle(Request $request, Closure $next): Response|RedirectResponse {
    $headers  = $request->headers;
    $authUser = $request->get("_auth_user");
    
    if ( !$headers->has('client-secret') || !$headers->has('server-secret') || !$authUser) {
      throw new AccessDeniedHttpException("Unauthorized - Missing secrets");
    }
    
    // cerca sia il client secret che il severe secret per assicurarsi che combaciano
    $apps = App::where("secrets.client.secretKey", $headers->get('client-secret'))
      ->orWhere("secrets.server.secretKey", $headers->get('server-secret'))
      ->get();
    
    $isSameApp = false;
    
    if ($apps->count() === 1) {
      $app         = $apps[0];
      $clientMatch = $app->secrets["client"]["secretKey"] === $headers->get('client-secret');
      $serverMatch = $app->secrets["server"]["secretKey"] === $headers->get('server-secret');
      
      $isSameApp = $clientMatch || $serverMatch;
    }
    
    if ($apps->count() !== 2 && !$isSameApp) {
      throw new AccessDeniedHttpException("Unauthorized - Invalid secrets");
    }
    
    $reqApp = null;
    
    foreach ($apps as $app) {
      if ($app["secrets"]["client"]["secretKey"] === $headers->get('client-secret')) {
        $reqApp = $app;
      }
    }
    
    // Store in session the app where the request has come
    Session::put('app', $reqApp);
    $request->request->add(['app' => $reqApp->code]);
    
    $user = User::find($authUser["_id"]);
    
    Auth::login($user);
    
    return $next($request);
  }
}
