<?php

namespace app\admin\controller;

use app\admin\model\BusinessGasModel;
use app\admin\model\BusinessModel;
use app\admin\model\BusinessNatureModel;
use app\admin\model\BusinessProcessModel;
use cmf\controller\AdminBaseController;
use think\Controller;
use think\Request;

class StatisticalController extends AdminBaseController
{
    /**
     * 业务统计
     * @param $request
     * @return \think\Response
     * @throws
     */
    public function business(Request $request)
    {

        $param = $request->param();
        $busNM = new BusinessNatureModel;
        $hs_field = $busNM->column("id,name");
        //气体性质数据集
        $hs_field_data = [];
        $gas_field_data = [];

        $busM = new BusinessModel;


        /*时间筛选*/
        $time = [];
        $startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime = empty($param['end_time']) ? 0 : strtotime($param['end_time']);

        if (!empty($startTime) && !empty($endTime)) {
            $time['create_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $time['create_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $time['create_time'] = ['<= time', $endTime];
            }
        }


        //统计已受理用户
        $sum_sl_hs = $busM->field('sum(house_num) as house_num ,nature_id')
            ->where('status', '>=', 3)
            ->where($time)
            ->order('nature_id')
            ->group('nature_id')
            ->select();


        //加入表头以及初始化数据
        foreach ($hs_field as $id => $name) {
            $hs_field_data[$id] = ['field' => $name, 'sl_house_num' => 0, 'tq_house_num' => 0];
        }

        //加入已受理户数统计
        foreach ($sum_sl_hs as $v) {
            $id = $v['nature_id'];
            $house_num = intval($v['house_num']);
            $hs_field_data[$id]['sl_house_num'] = $house_num;
        }

        //统计已通气用户
        $sum_tq_hs = $busM->field('sum(house_num) as house_num , nature_id')
            ->where('status', '=', 10)
            ->where($time)
            ->order('nature_id')
            ->group('nature_id')
            ->select();


        //加入已通气户数统计
        foreach ($sum_tq_hs as $v) {
            $id = $v['nature_id'];
            $house_num = intval($v['house_num']);
            $hs_field_data[$id]['tq_house_num'] = $house_num;
        }

        //统计数据
        $ytq = 0;
        $ysl = 0;
        foreach ($hs_field_data as $k => $v) {
            $ysl += $v['sl_house_num'];
            $ytq += $v['tq_house_num'];

        }

        $hs_field_data[] = [
            'field' => '总计',
            'sl_house_num' => $ysl,
            'tq_house_num' => $ytq,
        ];


        $busGM = new BusinessGasModel;
        $gas_field = $busGM->column("type,name");

        foreach ($gas_field as $k => $v) {
            $gas_field_data[$k] = ['field' => is_numeric($v) ? "{$v}m³" : $v, 'sl_house_num' => 0, 'tq_house_num' => 0];
        }


        //统计已受理
        $sum_sl_gas = $busM->field('sum(house_num) as house_num ,gas_type')
            ->where('status', '>=', 3)
            ->where($time)
            ->order('gas_type')
            ->group('gas_type')
            ->select();
        //加入已受理
        foreach ($sum_sl_gas as $v) {
            $type = $v['gas_type'];
            $house_num = intval($v['house_num']);
            $gas_field_data[$type]['sl_house_num'] = $house_num;
        }
        //统计已通气
        $sum_tq_gas = $busM->field('sum(house_num) as house_num ,gas_type')
            ->where('status', '=', 10)
            ->where($time)
            ->order('gas_type')
            ->group('gas_type')
            ->select();
        //加入已通气
        foreach ($sum_tq_gas as $v) {
            $type = $v['gas_type'];
            $house_num = intval($v['house_num']);
            $gas_field_data[$type]['tq_house_num'] = $house_num;
        }
        //统计数据
        $ytq = 0;
        $ysl = 0;
        foreach ($gas_field_data as $k => $v) {
            $ysl += $v['sl_house_num'];
            $ytq += $v['tq_house_num'];

        }
        $gas_field_data[] = [
            'field' => '总计',
            'sl_house_num' => $ysl,
            'tq_house_num' => $ytq,
        ];

        $this->assign([
            'hs_field_data' => $hs_field_data, //字段
            'gas_field_data' => $gas_field_data, //字段
        ]);
        return $this->fetch();
    }

    /**
     *超时统计
     * @param $request
     * @return \think\Response
     * @throws
     */
    public function overtime(Request $request)
    {

        $where = array();
        /*时间筛选*/
        $startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime = empty($param['end_time']) ? 0 : strtotime($param['end_time']);

        if (!empty($startTime) && !empty($endTime)) {
            $where['p.create_time'] = [['>= time', $startTime], ['<= time', $endTime]];
        } else {
            if (!empty($startTime)) {
                $where['p.create_time'] = ['>= time', $startTime];
            }
            if (!empty($endTime)) {
                $where['p.create_time'] = ['<= time', $endTime];
            }
        }
        $where['p.step'] = array('gt',1);
        $where['b.limit_type'] = array('eq',1);
        $busM = new BusinessModel;

        $list = $busM->alias('b')
            ->field('b.name as username,u.user_nickname as manager_name,p.*')
            ->join('business_process p', 'p.business_id=b.id')
            ->join('user u', 'u.id= p.user_id')
            ->where('p.day > p.expected_day')
            ->where($where)
            ->order('p.business_id desc')
            ->select();

        $this->assign('list', $list);
        return $this->fetch();
    }

}
