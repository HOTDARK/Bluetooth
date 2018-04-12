<?php
/**
 * Created by PhpStorm.
 * User: xiezh
 * Date: 2018/3/9
 * Time: 0:05
 */namespace  App\Models;

 use Illuminate\Database\Eloquent\Model;

 class institute extends Model{

     protected $table = 'institute';
     protected $primaryKey = 'code';
     protected $fillable = ['name','code','major'];

     function getMajorList($code){
        return $this->where('code',$code)
             ->select("major")->get();
     }

     function getAllInstitute(){
         $res = $this::all();
         foreach ($res as $item)
             $item['major'] = json_decode($item['major']);
         return $res;
     }

     function createExample(){


         for($i = 1;$i<=3;$i++){
             $list = [];
             $list[] = ['majorCode'=>'0'.$i.'01','majorName'=>'0'.'测试专业'.$i.'01'];
             $list[] = ['majorCode'=>'0'.$i.'02','majorName'=>'0'.'测试专业'.$i.'02'];
             $list[] = ['majorCode'=>'0'.$i.'03','majorName'=>'0'.'测试专业'.$i.'03'];

             $str = json_encode($list);
             $ex = ['name'=>'测试学院'.$i,'code'=>'0'.$i,'major'=>$str];
             $this::create($ex);
         }

     }
 }