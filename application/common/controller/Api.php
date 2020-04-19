<?php

namespace app\common\controller;


use phpDocumentor\Reflection\DocBlock\Tag\VarTag;
use think\Config;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Hook;
use think\Lang;
use think\Loader;
use think\Paginator;
use think\Request;
use think\Response;
use think\Cache;
use think\Db;
use app\common\util\JwtUtil;
//use app\common\library\wx\WXBizDataCrypt;
//use fast\Http;
use think\cache\driver\Redis;

/**
 * API控制器基类
 */
class Api
{

    /**
     * @var Request Request 实例
     */
    protected $request;

    /**
     * @var redis  实例
     */
    public  $redis;

    /**
     * @var bool 验证失败是否抛出异常
     */
    protected $failException = false;

    /**
     * @var bool 是否批量验证
     */
    protected $batchValidate = false;

    /**
     * @var array 前置操作方法列表
     */
    protected $beforeActionList = [];

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = [];

    /**
     * 权限Auth
     * @var Auth 
     */
    protected $auth = null;

    /**
     * 默认响应输出类型,支持json/xml
     * @var string 
     */
    protected $responseType = 'json';

    /**
     * 构造方法
     * @access public
     * @param Request $request Request 对象
     */
    public function __construct(Request $request = null)
    {

        $this->request = is_null($request) ? Request::instance() : $request;

        $this->redis = Cache::getHandler();

        // 控制器初始化
        $this->_initialize();


        // 前置操作方法
        if ($this->beforeActionList)
        {
            foreach ($this->beforeActionList as $method => $options)
            {
                is_numeric($method) ?
                $this->beforeAction($options) :
                $this->beforeAction($method, $options);
            }
        }
    }

    /**
     * 初始化操作
     * @access protected
     */
    protected function _initialize()
    {

        //移除HTML标签
        $this->request->filter('strip_tags');

       // $this->auth = Auth::instance();

        $modulename = $this->request->module();
        $controllername = strtolower($this->request->controller());
        $actionname = strtolower($this->request->action());

        // token
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('token')));

     //   $path = str_replace('.', '/', $controllername) . '/' . $actionname;
        // 设置当前请求的URI
    //    $this->auth->setRequestUri($path);
        // 检测是否需要验证登录
        /*if (!$this->auth->match($this->noNeedLogin))
        {
            //初始化
            $this->auth->init($token);
            //检测是否登录
            if (!$this->auth->isLogin())
            {
                $this->error(__('Please login first'), null, 401);
            }
            // 判断是否需要验证权限
            if (!$this->auth->match($this->noNeedRight))
            {
                // 判断控制器和方法判断是否有对应权限
                if (!$this->auth->check($path))
                {
                    $this->error(__('You have no permission'), null, 403);
                }
            }
        }
        else
        {
            // 如果有传递token才验证是否登录状态
            if ($token)
            {
                $this->auth->init($token);
            }
        }*/

      /*  $upload = \app\common\model\Config::upload();

        // 上传信息配置后
        Hook::listen("upload_config_init", $upload);

        Config::set('upload', array_merge(Config::get('upload'), $upload));

        // 加载当前控制器语言包
        $this->loadlang($controllername);*/
    }


    public function checkSessKey($sess_key){
        var_dump($sess_key);
    }


    /**
     * 操作成功返回的数据
     * @param string $msg   提示信息
     * @param mixed $data   要返回的数据
     * @param int   $error_code   错误码，默认为1
     * @param string $type  输出类型
     * @param array $header 发送的 Header 信息
     */
    protected function success($msg = '', $data = null, $error_code = 0, $type = null, array $header = [])
    {

        $this->result($msg, $data, $error_code, $type, $header);
    }

    /**
     * 操作失败返回的数据
     * @param string $msg   提示信息
     * @param mixed $data   要返回的数据
     * @param int   $error_code   错误码，默认为1
     * @param string $type  输出类型
     * @param array $header 发送的 Header 信息
     */
    protected function error($msg = '', $data = null, $error_code = 1, $type = null, array $header = [])
    {
        $this->result($msg, $data, $error_code, $type, $header);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @access protected
     * @param mixed  $msg    提示信息
     * @param mixed  $data   要返回的数据
     * @param int    $code   错误码，默认为0
     * @param string $type   输出类型，支持json/xml/jsonp
     * @param array  $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function result($msg, $data = null, $error_code = 0, $type = null, array $header = [])
    {
        $result = [
            'error_code' => $error_code,
            'msg'  => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'bizobj' => $data,
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);

        if (isset($header['statuscode']))
        {
            $error_code = $header['statuscode'];
            unset($header['statuscode']);
        }
        else
        {
            //未设置状态码,根据code值判断
            $error_code = $error_code >= 1000 || $error_code < 200 ? 200 : $error_code;
        }
        $response = Response::create($result, $type, $error_code)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * 前置操作
     * @access protected
     * @param  string $method  前置操作方法名
     * @param  array  $options 调用参数 ['only'=>[...]] 或者 ['except'=>[...]]
     * @return void
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only']))
        {
            if (is_string($options['only']))
            {
                $options['only'] = explode(',', $options['only']);
            }

            if (!in_array($this->request->action(), $options['only']))
            {
                return;
            }
        }
        elseif (isset($options['except']))
        {
            if (is_string($options['except']))
            {
                $options['except'] = explode(',', $options['except']);
            }

            if (in_array($this->request->action(), $options['except']))
            {
                return;
            }
        }

        call_user_func([$this, $method]);
    }

    /**
     * 设置验证失败后是否抛出异常
     * @access protected
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    protected function validateFailException($fail = true)
    {
        $this->failException = $fail;

        return $this;
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @param  mixed        $callback 回调方法（闭包）
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate($data, $validate, $message = [], $batch = false, $callback = null)
    {
        if (is_array($validate))
        {
            $v = Loader::validate();
            $v->rule($validate);
        }
        else
        {
            // 支持场景
            if (strpos($validate, '.'))
            {
                list($validate, $scene) = explode('.', $validate);
            }

            $v = Loader::validate($validate);

            !empty($scene) && $v->scene($scene);
        }

        // 批量验证
        if ($batch || $this->batchValidate)
            $v->batch(true);
        // 设置错误信息
        if (is_array($message))
            $v->message($message);
        // 使用回调验证
        if ($callback && is_callable($callback))
        {
            call_user_func_array($callback, [$v, &$data]);
        }

        if (!$v->check($data))
        {
            if ($this->failException)
            {
                throw new ValidateException($v->getError());
            }

            return $v->getError();
        }

        return true;
    }


    //验证是否能取到用户sess_key,取不到用户信息返回code
    public function validSessKey($sess_key=''){
        if(!empty($sess_key)){
            $arr = [  'openid', 'session_key' ];
            $sess_info = $this->redis->hmget($sess_key,$arr);

            $openid_re = $sess_info['openid'];
            $user_list = Db::table('user')
                ->where('openid_re',$openid_re)
                ->field('id,username,mobile,gender,birthday,available_balance')
                ->find();
          //  var_dump($openid_re);
           // var_dump($user_list);
            if(empty($user_list)){
                $this->error('未获取到用户信息', null, 4);exit;
            }
        }else{
            $this->error('缺少必要的参数', null, 2);exit;
        }
    }


    //给被分享的用户加入新team
    public function addUser2Team($sess_key='12212',$ukey = 1){

        $arr = [  'openid', 'session_key' ];
        $sess_info = $this->redis->hmget($sess_key,$arr);
        $openid_re = $sess_info['openid'];
        $user_info = Db::table('user')
            ->where('openid_re',$openid_re)
            ->field('id,username,mobile,gender,birthday,available_balance')
            ->find();
        if(!empty($user_info)){
            $team_info = Db::table('user_team')->where('low_user_id','=',$user_info['id'])->find();

            if(empty($team_info)){
                if($user_info['id']!= base64_decode($ukey)){
                    $arr = [
                        'low_user_id' => $user_info['id'],
                        'up_user_id' => base64_decode($ukey),
                        'create_at' => date("Y-m-d H:i:s",time()),
                        'update_at' => date("Y-m-d H:i:s",time())
                    ];
                    Db::table('user_team')->insert($arr);
                }
            }
        }

    }

    public function wlog($log,$file='test.txt'){
        error_log(var_export($log,1),3,$_SERVER['DOCUMENT_ROOT']."/".$file);
    }

    //获取用户信息
    public function getGUserInfo($openid){
        $arr = [  'openid', 'session_key' ];
        $sess_info = $this->redis->hmget($openid,$arr);
        if(empty($sess_info['openid'])){
            $this->error('lostkey',null,10);exit;
        }
        $openid = $sess_info['openid'];
        $user_info = Db::name('users')
            ->where('openid',$openid)
            ->field('user_id,openid,mobile,mobile_validated,weixin_mobile,coupon_status,sex,type,merch_login,store_id')
            ->find();
        return $user_info;
    }



    //获取用户信息
    public function getTUserInfo($openid){
        $user_info = Db::name('users')
            ->where('openid',$openid)
            ->field('user_id,mobile,user_money,openid')
            ->find();
        return $user_info;
    }

    /**
     * 上传文件
     */
    public function upload()
    {
        Config::set('default_return_type', 'json');
        $file = $this->request->file('file');

        $upload = Config::get('upload');
        
        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix = $suffix ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr = explode('/', $fileInfo['type']);

        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error(__('Uploaded file format is limited'));
        }
        $replaceArr = [
            '{year}'     => date("Y"),
            '{mon}'      => date("m"),
            '{day}'      => date("d"),
            '{hour}'     => date("H"),
            '{min}'      => date("i"),
            '{sec}'      => date("s"),
            '{random}'   => Random::alnum(16),
            '{random32}' => Random::alnum(32),
            '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
            '{suffix}'   => $suffix,
            '{.suffix}'  => $suffix ? '.' . $suffix : '',
            '{filemd5}'  => md5_file($fileInfo['tmp_name']),
        ];
        $savekey = $upload['savekey'];
        $savekey = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName = substr($savekey, strripos($savekey, '/') + 1);
        //
        $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if ($splInfo) {
            $imagewidth = $imageheight = 0;
            if (in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf'])) {
                $imgInfo = getimagesize($splInfo->getPathname());
                $imagewidth = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
                $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
            }
            $params = array(
                'admin_id'    => (int)$this->auth->id,
                'user_id'     => 0,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
            );
            $attachment = model("attachment");
            $attachment->data(array_filter($params));
            $attachment->save();
            \think\Hook::listen("upload_after", $attachment);
            $this->success(__('Upload successful'), null, [
                'url' => $uploadDir . $splInfo->getSaveName()
            ]);
        } else {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }
    }

    //给用户做jwt签名
    public function signUserJwtToken($user_data){
        $jwtUtil = new JwtUtil();
        return $jwtUtil->signToken($user_data);
    }



    //解析用户token
    public function analysisUserJwtToken(){
        $auth_code = $_SERVER['HTTP_AUTHORIZATION'];
        $auth_code = str_replace("Bearer ","",$auth_code);
        $jwtUtil = new JwtUtil();
        if(empty($auth_code)){
            $this->error('token过期,请重新调用login接口', null, 14);exit;
        }
        $userData =  $jwtUtil->analysisToken($auth_code);
        if(!empty($userData->openid)){
            return $userData->openid;
        }else{
            $this->error('token有误,请重新调用login接口', null, 14);exit;
        }
    }

    //把用户存储到redis中
    public function cacheUser($user_data){
        $openid = $user_data['openid'];
        array_walk($user_data,function($value,$key)use($openid){
            $result = $this->redis->hset($openid,$key,$value);
        });
    }


    //获取图片
    public function getImage($image,$image_oss){
        if(!empty($image_oss)){
            return $image_oss;
        }else{
            $server_image_url = "https://".config('server_name').$image;
            return $server_image_url;
        }
    }



}
