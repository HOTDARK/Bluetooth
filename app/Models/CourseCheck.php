<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CourseCheck extends Model
{
    protected $table = 'course_check';

    protected $primaryKey = 'ccid';

    protected $fillable = ['stuNum', 'stuName', 'trid', 'jxbID', 'course', 'year', 'month', 'week', 'hash_day', 'hash_lesson',
        'major', 'grade', 'class', 'scNum', 'status'];

    public function stuAttendance($user, $info)
    {
        $need = ['week', 'hash_day', 'hash_lesson', 'status', 'jxbID', 'course'];
        $condition = [
            'stuNum' => $user['stu_code'],
            'year' => $info['year'],
            'hash_day' => $info['hash_day'],
            'hash_lesson' => $info['hash_lesson'],
            'week' => $info['week']
        ];

        if ($info['week'] == 0)
            unset($condition['week']);

        if ($info['hash_day'] == 0)
            unset($condition['hash_day']);

        if ($info['hash_lesson'] == 0)
            unset($condition['hash_lesson']);

        if (!$res = $this->where($condition)->select($need)->get())
            return false;

        return $res;
    }

    public function teaAttendance($info)
    {
        $need = ['stuNum', 'stuName', 'week', 'hash_day', 'hash_lesson', 'status'];
        $condition = [
            'jxbID' => $info['jxbID'],
            'year' => $info['year'],
            'hash_day' => $info['hash_day'],
            'hash_lesson' => $info['hash_lesson'],
            'week' => $info['week']
        ];

        if ($info['week'] == 0)
            unset($condition['week']);

        if ($info['hash_day'] == 0)
            unset($condition['hash_day']);

        if ($info['hash_lesson'] == 0)
            unset($condition['hash_lesson']);

        if (!$res = $this->where($condition)->select($need)->get())
            return false;

        return $res;
    }

    public function getWeekStatistics($info, $trid)
    {
        $need = ['hash_day', 'status'];
        $condition = [
            'year' => $info['year'],
            'trid' => $trid,
            'major' => $info['major'],
            'grade' => $info['grade'],
            'scNum' => $info['scNum']
        ];

        if ($info['major'] == 0)
            unset($condition['major']);

        if ($info['grade'] == 0)
            unset($condition['grade']);

        if ($info['scNum'] == 0)
            unset($condition['scNum']);

        if (!$res = $this->where($condition)->select($need)->get())
            return false;

        for ($i = 0; $i < 7; $i++) {
            $count_leave[$i] = 0;
            $count_late[$i] = 0;
            $count_absence[$i] = 0;
        }

        foreach ($res as $key => $value) {
            switch ($value['status']) {
                case env('LEAVE') :
                    {
                        $count_leave[$value['hash_day']]++;
                        break;
                    }
                case env('LATE') :
                    {
                        $count_late[$value['hash_day']]++;
                        break;
                    }
                case env('ABSENCE') :
                    {
                        $count_absence[$value['hash_day']]++;
                        break;
                    }
            }
        }

        $data = [
            'leave' => $count_leave,
            'late' => $count_late,
            'absence' => $count_absence
        ];

        return $data;
    }

    public function getMonthStatistics($month, $trid)
    {
        $year = getCourseYear();
        $condition = [
            'trid' => $trid,
            'year' => $year,
            'month' => $month
        ];
        $need = ['status', 'created_at'];
        if (!$res = $this->where($condition)->select($need)->get())
            return false;

        $month_day = date('t', time());
        for ($i = 0; $i < $month_day; $i++) {
            $count[$i] = 0;
        }

        foreach ($res as $key => $value) {
            $day = intval(substr($value['created_at'], 8, 2));
            if ($value['status'] == env('ABSENCE'))
                $count[$day]++;
        }

        return $count;
    }

    public function getTermStatistics($info, $trid)
    {
        //先获取课程表中所有的该教师的课程
        $condition = [
            'year' => $info['year'],
            'trid' => $trid,
            'scNum' => $info['scNum'],
            'grade' => $info['grade'],
            'jxbID' => $info['jxbID']
        ];
        $course_need = ['jxbID', 'hash_day', 'hash_lesson', 'week'];

        //清除无用信息
        foreach ($info as $key => $value) {
            if ($value == 0)
                unset($condition[$key]);
        }

        //
        $course_condition = $condition;
        if (isset($condition['grade'])) {
            unset($course_condition['grade']);
        }

        if (!$course_res = TCourse::where($course_condition)->select($course_need)->get())
            return false;

        //获取所有考勤信息
        $check_need = ['jxbID', 'week', 'hash_day', 'hash_lesson', 'status'];
        if (!$check_res = $this->where($condition)->select($check_need)->get())
            return false;

        //制作这学期所有课程的数组
        $statistics_arr = [];
        foreach ($course_res as $key => $value) {
            $week_str = $value['week'];
            $week_arr = explode(',', $week_str);
            //$statistics_arr[$value['jxbID']] = [];
            foreach ($week_arr as $k => $v) {
                $temp_key = $v . $value['hash_day'] . $value['hash_lesson'];
                $statistics_arr[$value['jxbID']][$temp_key] = 0;
            }
        }

        //统计人数
        $absence_enum = env('ABSENCE');
        foreach ($check_res as $k1 => $v1) {
            if ($v1['status'] == $absence_enum) {
                $temp_key = $v1['week'] . $v1['hash_day'] . $v1['hash_lesson'];
                if (array_key_exists($v1['jxbID'], $statistics_arr))
                    $statistics_arr[$v1['jxbID']][$temp_key]++;
            }
        }

//        //制作这学期所有课程的数组
//        $week_num = getAllWeek();
//        $statistics_arr = [];
//        foreach ($check_res as $key => $value) {
//            $new_key = $value['jxbID'] . '.' . $value['hash_day'] . '.' . $value['hash_lesson'];
//            $statistics_arr[$new_key]  = [];
//            for ($i = 0; $i < $week_num; $i++) {
//                $statistics_arr[$new_key][$i] = 0;
//            }
//        }
//
//        //统计人数
//        $absence_enum = env('ABSENCE');
//        foreach ($check_res as $k1 => $v1) {
//            if ($v1['status'] == $absence_enum) {
//                $temp_key = $v1['jxbID'] . '.' . $v1['hash_day'] . '.' . $v1['hash_lesson'];
//                $statistics_arr[$temp_key][($v1['week'] - 1)]++;
//            }
//        }

//        //制作这学期所有课程的数组
//        $week_num = getAllWeek();
//        $statistics_arr = [];
//        foreach ($check_res as $key => $value) {
//            $new_key = $value['jxbID'];
//            $statistics_arr[$new_key]  = [];
//            for ($i = 0; $i < $week_num; $i++) {
//                $statistics_arr[$new_key][$i] = 0;
//            }
//        }
//
//        //统计人数
//        $absence_enum = env('ABSENCE');
//        foreach ($check_res as $k1 => $v1) {
//            if ($v1['status'] == $absence_enum) {
//                $temp_key = $v1['jxbID'];
//                $statistics_arr[$temp_key][($v1['week'] - 1)]++;
//            }
//        }

        return $statistics_arr;

    }

    public function getStuList($info, $trid)
    {
        $need = ['ccid', 'stuNum', 'stuName', 'hash_day', 'class', 'status', 'created_at'];
        $condition = [
            'trid' => $trid,
            'year' => $info['year'],
            'month' => $info['month'],
            'week' => $info['week'],
            'major' => $info['major'],
            'grade' => $info['grade'],
            'scNum' => $info['scNum'],
            'status' => $info['status']
        ];

        //返回当月信息,否则返回当周信息
        if ($info['this_month'] && !$info['today']) {
            $condition['month'] = getNowMonth();
            unset($condition['week']);
        }

        foreach ($condition as $key => $value)
            if (!$value && $condition[$key] != '0')
                unset($condition[$key]);

        if (empty($info['stuName']) && empty($info['stuNum'])) {
            if (!$res = $this->where($condition)->select($need)->paginate($info['per_page']))
                return false;
        } elseif (!empty($info['stuName'])) {
            if (!$res = $this->where($condition)
                ->where('stuName', 'like', '%' . $info['stuName'] . '%')
                ->select($need)
                ->paginate($info['per_page']))
                return false;
        } elseif (!empty($info['stuNum'])) {
            if (!$res = $this->where($condition)
                ->where('stuNum', 'like', '%' . $info['stuNum'] . '%')
                ->select($need)
                ->paginate($info['per_page']))
                return false;
        }

        if (!$info['today']) {
            return $res;
        }

        //返回当天信息
        $hash_day = date('N', time()) - 1;
        foreach ($res as $key => $value) {
            if ($value['hash_day'] != $hash_day) {
                unset($res[$key]);
            }
        }

        return $res;
    }

    function getAbsence($trid, $jxbID)
    {
        $year = getCourseYear();
        $list = $this->where(['trid' => $trid, 'jxbID' => $jxbID])
            ->whereNotIn('status', [1])
            ->select()->get();
        return $list;
    }

    function getAbsenceClass()
    {
        $res = $this->whereNotNull('class')
            ->whereNotIn('status', [1])
            ->groupBy('class')
            ->distinct()
            ->select('class')
            ->get();
        return $res;
    }

    function getAttendenceByStuCode($stuCode)
    {
        $res = $this->where('stuNum', $stuCode)
            ->whereNotIn('status', [1])
            ->select()
            ->get();
        return $res;
    }

    function getTypeCount()
    {
        for ($i = 1; $i < 5; $i++) {
            $count = $this->where('status', $i)
                ->whereNotIn('status', [1])
                ->select('statuc')
                ->count();
            $array[$i] = $count;
        }
        return $array;
    }

    function getMajorCount($major)
    {
        return $this->where('major', $major)
            ->whereNotIn('status', [1])
            ->select('major')
            ->count('major');
    }

    function getAllMajor()
    {

    }

    public function getPushesClass()
    {
        $worse = DB::select("SELECT class,COUNT(*)FROM course_check GROUP BY class ORDER BY COUNT(*) DESC;");

        $i = 0;
        foreach ($worse as $key => $value) {
            if ($i < 10) {
                $array[$key] = $value;
                $i++;
            }
        }
        //$best = DB::select("SELECT class,COUNT(*)FROM course_check GROUP BY class ORDER BY COUNT(*);");
        return $array;
    }

    public function getAttendance($code)
    {
        $code = $code . '%';
        return DB::select('select * from course_check where major like ? and status not like ?', [$code, 1]);
    }

    public function getSchoolYearAttendance()
    {
        return DB::select('select year from course_check where status not like ? order by year ', [1]);
    }

    public function getSchoolCurrentYearAttendance($years)
    {

        return $res = $this->whereIn('grade', $years)
            ->whereNotIn('status', [1])
            //->select('count(*)')
            ->select('grade')
            ->get();
    }

    public function getTypeALL()
    {
        return $this->whereNotNull('status')
            ->select('status')
            ->get();
    }

    public function getMonthALL()
    {
        return $this->whereNotNull('month')
            ->whereNotIn('status', [1])
            ->select('month')
            ->get();
    }

    public function getInstituteOrder()
    {
        $mInstitute = new Institute();
        $res = $mInstitute->whereNotNull('code')
           ->get();
        $instituteinfoList = [];
        foreach ($res as $item) {
            $code = $item['code'];
            while (strlen($code) < 2) {
                $code = '0' . $code;
            }
            $code = $code . '%';
            $count = DB::select('select count(*) from course_check where status != ? and major like ? order by count(*) desc ', [1, $code]);

            foreach ($count as $item_2){
                $str = 'count(*)';
                $count = $item_2->$str;
            }
            $mInstituteinfo['name'] = $item['name'];
            $mInstituteinfo['count'] = $count;
            $instituteinfoList[] = $mInstituteinfo;
        }
        arsort($instituteinfoList);
        return $instituteinfoList;
    }

}
