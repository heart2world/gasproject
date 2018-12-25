<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 10:29
 */

/**
 * 检测工作日
 * return 1上班 2休息
 */
function check_work_day(){
    $month = date('Y-n');
    $day = date('Y-n-j');
    $week = date('w');
    $curl = new \curl\Curl();
    $cacheKey ="DATES_CACHE";
    $result = cache($cacheKey);
    $ctime =\think\helper\Time::today()[1]-time();
    if (!$result){
        $result = $curl->get('http://v.juhe.cn/calendar/month?year-month='.$month.'&key=f7b0974f0d5608e67370d0b7adf068c4');
        $result = json_decode($result,true);
        cache($cacheKey,$result,$ctime);
    }

    $holiday_array = $result['result']['data']['holiday_array'];

    $rest_day = array();//休息日
    $work_day = array();//工作日
    if($holiday_array){
        foreach ($holiday_array as $key => $value) {
            foreach ($value['list'] as $k => $v) {
                if($v['status'] == 1){
                    $rest_day[] = $v['date'];
                }elseif($v['status'] == 2){
                    $work_day[] = $v['date'];
                }
            }
        }
    }
    if(in_array($week,[0,6])){
        //休息日
        if(in_array($day,$work_day)){
            //上班
            return 1;
        }else{
            return 0;
        }
    }else{
        //工作日
        if(in_array($day,$rest_day)){
            //休息
            return 0;
        }else{
            return 1;
        }
    }
}