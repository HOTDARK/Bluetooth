<?php
/**
 * Created by PhpStorm.
 * User: xiezh
 * Date: 2018/3/13
 * Time: 21:32
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class StuCourse extends Model
{
    protected $table = 'student_courses';
    protected $primaryKey = 'stuCode';
    protected $fillable = ['stuCode','courseJson','week'];

    function getCourseJsonByCode($code,$week){

        $res = $this->where('stuCode',$code)
            ->where('week',$week)->select('courseJson')->get(0);
        foreach ($res as $item)
        {
            return  $item->courseJson;
        }

    }
}