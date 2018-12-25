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
namespace app\admin\validate;

use think\Validate;

class UserValidate extends Validate
{
    protected $rule = [
        'mobile' => 'require|unique:user,mobile|regex:/^1[3456789][0-9]{9}$/',
        'user_nickname'  => 'require',
        'department_id' => 'require',
    ];

    protected $message = [
        'mobile.require' => '手机号不能为空',
        'mobile.unique'  => '手机号已经存在',
        'mobile.regex'  => '请输入正确的手机号',
        'user_nickname.require' => '姓名不能为空',
        'department_id.require' => '部门不能为空'
    ];
}