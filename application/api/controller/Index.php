<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\Db;
use think\Session;
use fast\Wx;

/**
 * 首页接口
 */
class Index extends Api
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


    //获取首页的广告
    public function  adv(){
        $data = Db::table('re_adv')->where('id','=',1)->find();
        $response = [
            'url'=>"https://".$_SERVER['HTTP_HOST'].$data['img'],
            'id'=>$data['id'],
            'content'=>$data['content'],
            'content_rec'=>$data['content_rec'],
        ];
        $data = [
            'data'=>$response,
        ];
        $this->success('success', $data);
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




    public function helltest(){
        //$sql1 = "update re_company set frozen = frozen - 1 where id=32";
        $sql1 = "insert  into  re_order set id = 4";
        $result = Db::query($sql1);
        var_dump(Db::getLastInsID());
        var_dump($result);
        exit;
    }

}
