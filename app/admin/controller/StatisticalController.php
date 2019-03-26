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
    public function overtime()
    {

        $where = array();
        /*时间筛选*/
        $param = $this->request->param();
        $startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime = empty($param['end_time']) ? 0 : strtotime($param['end_time']." 23:59:59");

        if (!empty($startTime) && !empty($endTime)) {
            $where['p.create_time'] = ['between time', [$startTime, $endTime]];
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

        $businessProcessModel = new BusinessProcessModel();
        $list = $businessProcessModel->alias('p')
            ->join('gas_business b','b.id=p.business_id','LEFT')
            ->join('gas_user u','u.id=p.user_id','LEFT')
            ->where('p.day > p.expected_day')
            ->where($where)
            ->order('p.business_id desc')
            ->field('p.*,b.name as username,u.user_nickname as manager_name')
            ->paginate(20);
        $list->appends($param);
        // 获取分页显示
        $page = $list->render();

        $this->assign("lists",$list->toArray()['data']);
        $this->assign('list', $list);
        $this->assign("page",$page);
        return $this->fetch();
    }


    public  function download_excel(){
        $where = array();
        /*时间筛选*/
        $param = $this->request->param();
        $startTime = empty($param['start_time']) ? 0 : strtotime($param['start_time']);
        $endTime = empty($param['end_time']) ? 0 : strtotime($param['end_time']." 23:59:59");

        if (!empty($startTime) && !empty($endTime)) {
            $where['p.create_time'] = ['between time', [$startTime, $endTime]];
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
        $businessProcessModel = new BusinessProcessModel();
        $list = $businessProcessModel->alias('p')
            ->join('gas_business b','b.id=p.business_id','LEFT')
            ->join('gas_user u','u.id=p.user_id','LEFT')
            ->where('p.day > p.expected_day')
            ->where($where)
            ->order('p.business_id desc')
            ->field('p.*,b.name as username,u.user_nickname as manager_name')
            ->select();
        $this->daysales_excel($list);
    }

    /**
     * @param $driver
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    function daysales_excel($driver){
        //导出表格
        $styleThinBlackBorderOutline = array(
            'borders' => array(
                'allborders' => array( //设置全部边框
                    'style' => \PHPExcel_Style_Border::BORDER_THIN //粗的是thick
                ),

            ),
        );
        $objExcel = new \PHPExcel();
        $objWriter = \PHPExcel_IOFactory::createWriter($objExcel, 'Excel5');
        // 设置水平垂直居中
        $objExcel->getActiveSheet()->getDefaultStyle()->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objExcel->getActiveSheet()->getDefaultStyle()->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        // 字体和样式
        $objExcel->getActiveSheet()->getDefaultStyle()->getFont()->setSize(10);
        $objExcel->getActiveSheet()->getStyle('A2:AB2')->getFont()->setBold(true);
        $objExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
        // 第一行、第二行的默认高度
        $objExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(30);
        $objExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(20);
        //设置某一列的宽度
        $objExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $objExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $objExcel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
        $objExcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);
        $objExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        //设置表头
        //  合并

       // ["预收账款编号","客户名称","所属业务编号","账款总额","账款总期数","当前收款额","当前期数","剩余收款额","下次交款日期","负责人","状态"]
        $objExcel->getActiveSheet()->mergeCells('A1:I1');
        $objActSheet = $objExcel->getActiveSheet(0);

        //
        $objActSheet->setTitle('超时统计');//设置excel的标题
        $objActSheet->setCellValue('A1','超时统计');
        $objActSheet->setCellValue('A2','客户名称');
        $objActSheet->setCellValue('B2','状态名称');
        $objActSheet->setCellValue('C2','完成时间');
        $objActSheet->setCellValue('D2','预计耗时(天)');
        $objActSheet->setCellValue('E2','实际耗时(天)');
        $objActSheet->setCellValue('F2','受理部门');
        $objActSheet->setCellValue('G2','责任人');
        $objActSheet->setCellValue('H2','备注');
        $objExcel->getActiveSheet()->getStyle( 'A1:H'.(sizeof($driver)+2))->applyFromArray($styleThinBlackBorderOutline);
        $baseRow = 3; //数据从N-1行开始往下输出 这里是避免头信息被覆盖

        foreach ( $driver as $r => $d ) {
            $i = $baseRow + $r;
            $objExcel->getActiveSheet()->setCellValue('A'.$i,$d['username']);
            $objExcel->getActiveSheet()->setCellValue('B'.$i,$d['name']);
            $objExcel->getActiveSheet()->setCellValue('C'.$i,date("Y-m-dH:i:s",$d['create_time']));
            $objExcel->getActiveSheet()->setCellValue('D'.$i,$d['expected_day']);
            $objExcel->getActiveSheet()->setCellValue('E'.$i,$d['day']);
            $objExcel->getActiveSheet()->setCellValue('F'.$i,$d['department']);
            $objExcel->getActiveSheet()->setCellValue('G'.$i,$d['manager_name']);
            $objExcel->getActiveSheet()->setCellValue('H'.$i,$d['remark']);
        }
        $objExcel->setActiveSheetIndex(0);
        //4、输出
        $objExcel->setActiveSheetIndex();
        header('Content-Type: applicationnd.ms-excel');
        $time=date('YmdHis');
        header("Content-Disposition: attachment;filename=超时统计_$time.xls");
        header('Cache-Control: max-age=0');
        $objWriter->save('php://output');
    }

}
