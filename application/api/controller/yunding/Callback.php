<?php

namespace app\api\controller\yunding;

use app\common\controller\Api;
use think\Db;
use think\Session;
use fast\Wx;

/**
 * 首页接口
 */
class Callback extends Api
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function test()
    {
        var_dump(123);exit;
        $this->success('请求成功');
    }


}