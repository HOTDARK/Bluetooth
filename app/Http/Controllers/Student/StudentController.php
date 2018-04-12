<?php

namespace App\Http\Controllers\Student;

use App\Models\institute;
use App\Models\Student;
use App\Models\StuCourse;
use App\Models\CourseCheck;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    protected $guard = 'students';

    public function login(Request $request)
    {
        $allow = ['stu_code', 'password'];
        $credentials = $request->only($allow);

        if (empty($credentials['stu_code']) || empty($credentials['password']))
            return response()->json([
                'status' => 403,
                'message' => '学号密码不能为空',
                'data' => NULL
                , 403]);

        //先查询本地数据库有无学生信息
        if ($token = Auth::guard($this->getGuard())->attempt($credentials, true, true)) {
            return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $token
            ], 200);
        }

        //如果没有，核对信息后插入
        $stu_info = $this->verify($credentials['stu_code'], $credentials['password']);

        //return $stu_info;
        if ($stu_info['status'] != 200)
            return response()->json([
                'status' => 400,
                'message' => '学号与密码不匹配',
                'data' => NULL
            ], 400);

        //类似注册，将学生信息插入数据库
        $data = [
            'stu_code' => $credentials['stu_code'],
            'password' => Hash::make($credentials['password']),
            'stuName' => $stu_info['data']['name'],
            'idNum' => $stu_info['data']['idNum'],
            'stuName' => $stu_info['data']['name'],
            'gender' => $stu_info['data']['gender'],
            'stuClass' => $stu_info['data']['classNum'],
            'stuMajor' => $stu_info['data']['major'],
            'stuGrade' => $stu_info['data']['grade']
        ];
        if (!$new_user = Student::create($data))
            return response()->json([
                'status' => 400,
                'message' => '登录失败，请重新尝试登录',
                'data' => NULL
            ], 400);

        if ($token = Auth::guard($this->getGuard())->attempt($credentials, true)) {
            return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $token
            ], 200);
        } else {
            return response()->json([
                'status' => 403,
                'message' => 'failed',
                'data' => NULL
            ], 403);
        }
    }

    public function getCourse(Request $request)
    {

        $week = $request->get('week') ?: 0;

        $user = $request->get('user');

        $mCourse = new StuCourse();

        return $mCourse->getCourseJsonByCode($user['stu_code'], $week);

        /*return $this->getStuCourseByCurl($user['stu_code'], $week);*/

        /*if($stu_code = $request->get('stu_code')){
            return $this->getStuCourseByCurl($stu_code, $week);
        }*/

    }

    public function getCourseTest(Request $request)
    {

        $week = $request->get('week') ?: 0;
        $stu_code = $request->get('stuNum');
        return $this->getStuCourseByCurl($stu_code, $week);
    }

    /**
     * 获取学生的考勤情况
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAttendance(Request $request)
    {
        $user = $request->get('user');
        $info = $request->get('info');

        //获取考勤信息
        $courseCheck_m = new CourseCheck();
        $res = $courseCheck_m->stuAttendance($user, $info);
        if (!$res)
            return response()->json([
                'status' => 403,
                'message' => '获取考勤信息失败'
            ], 403);

        if (count($res) == 0) {
            return response()->json([
                'status' => 200,
                'message' => 'success',
                'stuNum' => $user['stu_code'],
                'stuName' => $user['stuName'],
                'statistics' => [],
                'data' => []
            ], 200);
        }
        //获取统计信息
        $stu_m = new Student();
        $statistics = $stu_m->getStatistics($res);
        if (!$statistics)
            return response()->json([
                'status' => 403,
                'message' => '获取考勤统计信息失败'
            ], 403);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'stuNum' => $user['stu_code'],
            'stuName' => $user['stuName'],
            'statistics' => $statistics,
            'data' => $res
        ], 200);

    }

    private function verify($code, $pass)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'http://hongyan.cqupt.edu.cn/api/verify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['stuNum' => $code, 'idNum' => $pass]));

        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output, true);
    }

    private function getStuCourseByCurl($stu_code, $week)
    {
        $post_data = [
            'stuNum' => $stu_code,
            'week' => $week
        ];
        $post_data = http_build_query($post_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://hongyan.cqupt.edu.cn/api/kebiao');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output, true);
    }


    public function putStudentCourse()
    {
        $mStuCourse = new StuCourse();
        /*$fillable = ['stuNum', 'scNum', 'stuName', 'classRoom', 'day', 'hashDay', 'hashLesson', 'lesson', 'course',
            'period', 'rawWeek', 'status', 'teacher', 'type', 'week', 'weekBegin', 'weekModel', 'weekEnd'];*/

        $stuCode = 2015211001;
        $i = 0;
        while (true) {
            $res = $this->getStuCourseByCurl($stuCode, 0);
            $courseData = $res['data'];
            $stuCode = $res['stuNum'];

            if ($res['status'] != 200) {
                break;
            }
            foreach ($courseData as $item)

                $data = [
                    'stuNum' => $stuCode,
                    'scNum' => 'ceshi',
                    'stuName' => '测试',
                    'classRoom' => $item['classroom'],
                    'day' => $item['day'],
                    'hashDay' => $item['hash_day'],
                    'hashLesson' => $item['hash_lesson'],
                    'lesson' => $item['lesson'],
                    'course' => $item['course'],
                    'period' => $item['period'],
                    'rawWeek' => $item['rawWeek'],
                    'teacher' => $item['teacher'],
                    'type' => $item['type'],
                    'week' => json_encode($item['week']),
                    'weekBegin' => $item['weekBegin'],
                    'weekEnd' => $item['weekEnd'],
                    'weekModel' => $item['weekModel']
                ];
            $mStuCourse::create($data);
            $i++;
        }
        return $i;
    }


    /*function putCourseJson()
    {
        $stuCode = 2015211634;

        $mStuCourse = new StuCourse();
        while (true) {
            $i = 0;
            while ($i <= 16) {
                $courses = $this->getStuCourseByCurl($stuCode,$i );
                $courseJson = json_encode($courses);

                try{
                    if($courses['status']!=200){
                        break;
                    }
                }catch(\Exception $exception){
                    return $courseJson;
                }


                $need = ['stuCode' => $stuCode, 'courseJson' => $courseJson,
                    'week' => $i ];
                $mStuCourse::create($need);

                //return $need;
                $i++;
            }
            $stuCode++;


        }
    }*/

    /*function test(){
       $mInstitute  = new institute();
        $mInstitute->createExample();
    }*/
}