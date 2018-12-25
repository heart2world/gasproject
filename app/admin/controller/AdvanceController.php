<?php

namespace app\admin\controller;

use app\admin\model\AdvanceModel;
use app\admin\model\BusinessModel;
use app\admin\model\CollectionPeriodModel;
use app\user\model\UserModel;
use cmf\controller\AdminBaseController;
use think\Db;
use think\Model;
use think\Request;

class AdvanceController extends AdminBaseController
{

    /**
     * @var Model
     */
    private $model = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        $this->model= new AdvanceModel();
    }

    /**
     * 显示资源列表
     *
     * @throws
     *
     */
    public function index()
    {

        $where = [];
        $request = $this->request->param();
        /**搜索条件**/
        $keyword = $this->request->param('keyword');
        $status = $this->request->param('status');

        //时间条件查询
        $last_time   = empty($request['last_time']) ? 0 : strtotime($request['last_time']);
        $next_time   = empty($request['next_time']) ? 0 : strtotime($request['next_time']);

        if (!empty($last_time) && !empty($next_time))
        {
            $where['next_payment_time'] = ['between time', [$last_time,$next_time]];
        }elseif (!empty($last_time)){
            $where['next_payment_time'] = ['between time', [$last_time,2147483640]];
        }elseif (!empty($next_time)){
            $where['next_payment_time'] = ['<= time',$next_time];
        }

        if ($keyword) {
            $where['name|business_number'] = ['like', "%$keyword%"];
        }

        if ($status) {
            $where['a.status'] = $status;
        }


        $list = $this->model
            ->field("a.*,b.name")
            ->alias("a")
            ->join("business b",'a.business_number=b.number')
            ->where($where)
            ->order(["next_payment_time"=>'asc','id'=>'asc'])
            ->paginate(10);


        $perModel =new CollectionPeriodModel;
        foreach ($list as $k=> $value){
            //负责人查询
            $list[$k]['manager_name'] = $value['manager_id']?UserModel::get($value['manager_id'])->user_nickname:"";
            $list[$k] = array_merge( $list[$k]->toArray() , $perModel->getInfo($value['id']));
        }


        $list->appends(['keyword' => $keyword,'last_time'=>input('last_time'),'next_time'=>input('next_time'),'status'=>$status]);
        // 获取分页显示
        $page = $list->render();


        $this->assign("page", $page);
        $this->assign("list", $list);
        return $this->fetch();
    }

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create()
    {
       return $this->fetch();
    }

    /**
     * 保存新建的资源
     * @param  \think\Request  $request
     * @throws
     */
    public function save(Request $request)
    {
        $data = $request->param();

        //数据验证
        $vf  = $this->validate($data,'Advance');

        if ($vf!==true){
            $this->error($vf);
        }

       $business = BusinessModel::get(['number'=>$data['business_number']]);

        if (empty($business)){
            $this->error('请输入正确的业务编号!');
        }

        $list = $request->param('list/a');
        unset($data['list']);
        $data['manager_id'] = cmf_get_current_admin_id();
        $data['create_time'] = time();
        $advance_id = $this->model->isAutoWriteTimestamp(true)->insertGetId($data);

        if (!$advance_id){
            $this->error('添加失败 !');
        }
        $next_time = 0;
        if (sizeof($list)<1){
            $this->error('请至少新增一期账款');
        }else{
            //时间格式转换

            foreach ($list as $key=>$item){
                if ($item['receivable_time'] && $item['receivable_time'] = strtotime($item['receivable_time'])){
                     /*  if ($item['receivable_time']<time()){
                        $this->error("请输入正确的收款时间");
                    }*/
                    if ($item['period'] == 1){
                        $next_time =$item['receivable_time'] ;
                    }
                }
                $item['advance_id']=$advance_id;
                $list[$key]=$item;
            }
        }

        $period = new CollectionPeriodModel();
        $periodRes = $period->saveAll($list);

        if ($periodRes){
            $this->model->where('id',$advance_id)->setField('next_payment_time',$next_time);
            $this->success('添加成功',url($request->controller()."/index"));
        }else{

            $this->error("添加失败");
        }

    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return mixed
     * @throws
     */
    public function read($id)
    {
        $data = $this->model->where('id',$id)->find();
        if ($this->checkAuth($data['manager_id'])){
            $this->error("你无权访问此页!");
        }
        if ($data){

            $perModel =new CollectionPeriodModel;
            $data = $data->toArray();
            $user = BusinessModel::get(['number'=>$data['business_number'] ] );
            $manager = UserModel::get( $data['manager_id']);
            $data['name'] = $user ? $user->name:"";
            $data['manager_name']  = $manager? $manager->user_nickname:"";

            //登录
            $data['list']=$perModel->where('advance_id',$id)->select();
            $data = array_merge($data, $perModel->getInfo($id));
            $this->assign($data);
        }else{
            $this->error("数据不存在!");
        }

        return $this->fetch();
    }

    /**
     * 显示编辑资源表单页.
     * @param $id
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function edit($id)
    {
        $data = $this->model->where('id',$id)->find();
        $perModel =new CollectionPeriodModel;

        if ($this->checkAuth($data['manager_id'])){
            $this->error("你无权访问此页!");
        }
        if ($this->request->isAjax()){
             return $perModel
                 ->field('id,actual_time,period,receivable_amount,receivable_time')
                 ->where('advance_id',$id)->order('period')
                 ->select();
        }

        if ($data){


            $data  = $data->toArray();
            $user = BusinessModel::get(['number'=>$data['business_number'] ] );
            $manager = UserModel::get( $data['manager_id']);

            $data['name'] = $user ? $user->name:"";
            $data['manager_name']  = $manager? $manager->user_nickname:"";


            $data = array_merge($data, $perModel->getInfo($id));

            $this->assign($data);
        }
        else{
            $this->error("数据不存在!");
        }
        return $this->fetch();
    }


    /**
     * 操作收款
     * @param Request $request
     * @param $id
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    public function next_period(Request $request,$id){

        $period_model =new CollectionPeriodModel;


        $w = ["advance_id"=>$id,'actual_time'=>0 ];
        //当前期
        $next_data = $period_model->where($w)->order('period')->find();
        //最后一期
        $last_period =  $period_model->where('advance_id',$id)->count();

        if ($request->post('save')){

            $period_id = $request->param('period_id');
            $result =  $period_model->where('id',$period_id)->setField('actual_time',time());
            if ($result){

                if ( $period_model->where($w)->order('period')->count()==0){
                    $this->model->where('id',$id)->setField(['next_payment_time'=>2147483647,'status'=>2]);
                }else{
                    //下一期时间
                    $next_data = $period_model->where($w)->order('period')->find();
                    $this->model->where('id',$id)->setField('next_payment_time',$next_data['receivable_time']);
                }
                $this->success("操作成功!");
            }
            $this->error("操作失败!");
        }else{

            $next_data['receivable_time']=date('Y-m-d', $next_data['receivable_time']);
            $this->success('success',null,$next_data);
        }



    }



    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @throws
     */
    public function update(Request $request)
    {

        $data = $request->param('list/a');

        if (count($data)<1){
            $this->error("提交失败! 请至少保留一条数据");
        }
        $rem_ids = $request->param('del/a');


        foreach ($data as $key=>$item){
            $item['receivable_time'] = strtotime($item['receivable_time']);
            $item['actual_time'] = strtotime($item['actual_time']);
            $data[$key]=$item;
        }

        $period_model =new CollectionPeriodModel;

        $result  = $period_model->isUpdate(true)->saveAll($data);
        if ($rem_ids){
            $period_model->whereIn('id',$rem_ids)->delete();
        }

        if ($result){
            $this->success('修改成功!');
        }else{
            $this->error("修改失败");
        }
    }

    /**
     * 删除指定资源
     * @param  int  $id
     */
    public function delete($id)
    {

        $result  =$this->model->where('id',$id)->delete();

        if ($result){
            CollectionPeriodModel::destroy(['advance_id'=>$id]);
            $this->success('删除成功!');
        }else{
            $this->error("删除失败");
        }
    }

    function checkAuth($id){
        return !in_array(cmf_get_current_admin_id(),[$id,1]);
    }
}
