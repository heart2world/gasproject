<?php

namespace app\admin\model;

use think\Model;

class CollectionPeriodModel extends Model
{
    protected $autoWriteTimestamp=true;
    /**
     * @param $advance_id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfo($advance_id){

        $_money = $this
            ->field('sum(receivable_amount) as receivable_sum,count(id) as period_count')
            ->where('advance_id',$advance_id)
            ->find();
        //总期数统计
        $data['period_count'] = $_money['period_count'];

        //应收款统计
        $data['receivable_sum'] = $_money['receivable_sum'];

        //当前期数计算
        $curr = $this->where(["advance_id"=>$advance_id,'actual_time'=>0])->order('period')->value("period");
        $curr_period = $curr?:$_money['period_count'];
        $data['curr_period'] = $curr_period;

        //是否完成
        $data["has_next"]=$curr?1:0;
        //实际收款统计
        $actual_sum = $this->where(['actual_time'=>['>',0] ,"advance_id"=>$advance_id])->sum('receivable_amount');
        $data['actual_sum'] = $actual_sum ;

        //剩余应收计算
        $data['surplus_sum'] =($_money['receivable_sum']-$actual_sum);

      return $data;
        
    }
}
