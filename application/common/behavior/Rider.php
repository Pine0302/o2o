<?php

/**
 * User: dyr
 * Date: 2017/11/24 0024
 * Time: 下午 3:00
 */

namespace app\common\behavior;
use app\common\logic\wechat\WechatUtil;
use app\common\library\Importer;
use think\Db;
use app\common\repository\CompanyRepository;
use app\common\repository\UserRepository;
class Rider
{
    /**
     * @var CompanyRepository
     */
    private $companyRepository;

    /**
     * @var UserRepository;
     */
    private $userRepository;

    public function __construct()
    {
        $this->companyRepository = new CompanyRepository();
        $this->userRepository = new UserRepository();
    }

    public function run(&$info)
    {
        $file_Url = $info->getData('url');
        $file_path = $_SERVER['DOCUMENT_ROOT']."public".$file_Url;
        $importerObj = new Importer();
        $data = $importerObj->importExecl($file_path);
        //print_r($data);exit;
        $num = count($data)+1;
        $arr = [];
        $company_id = $data[2]['A'];
        for ($i=2;$i<$num;$i++){
            array_push($arr,$data[$i]['B']);
        }
        $result = $this->companyRepository->setRidersNotBelongToCompanyByMobile($company_id,$arr);
























        // 记录订单操作日志
        /*$action_info = array(
            'order_id'        =>$info['order_id'],
            'action_user'     =>0,
            'action_note'     => '您提交了订单，请等待系统确认',
            'status_desc'     =>'提交订单', //''
            'log_time'        =>time(),
        );
        Db::name('order_action')->add($action_info);*/

        // 如果有微信公众号 则推送一条消息到微信.微信浏览器才发消息，否则下单超时。by清华
        /*if(is_weixin()){
            $user = Db::name('OauthUsers')->where(['user_id'=>$order['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            if ($user) {
                $wx_content = "您刚刚下了一笔订单:{$order['order_sn']}!";
                $wechat = new WechatUtil();
                $wechat->sendMsg($user['openid'], 'text', $wx_content);
            }
        }*/

        //用户下单, 发送短信给商家
        /*$res = checkEnableSendSms("3");
        if($res && $res['status'] ==1){
            $sender = tpCache("shop_info.mobile");
            $params = array('consignee'=>$order['consignee'] , 'mobile' => $order['mobile']);
            sendSms("3", $sender, $params);
        }*/
    }

}