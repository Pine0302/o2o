<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Session;
use fast\Wx;

/**
 * 首页接口
 */
class Web extends Api
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

//测试获取用户列表接口
    public function testApiGetUserList(){
        $data = $this->request->request();
        $page = isset($data['page']) ? $data['page'] : 1;
        $page_size = isset($data['page_size']) ? $data['page_size'] : 2;

        $data = Db::table('user')->page($page,$page_size)->select();
        $count = Db::table('user')->count();
        $arr = array();
        foreach($data as $kd=>$vd){
            $arr[] = [
                'id'=>$vd['id'],
                'username'=>$vd['username'],
                'nickname'=>$vd['nickname'],
                'mobile'=>$vd['mobile'],
                'avatar'=>$vd['avatar'],
                'birthday'=>$vd['birthday'],
            ];
        }
        $page_info = [
            'cur_page'=>$page,
            'page_size'=>$page_size,
            'total_items'=>$count,
            'total_pages'=>ceil($count/$page_size)
        ];
        $data = [
            'data'=>$arr,
            'page_info'=>$page_info,
        ];
        $this->success('success', $data);
    }






}
