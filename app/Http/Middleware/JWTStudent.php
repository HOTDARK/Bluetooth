<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Exception;
use Illuminate\Support\Facades\Auth;

class JWTStudent
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
//        if (! $user = Auth::guard('students')->authenticate()) {
//            return response()->json([
//                'status' => 404,
//                'message' => 'user_not_found'
//            ], 404);
//        }

        try {
            if (!$user = Auth::guard('students')->authenticate())
                return response()->json([
                    'status' => 404,
                    'message' => 'user_not_found'
                ], 404);
        } catch (Exception $e) {

            $stu_code = $request->get("stu_code");

            if ($stu_code == null) {
                return response()->json([
                    'status' => 404,
                    'message' => 'here is some wrong'
                ], 404);
            }else{
               $student = new Student();
               $user  = $student->where(['stu_code'=>$stu_code])->select()->first();
                $request->attributes->add(compact('user'));
            }

        }

        $request->attributes->add(compact('user'));

        return $next($request);
    }
}
