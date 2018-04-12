<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', 'Teacher\TeacherController@toLogin');
Route::get('/login', 'Teacher\TeacherController@toLogin');
Route::get('/index', 'Teacher\TeacherController@index');
Route::get('/home', 'Teacher\TeacherController@index');
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => ['web'], 'prefix' => 'api'], function () {
    Route::any('/test', 'Teacher\TeacherController@putCourseCheck');
    //教师操作
    Route::group(['prefix' => 'teacher', 'namespace' => 'Teacher'], function () {
        Route::post('/login', 'TeacherController@login');//登录
        Route::group(['middleware' => ['jwt.teacher']], function () {
            Route::get('/course', 'TeacherController@getCourse');//获取教师课程表
            Route::get('/stulist', 'TeacherController@getStuListByJxbID');//获取指定教学班的学生名单
            Route::post('/attendance', 'TeacherController@checkAttendance');//提交考勤数据
            Route::get('/attendance', 'TeacherController@getAttendance')->middleware('tea.attendance');//获取考勤数据

            Route::group(['prefix'=>'dataanalysis'],function (){
                Route::get('/course','TeacherController@dataGetJxb');//获取教师课程
                Route::get('/attendance','TeacherController@dataGetAbsence');//获取jxb的缺勤信息
                //Route::get('/class','TeacherController@dataGetClass');//获取班级缺勤信息
                Route::get('/pushesclass','TeacherController@dataGetPushesClass');//获取突出的班级
                //Route::get('/classStudentList','TeacherController@dataGetClassStudentList');
                Route::get('/classStudentAttendence','TeacherController@dataGetClassStudentListAttendence');//获取班级缺勤信息
                Route::get('/dataAll','TeacherController@dataGetALL');
                Route::get('/instituteAll','TeacherController@dataGetAllInstitute');//获取所有的学院
                Route::get('/instituteInfo','TeacherController@dataGetInstituteInfo');//获取学院信息
                Route::get('/schoolYear','TeacherController@dataGetSchoolYear');//获取学校所有年的缺勤信息总记录
                Route::get('/schoolGrade','TeacherController@dataGetCurrentYearAttendance');//获录在校年级的缺勤信息
                Route::get('/schoolType','TeacherController@dataGetTypeAttendance');//获录在校年级的缺勤信息
                Route::get('/schoolMonth','TeacherController@dataGetMonthAttendance');//获录在校年级的缺勤信息
                Route::get('/schoolInstitute','TeacherController@dataGetSchoolInstitute');//获取学院的缺勤排名信息

            });
            Route::group(['prefix' => 'web'], function () {
                Route::get('/courselist', 'TeacherController@getCourseList');
                Route::get('/statistics', 'TeacherController@getStatistics');
                Route::get('/weekstatistics', 'TeacherController@getWeekStatistics')->middleware('tea.statistics');
                Route::get('/monthstatistics', 'TeacherController@getMonthStatistics');
                Route::get('/termstatistics', 'TeacherController@getTermStatistics')->middleware('tea.statistics');
                Route::get('/stulist', 'TeacherController@getStuList')->middleware('tea.stu.list');
                Route::get('/stulist/excel', 'TeacherController@getStuListExcel')->middleware('tea.stu.list');
                Route::put('/stu', 'TeacherController@setStuStatus');
            });
        });
    });
    //学生操作
    Route::group(['prefix' => 'student', 'namespace' => 'Student'], function () {

        Route::any('/login', 'StudentController@login');//登录
        Route::group(['middleware' => ['jwt.student']], function () {
            Route::get('/course', 'StudentController@getCourse');//获取学生课表
            Route::get('/attendance', 'StudentController@getAttendance')->middleware('stu.attendance');//获取学生本人的考勤信息
        });
        Route::get('/courseTest', 'StudentController@getCourseTest');//获取学生课表

    });

});




//Route::get('/spider/teacher/list', 'Spider\TeacherController@getList');
//Route::get('/spider/teacher/course', 'Spider\TeacherController@getCourse');
//Route::get('/spider/teacher/course', 'Spider\TeacherController@getNewCourse');
//Route::get('/spider/teacher/stulist', 'Spider\TeacherController@getStuList');
