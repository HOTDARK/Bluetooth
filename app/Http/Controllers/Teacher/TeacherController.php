<?php

namespace App\Http\Controllers\Teacher;

use App\Models\institute;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TCourse;
use App\Models\SList;
use App\Models\CourseCheck;
use App\Jobs\checkAttendance;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use PhpParser\Node\Expr\Array_;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class TeacherController extends Controller
{
    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    protected $guard = 'teachers';

    public function toLogin(Request $request)
    {
        return view('login');
    }

    public function index(Request $request)
    {
        return view('index');
    }

    /*登录*/
    public function login(Request $request)
    {
        $credentials = $request->only('trid', 'password');

        if (empty($credentials['trid']) || empty($credentials['password']))
            return response()->json([
                'status' => 403,
                'message' => '教师ID与密码不能为空',
                'data' => NULL
            ], 403);

        if ($token = Auth::guard(/*$this->getGuard()*/
            'teachers')->attempt($credentials, true)) {
            return response()->json([
                'status' => 200,
                'message' => 'success',
                'data' => $token
            ], 200);
        } else {
            return response()->json([
                'status' => 403,
                'message' => 'failed',
                'data' => $token
            ], 403);
        }

    }

    public function getCourse(Request $request)
    {
        $year = getCourseYear();
        $user = $request->get('user');
        $week = $request->get('week') ?: 0;

        $course_m = new TCourse();
        if (!$courseList = $course_m->getCourse($user['trid'], $year)) {
            return response()->json([
                'status' => 403,
                'message' => 'failed',
            ], 403);
        }

        if ($week < 0) {
            return response()->json([
                'status' => 403,
                'message' => 'week须为非负整数'
            ], 403);
        }

        //返回指定周数的课表
        if ($week != 0) {
            foreach ($courseList as $key => $value) {
                $weeks = explode(',', $value['week']);
                $temp = [];
                if (!in_array($week, $weeks))
                    unset($courseList[$key]);
                else {
                    for ($i = 0; $i < count($weeks); $i++) {
                        array_push($temp, intval($weeks[$i]));
                    }
                }
                $value['week'] = $temp;
                $value['hash_day'] = intval($value['hash_day']);
                $value['hash_lesson'] = intval($value['hash_lesson']);
                $value['begin_lesson'] = intval($value['begin_lesson']);
            }
            $courseList = json_decode($courseList, true);
            $arr_courseList = array_values($courseList);
        } else {
            foreach ($courseList as $key => $value) {
                $weeks = explode(',', $value['week']);
                $temp = [];
                for ($i = 0; $i < count($weeks); $i++) {
                    array_push($temp, intval($weeks[$i]));
                }
                $value['week'] = $temp;
                $value['hash_day'] = intval($value['hash_day']);
                $value['hash_lesson'] = intval($value['hash_lesson']);
                $value['begin_lesson'] = intval($value['begin_lesson']);
            }
            $arr_courseList = $courseList;
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'version' => env('APP_VERSION'),
            'data' => $arr_courseList,
            'nowWeek' => getNowWeek(),
            'year' => $year
        ], 200);
    }

    public function getStuListByJxbID(Request $request)
    {
        //
        $jxbID = $request->get('jxbID');
        $user = $request->get('user');

        if (!$res = SList::where('jxbID', $jxbID)->select('stu_list')->first())
            return response()->json([
                'status' => 403,
                'message' => 'failed',
            ], 403);

        @ $List = unserialize($res['stu_list']);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'trid' => $user['trid'],
            'data_num' => count($List),
            'data' => $List
        ], 200);
    }



    public function checkAttendance(Request $request)
    {
        //
        $user = $request->get('user');
        $need = ['week', 'hash_day', 'hash_lesson', 'jxbID', 'status'];
        $info = $request->only($need);
        $week = $info['week'];

        $data = explode(',', $info['status']);

        $condition = [
            'trid' => $user['trid'],
            'jxbID' => $info['jxbID'],
            'hash_day' => $info['hash_day'],
            'hash_lesson' => $info['hash_lesson']
        ];

        $res1 = TCourse::where($condition)->select(['tcid', 'scNum', 'course'])->first();
        if (empty($res1['tcid']))
            return response()->json([
                'status' => 404,
                'message' => '这节课不存在'
            ], 404);

        if (!$res2 = SList::where('jxbID', $info['jxbID'])->select('stu_list')->first())
            return response()->json([
                'status' => 400,
                'message' => '考勤失败'
            ], 400);

        $list = unserialize($res2['stu_list']);
        if (count($list) != count($data))
            return response()->json([
                'status' => 403,
                'message' => '状态参数数量与人数不一致'
            ], 403);

        $year = getCourseYear();
        $month = getNowMonth();
        $i = 0;

        foreach ($list as $key => $value) {
            $job_data = [
                'stuNum' => $value['stuNum'],
                'stuName' => $value['name'],
                'trid' => $user['trid'],
                'jxbID' => $info['jxbID'],
                'course' => $res1['course'],
                'year' => $year,
                'month' => $month,
                'week' => $week,
                'hash_day' => $info['hash_day'],
                'hash_lesson' => $info['hash_lesson'],
                'major' => $value['major'],
                'grade' => $value['grade'],
                'class' => $value['calss'],  //抓取数据临时更改如此
                'scNum' => $res1['scNum'],
                'status' => $data[$i]
            ];
            $i++;
            $this->dispatch(new checkAttendance($job_data));
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
        ], 200);
    }

    public function getAttendance(Request $request)
    {
        $info = $request->get('info');

        //获取所有符合条件的考勤信息
        $courseCheck_m = new CourseCheck();
        $res = $courseCheck_m->teaAttendance($info);
        if (!$res)
            return response()->json([
                'status' => 403,
                'message' => '获取考勤信息失败'
            ], 403);

        //如果res结果为空
        if (count($res) == 0) {
            return response()->json([
                'status' => 200,
                'message' => 'success',
                'data_num' => 0,
                'data' => []
            ], 200);
        }
        //获取该教学班的人数
        $list = SList::where(['jxbID' => $info['jxbID']])->select('stu_list')->first();
        $stu_list = unserialize($list['stu_list']);
        $stu_num = count($stu_list);

        //将考勤信息统计
        $teacher_m = new Teacher();
        $statistics = $teacher_m->getStatistics($res, $stu_num, $info['status']);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data_num' => count($statistics),
            'data' => $statistics
        ], 200);

    }

    public function getCourseList(Request $request)
    {
        $year = getCourseYear();
        $user = $request->get('user');
        $week = 0;

        $course_m = new TCourse();
        if (!$courseList = $course_m->getCourse($user['trid'], $year)) {
            return response()->json([
                'status' => 400,
                'message' => 'failed',
                'data' => NULL,
            ], 400);
        }

        //简化课程信息，将不同的课程编号与之对应的课程名字返回
        $res = [];
        foreach ($courseList as $key => $value) {
            $res[$value['scNum']]['course'] = $value['course'];
            $res[$value['scNum']]['jxbID'] = [];
            array_push($res[$value['scNum']]['jxbID'], $value['jxbID']);
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $res
        ], 200);
    }

    public function getStatistics(Request $request)
    {
        $year = getCourseYear();
        $week = getNowWeek();
        $user = $request->get('user');
        $hash_day = date('N', time()) - 1;

        $condition = [
            'year' => $year,
            'trid' => $user['trid'],
            'week' => $week
        ];

        $need = ['hash_day', 'status'];

        if (!$res = CourseCheck::where($condition)->select($need)->get())
            return response()->json([
                'status' => 400,
                'message' => 'failed',
                'data' => NULL
            ], 400);

        $count_week_sign = 0;
        $count_day_sign = 0;
        $count_week_absence = 0;
        $count_day_absence = 0;
        foreach ($res as $key => $value) {
            if ($value['status'] == env('SIGN')) {
                $count_week_sign++;
                if ($value['hash_day'] == $hash_day)
                    $count_day_sign++;
            } elseif ($value['status'] == env('ABSENCE')) {
                $count_week_absence++;
                if ($value['hash_day'] == $hash_day)
                    $count_day_absence++;
            }
        }

        $data = [
            'week_sign' => $count_week_sign,
            'week_absence' => $count_week_absence,
            'day_sign' => $count_day_sign,
            'day_absence' => $count_day_absence
        ];

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $data
        ], 200);
    }

    public function getWeekStatistics(Request $request)
    {
        $user = $request->get('user');
        $info = $request->get('info');

        $course_check_m = new CourseCheck();
        if (!$res = $course_check_m->getWeekStatistics($info, $user['trid']))
            return response()->json([
                'status' => 400,
                'message' => 'failed'
            ], 400);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $res
        ], 200);
    }

    public function getMonthStatistics(Request $request)
    {
        $user = $request->get('user');
        $month = getNowMonth();

        $course_check_m = new CourseCheck();
        if (!$res = $course_check_m->getMonthStatistics($month, $user['trid']))
            return response()->json([
                'status' => 400,
                'message' => 'failed'
            ], 400);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $res
        ], 200);
    }

    public function getTermStatistics(Request $request)
    {
        $user = $request->get('user');
        $info = $request->get('info');

        $course_check_m = new CourseCheck();
        if (!$res = $course_check_m->getTermStatistics($info, $user['trid']))
            return response()->json([
                'status' => 400,
                'message' => 'failed'
            ], 400);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $res
        ], 200);
    }

    public function getStuList(Request $request)
    {
        $user = $request->get('user');
        $info = $request->get('info');

        $course_check_m = new CourseCheck();
        $res = $course_check_m->getStuList($info, $user['trid']);

        if (!$res)
            return response()->json([
                'status' => 400,
                'message' => 'failed',
            ], 400);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $res
        ], 200);
    }

    public function getStuListExcel(Request $request)
    {
        $user = $request->get('user');
        $info = $request->get('info');
        $info['per_page'] = 9999;

        $course_check_m = new CourseCheck();
        $res = $course_check_m->getStuList($info, $user['trid']);

        if (!$res)
            return response()->json([
                'status' => 400,
                'message' => 'failed',
                'data' => NULL
            ], 400);

        $need = [
            'stuNum' => '学号',
            'stuName' => '姓名',
            'class' => '班级',
            'status' => '考勤状态',
            'created_at' => '考勤时间'
        ];
        $status = [
            env('SIGN') => '正常',
            env('LEAVE') => '请假',
            env('ABSENCE') => '旷到',
            env('LATE') => '迟到',
            env('LEAVE_EARLY') => '早退',
        ];
        $cellData = getExcelArray($res, $need, $status);

        return Excel::create('考勤信息', function ($excel) use ($cellData) {
            $excel->sheet('attendance', function ($sheet) use ($cellData) {
                $sheet->rows($cellData);
            });
        })->export('xls');
    }

    public function setStuStatus(Request $request)
    {
        $need = ['ccid', 'status'];
        $user = $request->get('user');
        $info = $request->only($need);

        if (!is_numeric($info['status']))
            return response()->json([
                'status' => 400,
                'message' => 'failed'
            ], 400);

        $condition = [
            'ccid' => $info['ccid'],
            'trid' => $user['trid']
        ];
        $data = [
            'status' => $info['status']
        ];
        if (!CourseCheck::where($condition)->update($data))
            return response()->json([
                'status' => 404,
                'message' => 'Not Found'
            ], 404);

        return response()->json([
            'stauts' => 200,
            'message' => 'success'
        ], 200);
    }

    /**
     * 获取当前时间是第几周
     * return int
     */
    private function getNowWeek()
    {
        $term_start = strtotime(env('TERM_START'));
        $term_end = strtotime(env('TERM_END'));
        $now = time();
        $week = 604800;
        if ($now > $term_start && $now < $term_end)
            return (int)(($now - $term_start) / $week) + 1;

        return 0;
    }


    /**
     * 获取教师所教的教学班信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function dataGetJxb(Request $request)
    {

        $trid = $request->get("trid");
        $mCourse = new TCourse();
        $list = $mCourse->where(['trid' => $trid])->get();

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $list
        ], 200);

    }

    /**
     * 获取jxb缺勤信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function dataGetAbsence(Request $request)
    {
        $trid = $request->get('trid');
        $jxbID = $request->get("jxbID");
        $mCourseCheck = new CourseCheck();

        $list = $mCourseCheck->getAbsence($trid, $jxbID);
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $list
        ], 200);
    }


    function dataGetClass(Request $request)
    {
        $mCourseCheck = new CourseCheck();
        $list = $mCourseCheck->getAbsenceClass();

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $list
        ], 200);
    }

    /**
     * 获取缺勤最多的班级10个
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function dataGetPushesClass(Request $request)
    {
        $mCourseCheck = new CourseCheck();
        $list = $mCourseCheck->getPushesClass();

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $list
        ], 200);
    }

    /**
     * 获取班级所有学生的信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function dataGetClassStudentListAttendence(Request $request)
    {
        $class = $request->get('class');
        $mStudent = new Student();
        $mCourseCheck = new CourseCheck();
        $condition = ['stuClass' => $class];

        $stuList = $mStudent->getStudentListByClass($condition);
        foreach ($stuList as $stu) {
            $stuCode = $stu['stu_code'];
            $res = $mCourseCheck->getAttendenceByStuCode($stuCode);
            //$arrays[$stuCode] = $res;
            foreach ($res as $attend) {
                $arrays[] = $attend;
            }
            //array_merge($arrays, $res);
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $arrays
        ], 200);
    }

    function dataGetALL(Request $request)
    {

        $mCouseCheck = new CourseCheck();
        $mStudent = new Student();

        $typeList = $mCouseCheck->getTypeCount();
        $majorList = $mStudent->getAllMajor();

        $majorCountList = [];
        foreach ($majorList as $major) {
            $majorCount = $mCouseCheck->getMajorCount($major['stuMajor']);
            $majorCountList[$major['stuMajor']] = $majorCount;
        }
        $data = [];
        $data['typeList'] = $typeList;
        $data['majorCountList'] = $majorCountList;


        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $data
        ], 200);
    }
    /* function getInstituteCode (Request $request){
        $mStudent =  new Student();
        $majorList = $mStudent->getAllMajor();
        $array = [];
        foreach ($majorList as $major) {
           $code =  $major['stuMajor'];
           $code = substr($code,0,2);
           $array['instituteCode'] = $code;
            //由于不知道学校学院代码，暂时只开放计算机学院04


         }

     }*/

    /**
     * 获取学院缺勤信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function dataGetInstituteInfo(Request $request)
    {
        $code = $request->get('code');
        $mCourseCheck = new CourseCheck();
        $res = $mCourseCheck->getAttendance($code);
        $classList = [];
        $majorList = [];
        $gradeList = [];
        $courseList = [];
        $statusList = [];
        $tridList = [];
        $monthList = [];
        $yearList = [];
        foreach ($res as $item) {

            if (isset($classList[$item->class])) {
                $classList[$item->class]++;
            } else {
                $classList[$item->class] = 1;
            }

            if (isset($majorList[$item->major])) {
                $majorList[$item->major]++;
            } else {
                $majorList[$item->major] = 1;
            }

            if (isset($gradeList[$item->grade])) {
                $gradeList[$item->grade]++;
            } else {
                $gradeList[$item->grade] = 1;
            }

            if (isset($courseList[$item->course])) {
                $courseList[$item->course]++;
            } else {
                $courseList[$item->course] = 1;
            }

            if (isset($statusList[$item->status])) {
                $statusList[$item->status]++;
            } else {
                $statusList[$item->status] = 1;
            }

            if (isset($tridList[$item->trid])) {
                $tridList[$item->trid]++;
            } else {
                $tridList[$item->trid] = 1;
            }

            if (isset($monthList[$item->month])) {
                $monthList[$item->month]++;
            } else {
                $monthList[$item->month] = 1;
            }

            if (isset($yearList[$item->year])) {
                $yearList[$item->year]++;
            } else {
                $yearList[$item->year] = 1;
            }

        }
        ksort($gradeList);
        ksort($yearList);
        $classList = $this->getTop10($classList);
        $majorList = $this->getTop10($majorList);
        $courseList = $this->getTop10($courseList);
        $tridList = $this->getTop10($tridList);

        //缺勤前10班级
        $classKey = array_keys($classList);
        $classvalue = array_values($classList);

        //缺勤前10的专业
        $majorKey = array_keys($majorList);
        $majorvalue = array_values($majorList);

        //历年的学院缺勤对比
        $gradeKey = array_keys($gradeList);
        $gradelue = array_values($gradeList);

        //缺勤前10的课程
        $courseKey = array_keys($courseList);
        $coursevalue = array_values($courseList);

        //缺勤种类分布
        $statusKey = array_keys($statusList);
        $statusvalue = array_values($statusList);

        //缺勤教师前10
        $tridKey = array_keys($tridList);
        $tridvalue = array_values($tridList);

        //月份分布
        ksort($monthList);
        $monthKey = array_keys($monthList);
        $monthValue = array_values($monthList);

        //每年缺勤总信息
        $yearKey = array_keys($yearList);
        $yearValue = array_values($yearList);
        //$array = ['classInfo' => $classList, 'majorInfo' => $majorList, 'gradeInfo' => $gradeList, 'courseInfo' => $courseList, 'statusInfo' => $statusList,'tridList'=>$tridList];
        $array[] = $classList;
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'classKey' => $classKey,
            'classValue' => $classvalue,
            'gradeKey' => $gradeKey,
            'gradeValue' => $gradelue,
            'majorKey' => $majorKey,
            'majorvalue' => $majorvalue,
            'courseKey' => $courseKey,
            'coursevalue' => $coursevalue,
            'statusKey' => $statusKey,
            'statusvalue' => $statusvalue,
            'tridKey' => $tridKey,
            'tridvalue' => $tridvalue,
            'monthKey' => $monthKey,
            'monthValue' => $monthValue,
            'yearKey' => $yearKey,
            'yearValue' => $yearValue
        ], 200);
    }

    /**
     * 获取所有的学院信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function dataGetAllInstitute(Request $request)
    {
        $mInstitute = new Institute();
        $res = $mInstitute->getAllInstitute();

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $res
        ], 200);
    }

    function getTop10($list)
    {
        /**
         * 排序 全部按照值的大小降序
         */
        arsort($list);

        if (count($list) > 10) {
            return array_slice($list, 10);
        } else {
            return $list;
        }
    }

    function dataGetSchoolYear(Request $request)
    {
        $mcourseCheck = new CourseCheck();
        $res = $mcourseCheck->getSchoolYearAttendance();
        $yearList = [];

        foreach ($res as $item){
            if (isset($yearList[$item->year])) {
                $yearList[$item->year]++;
            } else {
                $yearList[$item->year] = 1;
            }
        }
        $majorKey = array_keys($yearList);
        $majorvalue = array_values($yearList);
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'key' => $majorKey,
            'value' => $majorvalue,
        ], 200);
    }

    /**
     * 获取在校的年级的缺勤分布
     */
    function dataGetCurrentYearAttendance()
    {
        $current = getCourseYear();
        $flag = substr($current, -1, 1);
        $yearSub = substr($current, 0, strlen($current) - 1);

        if ($flag == 1) {
            $years = array($yearSub - 1 + 2000, $yearSub - 2 + 2000, $yearSub - 3 + 2000, $yearSub - 4 + 2000);
        } else {
            $years = array($yearSub - 1 + 2000, $yearSub - 2 + 2000, $yearSub - 3 + 2000, $yearSub + 2000);
        }

        $mcourseCheck = new CourseCheck();
        $res = $mcourseCheck->getSchoolCurrentYearAttendance($years);
        $gradeList = [];

        foreach ($res as $item) {
            if (isset($gradeList[$item->grade])) {
                $gradeList[$item->grade]++;
            } else {
                $gradeList[$item->grade] = 1;
            }
        }
        $keyList = array_keys($gradeList);
        $valueList= array_values($gradeList);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'key' => $keyList,
            'value' => $valueList,
        ], 200);
    }

    function dataGetTypeAttendance()
    {

        $mcourseCheck = new CourseCheck();

        $res = $mcourseCheck->getTypeALL();

        $typeList = [];
        foreach ($res as $item) {
            if (isset($typeList[$item->status])) {
                $typeList[$item->status]++;
            } else {
                $typeList[$item->status] = 1;
            }
        }
        ksort($typeList);

        $keyList = array_keys($typeList);
        $valueList= array_values($typeList);

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'key'=>$keyList,
            'value'=>$valueList,
        ], 200);
    }

    public function dataGetMonthAttendance(){
        $mcourseCheck = new CourseCheck();

        $res = $mcourseCheck->getMonthALL();

        $monthList = [];
        foreach ($res as $item) {
            if (isset($monthList[$item->month])) {
                $monthList[$item->month]++;
            } else {
                $monthList[$item->month] = 1;
            }
        }
        ksort($monthList);
        $keyList = array_keys($monthList);
        $valueList= array_values($monthList);
        return response()->json([
            'status' => 200,
            'message' => 'success',
            'key'=>$keyList,
            'value'=>$valueList
        ], 200);
    }

    public function dataGetSchoolInstitute(){
        $mcourseCheck = new CourseCheck();
        $res = $mcourseCheck->getInstituteOrder();

        asort($res);
        foreach ($res as $item){
            $keyList[] =   $item['name'];
            $valueList[] = $item['count'];
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'key'=>$keyList,
            'value'=>$valueList
            //'instituteValue'=>$valueList
        ], 200);
    }


    public function putCourseCheck(Request $request)
    {
        $job_data = [
            'stuNum' => '',
            'stuName' => '',
            'trid' => '089906',
            'jxbID' => 'cs20151111',
            'course' => '测试课程',
            'year' => '151',
            'month' => '',
            'week' => '',
            'hash_day' => '4',
            'hash_lesson' => '9',
            'major' => '',
            'grade' => '',
            'class' => '',  //抓取数据临时更改如此
            'scNum' => '0000022',
            'status' => ''
        ];
        $i = 0;
        $mCourseCheck = new CourseCheck();
        while ($i < 3000) {
            $job_data['stuNum'] = '20152116' . ($i % 100);
            $job_data['stuName'] = '测试' . ($i % 100);
            $job_data['month'] = ($i % 12 + 1);
            $job_data['week'] = ($i % 18 + 1);
            $job_data['major'] = '0' . ($i % 3 + 1) . '0' . (rand(1, 3));
            $job_data['grade'] = rand(1, 4) + 2013;
            $job_data['class'] = '0' . ($i % 3 + 1) . '0' . (rand(1, 3)) . '15' . (rand(1, 5));

            $status = rand(1, 20);
            if ($status > 5 & $status != 9) {
                $status = 1;
            }
            $job_data['status'] = $status;
            $mCourseCheck::create($job_data);
            $i++;
        }

    }

}
