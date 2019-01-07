<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/13
 * Time: 16:34
 */

namespace app\admin\controller;

use app\admin\model\BusinessModel;
use think\queue\Job;

class Work
{
    /**
     * fire方法是消息队列默认调用的方法
     * @param Job            $job      当前的任务对象
     * @param array|mixed    $data     发布任务时自定义的数据
     */
    public function fire(Job $job,$data){//执行当前队列时间为当天凌晨1点
        //获取明天凌晨1分时间戳
        $tomorrow_time = mktime(0, 0, 0, date('m'), date('d')+1, date('Y'))+60;;
        // 如有必要,可以根据业务需求和数据库中的最新数据,判断该任务是否仍有必要执行
        $isJobStillNeedToBeDone = $this->checkDatabaseToSeeIfJobNeedToBeDone();
        if(!$isJobStillNeedToBeDone){//如果当天不是工作日，则直接进入明天的任务
            //获取时间间隔
            $distance_time = $tomorrow_time-time();
            $job->delete();
            $job->release($distance_time);
            return;
        }

        $isJobDone = $this->doWorkJob();

        if ($isJobDone) {
            //获取时间间隔
            $distance2_time = $tomorrow_time-time();
            //如果任务执行成功，重发
            $job->delete();
            $job->release($distance2_time);
        }else{
            if ($job->attempts() > 3) {
                //通过这个方法可以检查这个任务已经重试了几次了
                $info = '任务执行失败,执行任务超过了3次,当前时间:'.date('Y-m-d H:i:s');
                file_put_contents('queue.txt',var_export($info,true)."\n",FILE_APPEND);
                //重新发布这个任务
                //获取时间间隔
                $distance3_time = $tomorrow_time-time();
                $job->delete();
                $job->release($distance3_time); //$delay为延迟时间，表示该任务延迟几秒后再执行
            }
        }
    }

    /**
     * 有些消息在到达消费者时,可能已经不再需要执行了
     * @param array|mixed    $data     发布任务时自定义的数据
     * @return boolean                 任务执行的结果
     */
    private function checkDatabaseToSeeIfJobNeedToBeDone(){
        $today_whether = check_work_day();
        if($today_whether == 1){
            return true;
        }else{
            $info = '休息日不任务执行,当前时间:'.date('Y-m-d H:i:s');
            file_put_contents('queue.txt',var_export($info,true)."\n",FILE_APPEND);
            return false;
        }
    }

    /**
     * 根据消息中的数据进行实际的业务处理
     * @param array|mixed    $data     发布任务时自定义的数据
     * @return boolean                 任务执行的结果
     */
    private function doWorkJob() {
        // 根据消息中的数据进行实际的业务处理...
        $now_day = mktime(0, 0, 0, date('m'), date('d'), date('Y'));//当天时间戳
        $businessModel = new BusinessModel();
        $where['status'] = array('in','2,3,4,5,6,7,8,9');//不是预约和通气两种状态
        $where['create_day'] = array('lt',$now_day);//创建时间小于今天
        $business = $businessModel->where($where)->select();
        if(count($business) > 0){//如果有数据
            $dataArray = array();
            foreach ($business as $k=>$v){
                $dataArray[] = [
                    'id' => $v['id'],
                    'continuous_day' => $v['continuous_day']+1,
                    'update_time' => time()
                ];
            }
            $businessModel->saveAll($dataArray);
        }
        $info = "任务执行成功,当前时间:".date('Y-m-d H:i:s');
        file_put_contents('queue.txt',var_export($info,true)."\n",FILE_APPEND);
        return true;
    }
}