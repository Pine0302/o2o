<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Session;
use fast\Wx;
use app\common\library\CommonFunc;

/**
 * 工具接口
 */
class Tool extends Api
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     * 
     */
    public function index()
    {
        $this->success('请求成功');
    }

    //获取baidu token api
    public function getBaiduTokenApi(){
        $data = $this->request->post();
        $code = $data['code'];
        if($code=="milktea"){
            $baidu_token = $this->redis->get('baidutoken');
            if(empty($baidu_token)){
                $CommonFuncObj = new CommonFunc();
                $baidu_token = $CommonFuncObj->getBaiduToken();
                if(!empty($baidu_token)){  //存入redis
                    $expire_time = 30*24*60*60-1;
                    $result = $this->redis->set('baidutoken',$baidu_token,$expire_time);
                }
            }
            $data = ['access_token'=>$baidu_token];
            $this->success('success',$data);
        }else{
            $this->error('wrong code');
        }
    }






}
