<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/18
 * Time: 15:05
 */

namespace app\admin\controller;


use app\admin\model\DepartmentModel;
use app\admin\model\ProjectModel;
use app\admin\model\UserModel;
use cmf\controller\AdminBaseController;

class ProjectController extends AdminBaseController
{
    //内审资料登记
    public function index(){
        $where = array();
        /*搜索条件*/
        $keyword = $this->request->param('keyword','');
        if($keyword){
            $where['u.user_nickname|p.name|us.user_nickname'] = array('like',"%$keyword%");
        }
        $time_type = $this->request->param('time_type',0,'intval');
        $begin_times = $this->request->param('begin_time','');
        $end_times = $this->request->param('end_time','');
        if($time_type){
            $begin_time = 0;
            if($begin_times){
                $begin_time = strtotime($begin_times);
            }
            $end_time = 0;
            if($end_times){
                $end_time = strtotime($end_times);
            }
            if($time_type == 1){//发起时间
                if($begin_time > 0 && $end_time > 0){
                    $where['p.create_time'] = array('between time',[$begin_time,$end_time]);
                }elseif ($begin_time > 0){
                    $where['p.create_time'] = array('>= time',$begin_time);
                }elseif ($end_time > 0){
                    $where['p.create_time'] = array('<= time',$end_time);
                }
            }else{//审核时间
                if($begin_time > 0 && $end_time > 0){
                    $where['p.update_time'] = array('between time',[$begin_time,$end_time]);
                }elseif ($begin_time > 0){
                    $where['p.update_time'] = array('>= time',$begin_time);
                }elseif ($end_time > 0){
                    $where['p.update_time'] = array('<= time',$end_time);
                }
            }
        }
        $depart_id = $this->request->param('depart_id',0,'intval');
        if($depart_id){
            $where['u.department_id'] = array('eq',$depart_id);
        }
        $status = $this->request->param('status',0,'intval');
        if($status){
            ($status == 1) ? $where['p.status'] = array('eq',$status) : $where['p.status'] = array('eq',0);
        }

        $now_id = cmf_get_current_admin_id();

        $projectModel = new ProjectModel();
        $project = $projectModel->alias('p')
            ->join('gas_user u','u.id=p.user_id','LEFT')
            ->join('gas_user us','us.id=p.verify_user_id','LEFT')
            ->where($where)
            ->orderRaw("field(p.user_id,$now_id) desc,p.create_time desc")
            ->field('p.*,u.user_nickname,u.department_id,us.user_nickname verify_name')
            ->paginate(20);
        $project->appends([
            'keyword' => $keyword,
            'time_type' => $time_type,
            'begin_time' => $begin_times,
            'end_time' => $end_times,
            'depart_id' => $depart_id,
            'status' => $status
        ]);
        // 获取分页显示
        $page = $project->render();

        //获取部门信息
        $departmentModel = new DepartmentModel();
        $depart = $departmentModel->select();

        //匹配项目发起部门
        foreach ($project as $k=>$v){
            $project[$k]['department'] = '——';
            foreach ($depart as $m=>$n){
                if($n['id'] == $v['department_id']){
                    $project[$k]['department'] = $n['name'];
                }
            }
            $project[$k]['create_time'] = date('Y-m-d H:i:s',$v['create_time']);
            (empty($v['update_time'])) ? $project[$k]['update_time'] = '——' : $project[$k]['update_time'] = date('Y-m-d H:i:s',$v['update_time']);
            if(empty($v['verify_name'])){
                $project[$k]['verify_name'] = '——';
            }
        }

        $this->assign("project",$project);
        $this->assign("page", $page);
        $this->assign("lists",$project->toArray()['data']);
        $this->assign("depart", $depart);
        return $this->fetch();
    }

    //新增内审资料
    public function add_post(){
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'Project');
            if ($result !== true) {
                $this->error($result);
            } else {
                $now_id = cmf_get_current_admin_id();
                $userModel = new UserModel();
                $user_info = $userModel->find($now_id);
                $dataInfo = [
                    'name' => $this->request->param('name'),
                    'user_id' => $now_id,
                    'department_id' => $user_info['department_id'],
                    'create_time' => time()
                ];
                $projectModel = new ProjectModel();
                $result = $projectModel->allowField(true)->save($dataInfo);
                if ($result !== false) {
                    $this->success("新增成功！", url("Project/index"));
                } else {
                    $this->error("新增失败！");
                }
            }
        }
    }

    //审核内审项目
    public function verify_action(){
        $id = $this->request->param('id', 0, 'intval');
        if (!empty($id)) {
            $now_id = cmf_get_current_admin_id();
            $projectModel = new ProjectModel();
            $result = $projectModel->isUpdate(true)->save(array('id'=>$id,'verify_user_id'=>$now_id,'update_time'=>time(),'status'=>1));
            if ($result !== false) {
                $this->success("审核成功！", url("Project/index"));
            } else {
                $this->error('审核失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }
}