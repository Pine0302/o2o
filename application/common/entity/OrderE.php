<?php
namespace app\common\entity;

/**
 * Class User
 * @package app\common\entity
 */
class OrderE
{
    const TABLE_NAME = 'tp_order';
    const SHORT_TABLE_NAME = 'order';
    const PAY_TYPE = [
        'WEIXIN' => 1,
        'MONEY' => 2,
    ];
    const ORDER_STATUS = [
        'SUBMIT_NO_PAY' => 0,  //已提交未支付
        'PAID' => 1,            //已支付
        'TAKE' => 2,            //商家接单
        'DONE' => 5,            //完成
        'TO_BE_BACK' => 6,      //待退单
        'DONE_BACK' => 7,       //已退单
        'UNDONE_BACK' => 8,       //拒绝退单
    ];
    const PAY_STATUS = [
        'NO' => 0,  //未支付
        'YES' => 1,            //已支付
    ];
    const ORDER_STATUS_TIP = [
         0 => '已提交未支付',  //已提交未支付
         1 => '订单已支付',            //已支付
         2 => '商家已接单',            //已支付
         5 => '订单已完成',            //已支付
         6 => '退单待审核',            //已支付
         7 => '已退单',            //已支付
         8 => '拒绝退单',            //已支付
    ];

}

