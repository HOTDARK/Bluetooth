<?php

namespace App\Http\Middleware;

use App\Models\Teacher;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class JWTTeacher
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
//        if (! $user = Auth::guard('teachers')->authenticate()) {
//            return response()->json([
//                'status' => 404,
//                'message' => 'user_not_found'
//            ], 404);
//        }

        try {
            if (!$user = Auth::guard('teachers')->authenticate())
                return response()->json([
                    'status' => 404,
                    'message' => 'user_not_found'
                ], 404);


        } catch (AuthenticationException $e) {

            if($trid = $request->get('trid')){
                $teacher = new Teacher();
                if($user = $teacher->where(['trid' => $trid])->select()->first()){
                    $request->attributes->add(compact('user'));
                }else{
                    return response()->json([
                        'status' => 404,
                        'message' => 'trid'.$trid
                    ], 404);
                }
            }else {
                return response()->json([
                    'status' => 404,
                    'message' => '无trid is wrong'
                ], 404);
            }
        }

        $request->attributes->add(compact('user'));
        return $next($request);
    }
}
