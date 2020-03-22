<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人     
 * Date: 2015-09-09
 */
namespace app\admin\controller;


class Commentset extends Base {
    /**
     *  模板列表
     */
    public function index()
    {
        $list = M('order_comment_tip')->select();
        foreach($list as $kl=>$vl){
            $list[$kl]['level_name'] = config('COMMENT_LEVEL')[$vl['level']];
        }
        $this->assign('list',$list);
        return $this->fetch();
    }

    /**
     * 添加修改编辑  短信模板
     */
    public  function addEditSet(){

        $id = I('id/d',0);
        $model = M("order_comment_tip");
        $level_list = [];
        $common_level_set = config('COMMENT_LEVEL');
        foreach($common_level_set as $kc=>$vc){
            $level_list[] = [
                'id'=>$kc,
                'name'=>$vc,
            ];
        }
        $this->assign("level_list" , $level_list );
        if(IS_POST)
        {
         //   $data = I('post');
            $data =$_POST;
            $data['update_time'] = time();
            if($id){
                $model->update($data);
            }else{
                unset($data['id']);
                $id = $model->save($data);
            }
            $this->success("操作成功!!!",U('Admin/Commentset/index'));
            exit;
        }

        if($id){
            //进入编辑页面
            $setInfo = $model->where(" id = ".$id)->find();
            $this->assign("item" , $setInfo );
        }else{
            //进入添加页面
        }
        return $this->fetch("_set");
    }

    /**
     * 删除订单
     */
    public function delSet(){
        $id = I('id');
        $model = M("order_comment_tip");
        $row = $model->where(array('id' => $id))->delete();
        if ($row){
            $return_arr = array('status' => 1,'msg' => '删除成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        }else{
            $return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        }
        $this->ajaxReturn($return_arr,'json');

    }
}