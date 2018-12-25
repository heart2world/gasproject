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

class BusinessValidate extends Validate
{
    protected $rule = [
        'number' => 'require|alphaNum|unique:business,number',
        'name'  => 'require',
        'address' => 'require',
        'contact' => 'require',
        'contact_mobile'  => 'require|regex:\d{11}',
        'house_num' => 'require|regex:/^\d+$/',
        'gas_num' => 'require|number|egt:0'
    ];

    protected $message = [
        'number.require' => '业务编号不能为空',
        'number.alphaNum' => '业务编号只能输入字母和数字',
        'number.unique' => '业务编号已经存在',
        'name.require'  => '客户名称不能为空',
        'address.require' => '安装地址不能为空',
        'contact.require' => '联系人姓名不能为空',
        'contact_mobile.require' => '联系电话不能为空',
        'contact_mobile.regex'  => '请输入正确的联系电话',
        'house_num.require' => '包含户数不能为空',
        'house_num.regex' => '包含户数只能为0或正整数',
        'gas_num.require' => '合计用气量不能为空',
        'gas_num.number' => '合计用气量只能为数字',
        'gas_num.egt' => '合计用气量必须大于等于0'
    ];

    protected $scene = [
        'reservation'  =>  ['name','address','contact','contact_mobile'],
        'formal'  =>  ['number','name','address','contact','contact_mobile','house_num','gas_num'],
        'conversion' => ['number','house_num','gas_num']
    ];
}