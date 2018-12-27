<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 17:24
 */

namespace app\admin\controller;


use app\admin\model\BusinessGasModel;
use app\admin\model\BusinessModel;
use app\admin\model\BusinessNatureModel;
use app\admin\model\BusinessProcessModel;
use app\admin\model\BusinessTimeModel;
use app\admin\model\DepartmentModel;
use app\admin\model\UserModel;
use cmf\controller\AdminBaseController;
use think\Queue;

class BusinessController extends AdminBaseController
{
    //业务管理
    public function index(){
        $where = array();
        /*搜索条件*/
        $keyword = $this->request->param('keyword','');
        if($keyword){
            $where['number|name|contact|contact_mobile'] = array('like',"%$keyword%");
        }
        $status = $this->request->param('status',0,'intval');
        if($status){
            $where['status'] = array('eq',$status);
        }
        $begin_times = $this->request->param('begin_time','');
        $end_times = $this->request->param('end_time','');
        $begin_time = 0;
        if($begin_times){
            $begin_time = strtotime($begin_times);
        }
        $end_time = 0;
        if($end_times){
            $end_time = strtotime($end_times);
        }
        if($begin_time > 0 && $end_time > 0){
            $where['create_time'] = array('between time',[$begin_time,$end_time]);
        }elseif ($begin_time > 0){
            $where['create_time'] = array('>= time',$begin_time);
        }elseif ($end_time > 0){
            $where['create_time'] = array('<= time',$end_time);
        }

        $businessModel = new BusinessModel();
        $business = $businessModel->where($where)->order('status ASC,create_time DESC')->paginate(20);
        $business->appends([
            'keyword' => $keyword,
            'status' => $status,
            'begin_time' => $begin_times,
            'end_time' => $end_times
        ]);
        // 获取分页显示
        $page = $business->render();
        //获取状态列表
        $businessTimeModel = new BusinessTimeModel();
        $times = $businessTimeModel->order('id ASC')->select();
        //匹配状态名称
        foreach ($business as $k=>$v){
            foreach ($times as $m=>$n){
                if($v['status'] == $n['id']){
                    $business[$k]['status_name'] = $n['name'];
                }
            }
        }
        //获取用气量列表
        $businessGasModel = new BusinessGasModel();
        $gas = $businessGasModel->order('listorder ASC')->select();
        $this->assign("business",$business);
        $this->assign("lists",$business->toArray()['data']);
        $this->assign("page",$page);
        $this->assign("times",$times);
        $this->assign("gas",json_encode($gas));
        return $this->fetch();
    }

    //新增预约业务
    public function reservation_add(){
        //获取用气性质
        $businessNatureModel = new BusinessNatureModel();
        $nature = $businessNatureModel->order('id ASC')->select();
        $this->assign("nature",$nature);
        return $this->fetch();
    }

    //新增预约业务提交
    public function reservation_add_post(){
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'Business.reservation');
            if ($result !== true) {
                $this->error($result);
            } else {
                $businessModel = new BusinessModel();
                $_POST['create_time'] = time();
                $_POST['create_day'] = strtotime(date('Y-m-d 00:00:00'));
                $business_id = $businessModel->allowField(true)->insertGetId($_POST);
                if ($business_id !== false) {
                    $this->process_action($business_id,1);//添加预约流程
                    $this->success("添加成功！", url("Business/index"));
                } else {
                    $this->error("添加失败！");
                }
            }
        }
    }

    //新增正式业务
    public function formal_add(){
        //获取用气性质
        $businessNatureModel = new BusinessNatureModel();
        $nature = $businessNatureModel->where(array('type'=>1))->order('id ASC')->select();
        //获取用气量列表
        $businessGasModel = new BusinessGasModel();
        $gas = $businessGasModel->order('listorder ASC')->select();
        $this->assign("nature",$nature);
        $this->assign("gas",$gas);
        return $this->fetch();
    }

    //新增正式业务提交
    public function formal_add_post(){
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'Business.formal');
            if ($result !== true) {
                $this->error($result);
            } else {
                $businessModel = new BusinessModel();
                $_POST['type'] = 2;
                $_POST['create_time'] = time();
                $_POST['status'] = 3;
                $_POST['create_day'] = strtotime(date('Y-m-d 00:00:00'));
                $business_id = $businessModel->allowField(true)->insertGetId($_POST);
                if ($business_id !== false) {
                    $this->process_action($business_id,3);//添加受理正式申请流程
                    $this->success("添加成功！", url("Business/index"));
                } else {
                    $this->error("添加失败！");
                }
            }
        }
    }

    //添加预约流程信息
    private function process_action($business_id,$step,$remark = null){
        //获取当前用户信息
        $now_id = cmf_get_current_admin_id();
        $userModel = new UserModel();
        $user_info = $userModel->find($now_id);
        $department = '';
        if($user_info && !empty($user_info['department_id'])){
            $departmentModel = new DepartmentModel();
            $depart = $departmentModel->find($user_info['department_id']);
            if($depart){
                $department = $depart['name'];
            }
        }

        //获取流程信息
        $businessTimeModel = new BusinessTimeModel();
        $business = $businessTimeModel->find($step);

        $businessProcessModel = new BusinessProcessModel();
        $dataInfo = [
            'business_id' => $business_id,
            'name' => $business['name'],
            'step' => $step,
            'expected_day' => $business['day'],
            'department' => $department,
            'user_id' => $now_id,
            'remark' => $remark
        ];
        $businessProcessModel->allowField(true)->save($dataInfo);
        return true;
    }

    //编辑业务
    public function business(){
        $id = $this->request->param('id', 0, 'intval');
        $businessModel = new BusinessModel();
        $business = $businessModel->find($id);
        $where = array();
        if(!$business){
            $this->error("当前业务信息不存在！");
        }
        if($business['type'] == 2){
            $where['type'] = array('eq',1);
        }
        //获取用气量列表
        $businessGasModel = new BusinessGasModel();
        $gas = $businessGasModel->order('listorder ASC')->select();
        //获取用气性质
        $businessNatureModel = new BusinessNatureModel();
        $nature = $businessNatureModel->where($where)->order('id ASC')->select();
        $this->assign("business",$business);
        $this->assign("gas",$gas);
        $this->assign("nature",$nature);
        return $this->fetch();
    }

    //编辑业务提交
    public function business_post(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $businessModel = new BusinessModel();
            $business = $businessModel->find($id);
            if(!$business){
                $this->error("当前业务信息不存在！");
            }
            if($business['type'] == 2 || $business['status'] >= 3) {
                $result = $this->validate($this->request->param(), 'Business.edit');
            }else{
                $result = $this->validate($this->request->param(), 'Business.reservation');
            }
            if ($result !== true) {
                $this->error($result);
            } else {
                $return = $businessModel->allowField(true)->isUpdate(true)->save($_POST);
                if ($return !== false) {
                    $this->success("更新成功！", url("Business/index"));
                } else {
                    $this->error("更新失败！");
                }
            }
        }
    }

    //业务详情
    public function info(){
        $id = $this->request->param('id', 0, 'intval');
        $businessModel = new BusinessModel();
        $business = $businessModel->find($id);
        if(!$business){
            $this->error("当前业务信息不存在！");
        }
        //获取状态列表
        $businessTimeModel = new BusinessTimeModel();
        $times = $businessTimeModel->order('id ASC')->select();
        //获取用气性质
        $businessNatureModel = new BusinessNatureModel();
        $nature = $businessNatureModel->order('id ASC')->select();
        //匹配状态名称及用气性质
        foreach ($times as $m=>$n){
            if($business['status'] == $n['id']){
                $business['status_name'] = $n['name'];
            }
        }
        foreach ($nature as $x=>$y){
            if($business['nature_id'] == $y['id']){
                $business['nature_name'] = $y['name'];
            }
        }
        $this->assign("business",$business);
        return $this->fetch();
    }

    //流程信息
    public function process(){
        $id = $this->request->param('id', 0, 'intval');
        $businessModel = new BusinessModel();
        $business = $businessModel->find($id);
        if(!$business){
            $this->error("当前业务信息不存在！");
        }
        $businessProcessModel = new BusinessProcessModel();
        $process = $businessProcessModel
            ->alias('p')
            ->join('gas_user u','u.id=p.user_id','LEFT')
            ->where(array('p.business_id'=>$id))
            ->field('p.*,u.user_nickname,u.mobile')
            ->order('id ASC')
            ->select();
        $this->assign("process",$process);
        $this->assign("id",$id);
        $this->assign("limits",$business['limit_type']);
        return $this->fetch();
    }

    //预约转化为现场初勘
    public function conversion_reservation(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'type'=>1,'status'=>1))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->conversion_action($id,2,$remark,$business['continuous_day']);//转化
            if ($result == true) {
                $businessModel->isUpdate(true)->save(array('id'=>$id,'status'=>2,'continuous_day'=>0));//更新状态
                $this->success("转化成功！", url("Business/index"));
            } else {
                $this->error("转化失败！");
            }
        }
    }

    //现场初勘转化为受理正式申请
    public function conversion_site(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'type'=>1,'status'=>2))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->validate($this->request->param(), 'Business.conversion');
            if ($result !== true) {
                $this->error($result);
            }else {
                $return = $this->conversion_action($id, 3, $remark,$business['continuous_day']);//转化
                if ($return == true) {
                    $_POST['status'] = 3;
                    $_POST['continuous_day'] = 0;
                    $businessModel->allowField(true)->isUpdate(true)->save($_POST);//更新状态
                    $this->success("转化成功！", url("Business/index"));
                } else {
                    $this->error("转化失败！");
                }
            }
        }
    }

    //受理正式申请转化为设计
    public function conversion_accept(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'status'=>3))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->conversion_action($id,4,$remark,$business['continuous_day']);//转化
            if ($result == true) {
                $businessModel->isUpdate(true)->save(array('id'=>$id,'status'=>4,'continuous_day'=>0));//更新状态
                $this->success("转化成功！", url("Business/index"));
            } else {
                $this->error("转化失败！");
            }
        }
    }

    //设计转化为预算
    public function conversion_design(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'status'=>4))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->conversion_action($id,5,$remark,$business['continuous_day']);//转化
            if ($result == true) {
                $businessModel->isUpdate(true)->save(array('id'=>$id,'status'=>5,'continuous_day'=>0));//更新状态
                $this->success("转化成功！", url("Business/index"));
            } else {
                $this->error("转化失败！");
            }
        }
    }

    //预算转化为预算审核
    public function conversion_budget(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'status'=>5))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->conversion_action($id,6,$remark,$business['continuous_day']);//转化
            if ($result == true) {
                $businessModel->isUpdate(true)->save(array('id'=>$id,'status'=>6,'continuous_day'=>0));//更新状态
                $this->success("转化成功！", url("Business/index"));
            } else {
                $this->error("转化失败！");
            }
        }
    }

    //预算审核转化为合同办理
    public function conversion_verify(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $payment = $this->request->param('payment',0);
            $sms = $this->request->param('sms',0,'intval');
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'status'=>6))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $return = $this->validate($this->request->param(), 'Business.payment');
            if ($return !== true) {
                $this->error($return);
            }else {
                $result = $this->conversion_action($id, 7, $remark, $business['continuous_day']);//转化
                if ($result == true) {
                    $businessModel->isUpdate(true)->save(array('id' => $id, 'status' => 7, 'continuous_day' => 0,'payment'=>$payment,'sms'=>$sms));//更新状态
                    if($business['type'] == 1 && $sms == 1){//预约业务发送短信

                    }
                    $this->success("转化成功！", url("Business/index"));
                } else {
                    $this->error("转化失败！");
                }
            }
        }
    }

    //合同办理转化为缴费
    public function conversion_contract(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'status'=>7))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->conversion_action($id,8,$remark,$business['continuous_day']);//转化
            if ($result == true) {
                $businessModel->isUpdate(true)->save(array('id'=>$id,'status'=>8,'continuous_day'=>0));//更新状态
                $this->success("转化成功！", url("Business/index"));
            } else {
                $this->error("转化失败！");
            }
        }
    }

    //缴费转化为安装
    public function conversion_payment(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'status'=>8))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->conversion_action($id,9,$remark,$business['continuous_day']);//转化
            if ($result == true) {
                $businessModel->isUpdate(true)->save(array('id'=>$id,'status'=>9,'continuous_day'=>0));//更新状态
                $this->success("转化成功！", url("Business/index"));
            } else {
                $this->error("转化失败！");
            }
        }
    }

    //安装转化为通气
    public function conversion_installation(){
        if ($this->request->isPost()) {
            $id = $this->request->param('id', 0, 'intval');
            $remark = $this->request->param('remark',null);
            $businessModel = new BusinessModel();
            $business = $businessModel->where(array('id'=>$id,'status'=>9))->find();
            if(!$business){
                $this->error("当前业务信息不存在或转化流程有误！");
            }
            $result = $this->conversion_action($id,10,$remark,$business['continuous_day']);//转化
            if ($result == true) {
                $businessModel->isUpdate(true)->save(array('id'=>$id,'status'=>10,'continuous_day'=>0));//更新状态
                $this->success("转化成功！", url("Business/index"));
            } else {
                $this->error("转化失败！");
            }
        }
    }

    //转化操作
    private function conversion_action($business_id,$step,$remark,$continuous_day){
        //获取当前用户信息
        $now_id = cmf_get_current_admin_id();
        $userModel = new UserModel();
        $user_info = $userModel->find($now_id);
        $department = '';
        if($user_info && !empty($user_info['department_id'])){
            $departmentModel = new DepartmentModel();
            $depart = $departmentModel->find($user_info['department_id']);
            if($depart){
                $department = $depart['name'];
            }
        }

        //获取流程信息
        $businessTimeModel = new BusinessTimeModel();
        $business = $businessTimeModel->find($step);

        $businessProcessModel = new BusinessProcessModel();
        $dataInfo = [
            'business_id' => $business_id,
            'name' => $business['name'],
            'step' => $step,
            'day' => $continuous_day,
            'expected_day' => $business['day'],
            'department' => $department,
            'user_id' => $now_id,
            'remark' => $remark
        ];
        $businessProcessModel->allowField(true)->save($dataInfo);
        return true;
    }

    //回退流程
    public function conversion_back(){
        if ($this->request->isPost()) {
            $now_id = intval(cmf_get_current_admin_id());
            if($now_id !== 1){
                $this->error("仅超级管理员才能进行该操作！");
            }
            $id = $this->request->param('id', 0, 'intval');
            $where['id'] = array('eq',$id);
            $where['status'] = array('neq',1);
            $businessModel = new BusinessModel();
            $business = $businessModel->where($where)->find();
            if(!$business){
                $this->error("当前业务信息不存在或禁止回退！");
            }
            if($business['type'] == 2 && $business['status'] == 3){
                $this->error("正式申请业务当前状态禁止回退！");
            }
            //获取业务流程当前状态信息
            $businessProcessModel = new BusinessProcessModel();
            $process = $businessProcessModel->where(array('business_id'=>$id,'step'=>$business['status']))->find();
            //将当前状态天数加回业务连续天数
            $continuous_day = $process['day']+$business['continuous_day'];
            if($business['status'] == 3){
                $dataInfo = [
                    'id' => $id,
                    'number' => null,
                    'house_num' => null,
                    'gas_num' => null,
                    'gas_type' => null,
                    'status' => 2,
                    'continuous_day' => $continuous_day
                ];
            }elseif ($business['status'] == 7){
                $dataInfo = [
                    'id' => $id,
                    'payment' => null,
                    'sms' => 0,
                    'status' => 6,
                    'continuous_day' => $continuous_day
                ];
            }else {
                $dataInfo = [
                    'id' => $id,
                    'status' => $business['status']-1,
                    'continuous_day' => $continuous_day
                ];
            }
            $result = $businessModel->allowField(true)->isUpdate(true)->save($dataInfo);//回退
            if ($result !== false) {
                //删除该流程
                $businessProcessModel->where(array('business_id'=>$id,'step'=>$business['status']))->delete();
                $this->success("回退成功！", url("Business/index"));
            } else {
                $this->error("回退失败！");
            }
        }
    }

    //流程时间管理
    public function time_list(){
//        $this->timed_task();//触发队列任务
        $businessTimeModel = new BusinessTimeModel();
        $list = $businessTimeModel->order('id ASC')->select();
        $this->assign("list",$list);
        return $this->fetch();
    }

    //编辑流程时间
    public function time_post(){
        if ($this->request->isPost()) {
            $now_id = intval(cmf_get_current_admin_id());
            if($now_id !== 1){
                $this->error("仅超级管理员才能进行该操作！");
            }
            $id = $this->request->param('id', 0, 'intval');
            if($id == 1){
                $this->error("预约状态不可修改！");
            }
            $businessTimeModel = new BusinessTimeModel();
            $result = $businessTimeModel->allowField(true)->isUpdate(true)->save($_POST);
            if ($result !== false) {
                $this->success("更新成功！", url("Business/time_list"));
            } else {
                $this->error("更新失败！");
            }
        }
    }

    //定时任务执行触发
    public function timed_task(){
        // 1.当前任务将由哪个类来负责处理。
        //   当轮到该任务时，系统将生成一个该类的实例，并调用其 fire 方法
        $jobHandlerClassName  = 'app\admin\controller\Work';
        // 2.当前任务归属的队列名称，如果为新队列，会自动创建
        $jobQueueName  	  = "workJobQueue";
        // 3.当前任务所需的业务数据 . 不能为 resource 类型，其他类型最终将转化为json形式的字符串
        //   ( jobData 为对象时，需要在先在此处手动序列化，否则只存储其public属性的键值对)
        $jobData = array();
        // 4.将该任务推送到消息队列，等待对应的消费者去执行
        //获取明天凌晨1点时间戳
        $tomorrow_time = strtotime(date('Y-m-d 01:00:00'))+86400;
        $distance = $tomorrow_time-time();
        $isPushed = Queue::later($distance,$jobHandlerClassName, $jobData, $jobQueueName);
        // database 驱动时，返回值为 1|false  ;   redis 驱动时，返回值为 随机字符串|false
        if( $isPushed !== false ){
            echo date('Y-m-d H:i:s') . " a new Work Job is Pushed to the MQ"."<br>";
        }else{
            echo 'Oops, something went wrong.';
        }
    }
}