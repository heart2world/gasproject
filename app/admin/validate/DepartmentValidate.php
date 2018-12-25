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

class DepartmentValidate extends Validate
{
    protected $rule = [
        'name' => 'require|unique:department,name',
        'telephone'  => 'require|unique:department,name|regex:\d{11}',
    ];
    protected $message = [
        'name.require' => '部门名称不能为空',
        'name.unique'  => '部门名称已经存在',
        'telephone.require'  => '部门电话不能为空',
        'telephone.unique'  => '部门电话已经存在',
        'telephone.regex' => '部门电话请输入11位数字'
    ];
}