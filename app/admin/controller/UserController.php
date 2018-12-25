<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;

use app\admin\model\AdminMenuModel;
use app\admin\model\AuthAccessModel;
use app\admin\model\DepartmentModel;
use app\admin\model\RoleModel;
use app\admin\model\RoleUserModel;
use app\admin\model\UserModel;
use cmf\controller\AdminBaseController;
use think\Db;
use tree\Tree;

/**
 * Class UserController
 * @package app\admin\controller
 * @adminMenuRoot(
 *     'name'   => '管理组',
 *     'action' => 'default',
 *     'parent' => 'user/AdminIndex/default',
 *     'display'=> true,
 *     'order'  => 10000,
 *     'icon'   => '',
 *     'remark' => '管理组'
 * )
 */
class UserController extends AdminBaseController
{

    /**
     * 管理员列表
     * @adminMenu(
     *     'name'   => '管理员',
     *     'parent' => 'default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员管理',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $content = hook_one('admin_user_index_view');
        if (!empty($content)) {
            return $content;
        }

        $where = ["user_type" => 1];
        /**搜索条件**/
        $keyword = $this->request->param('keyword','');
        if ($keyword) {
            $where['mobile|user_nickname'] = array('like', "%$keyword%");
        }
        $depart_id = $this->request->param('depart_id','','intval');
        if ($depart_id) {
            $where['department_id'] = array('eq', $depart_id);
        }
        $status = $this->request->param('status','');
        if($status != ''){
            ($status == 1) ? $where['user_status'] = array('eq', $status) : $where['user_status'] = array('eq',0);
        }
        $userModel = new UserModel();
        $users = $userModel->where($where)->order("id ASC")->paginate(20);
        $users->appends(['keyword' => $keyword,'depart_id'=>$depart_id,'status'=>$status]);
        // 获取分页显示
        $page = $users->render();

        //获取部门信息
        $departmentModel = new DepartmentModel();
        $depart = $departmentModel->select();

        //匹配用户部门
        foreach ($users as $k=>$v){
            $users[$k]['department'] = '——';
            foreach ($depart as $m=>$n){
                if($v['department_id'] == $n['id']){
                    $users[$k]['department'] = $n['name'];
                }
            }
        }

        $this->assign("page", $page);
        $this->assign("depart", $depart);
        $this->assign("users", $users);
        $this->assign("lists",$users->toArray()['data']);
        return $this->fetch();
    }

    /**
     * 管理员添加
     * @adminMenu(
     *     'name'   => '管理员添加',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员添加',
     *     'param'  => ''
     * )
     */
    public function add()
    {
        $content = hook_one('admin_user_add_view');
        if (!empty($content)) {
            return $content;
        }
        //获取部门信息
        $departmentModel = new DepartmentModel();
        $depart = $departmentModel->select();

        //获取权限列表
        $category = $this->auth_list();

        $this->assign("category", $category);
        $this->assign("depart", $depart);
        return $this->fetch();
    }

    //获取权限列表
    private function auth_list($roleId = ''){
        $AuthAccess     = Db::name("AuthAccess");
        $adminMenuModel = new AdminMenuModel();

        $tree       = new Tree();
        $tree->icon = ['│ ', '├─ ', '└─ '];
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';

        $result = $adminMenuModel->where(array('shows'=>1))->order("list_order", "ASC")->column('');

        $newMenus      = [];
        if($roleId) {
            $privilegeData = $AuthAccess->where(["role_id" => $roleId])->column("rule_name");//获取权限表数据
        }else{
            $privilegeData = array();
        }

        foreach ($result as $m) {
            $newMenus[$m['id']] = $m;
        }

        foreach ($result as $n => $t) {
            $result[$n]['checked']      = ($this->_isChecked($t, $privilegeData)) ? ' checked' : '';
            $result[$n]['level']        = $this->_getLevel($t['id'], $newMenus);
            $result[$n]['style']        = empty($t['parent_id']) ? '' : 'display:none;';
            $result[$n]['parentIdNode'] = ($t['parent_id']) ? ' class="child-of-node-' . $t['parent_id'] . '"' : '';
        }

        $str = "<tr id='node-\$id'\$parentIdNode  style='\$style'>
                   <td style='padding-left:30px;'>\$spacer<input type='checkbox' name='menuId[]' value='\$id' level='\$level' \$checked onclick='javascript:check_node(this);'> \$name</td>
    			</tr>";
        $tree->init($result);

        $category = $tree->getTree(0, $str);
        return $category;
    }

    /**
     * 检查指定菜单是否有权限
     * @param array $menu menu表中数组
     * @param $privData
     * @return bool
     */
    private function _isChecked($menu, $privData)
    {
        $app    = $menu['app'];
        $model  = $menu['controller'];
        $action = $menu['action'];
        $name   = strtolower("$app/$model/$action");
        if ($privData) {
            if (in_array($name, $privData)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取菜单深度
     * @param $id
     * @param array $array
     * @param int $i
     * @return int
     */
    protected function _getLevel($id, $array = [], $i = 0)
    {
        if ($array[$id]['parent_id'] == 0 || empty($array[$array[$id]['parent_id']]) || $array[$id]['parent_id'] == $id) {
            return $i;
        } else {
            $i++;
            return $this->_getLevel($array[$id]['parent_id'], $array, $i);
        }
    }

    /**
     * 管理员添加提交
     * @adminMenu(
     *     'name'   => '管理员添加提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员添加提交',
     *     'param'  => ''
     * )
     */
    public function addPost()
    {
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'User');
            if ($result !== true) {
                $this->error($result);
            } else {
                //检查权限
                $auth_list = $this->request->param('menuId/a');
                if(!is_array($auth_list) || count($auth_list) < 1){
                    $this->error("请选择至少一项权限！");
                }
                unset($_POST['menuId']);
                //添加用户
                $mobile = $this->request->param('mobile');
                $_POST['user_login'] = $mobile;
                $_POST['user_pass'] = cmf_password(substr($mobile,-6));
                $_POST['create_time'] = time();
                $userModel = new UserModel();
                $user_id = $userModel->allowField(true)->insertGetId($_POST);
                if ($user_id !== false) {
                    //给该用户添加角色
                    $roleModel = new RoleModel();
                    $role_id = $roleModel->insertGetId(array('status'=>1,'name'=>$mobile,'remark'=>$mobile.'的角色','create_time'=>time()));
                    //删除权限
                    $authAccessModel = new AuthAccessModel();
                    $authAccessModel->where(["role_id" => $role_id, 'type' => 'admin_url'])->delete();
                    //添加权限
                    $authData = array();
                    $adminMenuModel = new AdminMenuModel();
                    foreach ($auth_list as $menuId) {
                        $menu = $adminMenuModel->where(["id" => $menuId])->field("app,controller,action")->find();
                        if ($menu) {
                            $app    = $menu['app'];
                            $model  = $menu['controller'];
                            $action = $menu['action'];
                            $name   = strtolower("$app/$model/$action");
                            $authData[] = array('role_id'=>$role_id,'rule_name'=>$name,'type' => 'admin_url');
                        }
                    }
                    if($authData){
                        $authAccessModel->saveAll($authData);
                    }
                    //将权限与用户绑定
                    $roleUserModel = new RoleUserModel();
                    $roleUserModel->save(array('role_id'=>$role_id,'user_id'=>$user_id));

                    $this->success("添加成功！", url("user/index"));
                } else {
                    $this->error("添加失败！");
                }
            }
        }
    }

    /**
     * 管理员编辑
     * @adminMenu(
     *     'name'   => '管理员编辑',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员编辑',
     *     'param'  => ''
     * )
     */
    public function edit()
    {
        $content = hook_one('admin_user_edit_view');

        if (!empty($content)) {
            return $content;
        }

        $id    = $this->request->param('id', 0, 'intval');
        $userModel = new UserModel();
        $user = $userModel->find($id);
        if(!$user){
            $this->error("不存在该用户信息");
        }
        $roleUserModel = new RoleUserModel();
        $relation = $roleUserModel->where(array('user_id'=>$id))->find();
        if(!$relation){
            $this->error("数据有误,请联系网站管理员");
        }
        //获取部门信息
        $departmentModel = new DepartmentModel();
        $depart = $departmentModel->select();
        foreach ($depart as $k=>$v){
            ($v['id'] == $user['department_id']) ? $depart[$k]['check'] = 'selected' : $depart[$k]['check'] = '';
        }

        //获取权限列表
        $category = $this->auth_list($relation['role_id']);

        $this->assign("category", $category);
        $this->assign("depart", $depart);
        $this->assign("user",$user);
        return $this->fetch();
    }

    /**
     * 管理员编辑提交
     * @adminMenu(
     *     'name'   => '管理员编辑提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员编辑提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {
        if ($this->request->isPost()) {
            if ($this->request->isPost()) {
                $result = $this->validate($this->request->param(), 'User');
                if ($result !== true) {
                    $this->error($result);
                } else {
                    $user_id = $this->request->param('id');
                    //检查权限
                    $auth_list = $this->request->param('menuId/a');
                    if(!is_array($auth_list) || count($auth_list) < 1){
                        $this->error("请选择至少一项权限！");
                    }
                    unset($_POST['menuId']);
                    //查询该用户角色关联
                    $roleUserModel = new RoleUserModel();
                    $relation = $roleUserModel->where(array('user_id'=>$user_id))->find();
                    if(!$relation){
                        $this->error("数据有误,请联系网站管理员");
                    }
                    //更新用户
                    $mobile = $this->request->param('mobile');
                    $_POST['user_login'] = $mobile;
                    $_POST['user_pass'] = cmf_password(substr($mobile,-6));
                    $userModel = new UserModel();
                    $res = $userModel->allowField(true)->isUpdate(true)->save($_POST);
                    if ($res !== false) {
                        $role_id = $relation['role_id'];//角色id
                        //删除权限
                        $authAccessModel = new AuthAccessModel();
                        $authAccessModel->where(["role_id" => $role_id, 'type' => 'admin_url'])->delete();
                        //添加权限
                        $authData = array();
                        $adminMenuModel = new AdminMenuModel();
                        foreach ($auth_list as $menuId) {
                            $menu = $adminMenuModel->where(["id" => $menuId])->field("app,controller,action")->find();
                            if ($menu) {
                                $app    = $menu['app'];
                                $model  = $menu['controller'];
                                $action = $menu['action'];
                                $name   = strtolower("$app/$model/$action");
                                $authData[] = array('role_id'=>$role_id,'rule_name'=>$name,'type' => 'admin_url');
                            }
                        }
                        if($authData){
                            $authAccessModel->saveAll($authData);
                        }
                        //修改角色信息
                        $roleModel = new RoleModel();
                        $roleModel->isUpdate(true)->save(array('id'=>$role_id,'name'=>$mobile,'remark'=>$mobile.'的角色'));

                        $this->success("更新成功！", url("user/index"));
                    } else {
                        $this->error("更新失败！");
                    }
                }
            }
        }
    }

    /**
     * 管理员重置密码
     * @adminMenu(
     *     'name'   => '管理员重置密码',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员重置密码',
     *     'param'  => ''
     * )
     */
    public function reset_password()
    {
        $id = $this->request->param('id', 0, 'intval');
        $userModel = new UserModel();
        $user = $userModel->where(array('user_type'=>1))->find($id);
        if(!$user){
            $this->error("不存在该用户信息！");
        }
        if ($user['id'] == 1) {//超级管理员
            $userModel->isUpdate(true)->save(array('id'=>$user['id'],'user_pass'=>cmf_password('123456')));
        } else {
            $userModel->isUpdate(true)->save(array('id'=>$user['id'],'user_pass'=>cmf_password(substr($user['mobile'],-6))));
        }
        $this->success("重置成功！");
    }

    /**
     * 停用管理员
     * @adminMenu(
     *     'name'   => '停用管理员',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '停用管理员',
     *     'param'  => ''
     * )
     */
    public function ban()
    {
        $id = $this->request->param('id', 0, 'intval');
        if (!empty($id)) {
            $result = Db::name('user')->where(["id" => $id, "user_type" => 1])->setField('user_status', '0');
            if ($result !== false) {
                $this->success("停用成功！", url("user/index"));
            } else {
                $this->error('停用失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    /**
     * 启用管理员
     * @adminMenu(
     *     'name'   => '启用管理员',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '启用管理员',
     *     'param'  => ''
     * )
     */
    public function cancelBan()
    {
        $id = $this->request->param('id', 0, 'intval');
        if (!empty($id)) {
            $result = Db::name('user')->where(["id" => $id, "user_type" => 1])->setField('user_status', '1');
            if ($result !== false) {
                $this->success("启用成功！", url("user/index"));
            } else {
                $this->error('启用失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    //部门管理
    public function department(){
        $departmentModel = new DepartmentModel();
        $depart = $departmentModel->order('create_time asc')->paginate(20);
        // 获取分页显示
        $page = $depart->render();
        $this->assign("depart",$depart);
        $this->assign("page",$page);
        $this->assign("lists",$depart->toArray()['data']);
        return $this->fetch();
    }

    //新增部门
    public function depart_add(){
        return $this->fetch();
    }

    //新增部门提交
    public function depart_add_post(){
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'Department');
            if ($result !== true) {
                $this->error($result);
            } else {
                $departmentModel = new DepartmentModel();
                $result = $departmentModel->allowField(true)->save($_POST);
                if ($result !== false) {
                    $this->success("添加成功！", url("user/department"));
                } else {
                    $this->error("添加失败！");
                }
            }
        }
    }

    //编辑部门
    public function depart_edit(){
        $id = $this->request->param('id', 0, 'intval');
        $departmentModel = new DepartmentModel();
        $depart = $departmentModel->find($id);
        if(!$depart){
            $this->error("不存在当前部门信息！");
        }
        $this->assign("depart",$depart);
        return $this->fetch();
    }

    //编辑部门提交
    public function depart_edit_post(){
        if ($this->request->isPost()) {
            $result = $this->validate($this->request->param(), 'Department');
            if ($result !== true) {
                $this->error($result);
            } else {
                $departmentModel = new DepartmentModel();
                $result = $departmentModel->allowField(true)->isUpdate(true)->save($_POST);
                if ($result !== false) {
                    $this->success("更新成功！", url("user/department"));
                } else {
                    $this->error("更新失败！");
                }
            }
        }
    }

    //删除部门
    public function depart_delete(){
        $id = $this->request->param('id', 0, 'intval');
        $departmentModel = new DepartmentModel();
        $depart = $departmentModel->find($id);
        if(!$depart){
            $this->error("不存在当前部门信息！");
        }
        //查询该部门下是否有用户
        $userModel = new UserModel();
        $count = $userModel->where(array('department_id'=>$id))->count();
        if($count > 0){
            $this->error("无法删除部门下有员工的部门！");
        }
        $departmentModel->where(array('id'=>$id))->delete();
        $this->success("删除成功");
    }
}