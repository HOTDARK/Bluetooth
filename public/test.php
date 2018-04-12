<?php  
$serverName = "localhost"; //数据库服务器地址
$uid = "xie";     //数据库用户名
$pwd = "xz107242"; //数据库密码
$connectionInfo = array("UID"=>$uid, "PWD"=>$pwd, "Database"=>"blue");
$conn = sqlsrv_connect($serverName, $connectionInfo);
if( $conn == false)
{
    echo "连接失败！";
    var_dump(sqlsrv_errors());
    exit;
}else{
    echo "链接成功";
    echo "链接成功";
}