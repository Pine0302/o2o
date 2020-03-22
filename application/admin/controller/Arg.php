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
 * Author: wangqh
 * Date: 2015-09-09
 *  阿里大鱼短信模板管理
 */
namespace app\admin\controller; 

class Arg extends Base{

    public  $send_scene;
    
    public function _initialize() {
        parent::_initialize();
        
        // 短信使用场景
     /*   $this->send_scene = C('SEND_SCENE');
        $this->assign('send_scene', $this->send_scene);*/
        
    }
    
    public function index(){        
      /* $smsTpls = M('sms_template')->select();
        $this->assign('smsTplList',$smsTpls);*/
    /*    return $this->fetch("sms_template_list");*/

        $args = M('store_arg')->select();
        //var_dump($args);
        $this->assign('args',$args);
        return $this->fetch("args");
    }
    
    /**
     * 添加修改编辑  短信模板
     */
    public  function addEditArgs(){
        
        $id = I('id/d',0);
        $model = M("store_arg");
        
        if(IS_POST)
        {    
            $data = I('post.');
            $data['create_at'] = time();
            
            if($id){
                $model->update($data);
            }else{
                $id = $model->save($data);
            }
            $this->success("操作成功!!!",U('Admin/Arg/index'));
            exit;
        } 
        
        
        if($id){
            //进入编辑页面
            $smsTemplate = $model->where(" id = ".$id)->find();
            $this->assign("arg" , $smsTemplate );
        }else{
            //进入添加页面
        }
        return $this->fetch("_arg");
    }
    
    /**
     * 删除订单
     */
   public function delTemplate(){
       $id = I('id');
       $model = M("sms_template");
       $row = $model->where(array('tpl_id' => $id))->delete();
       if ($row){
           $return_arr = array('status' => 1,'msg' => '删除成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
       }else{
           $return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);  
       } 
       $this->ajaxReturn($return_arr,'json');
       
   }

}