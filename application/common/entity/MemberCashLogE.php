<?php
namespace app\common\entity;

/**
 * Class User
 * @package app\common\entity
 */
class MemberCashLogE
{
    const TABLE_NAME = 'tp_member_cash_log';
    const SHORT_TABLE_NAME = 'member_cash_log';
    const TYPE = [
        'member_charge' => 1,           //个人充值
        'member_consume' => 2,          //个人消费
        'company_charge_member' => 3,   //公司给个人充值
    ];
    const WAY = [
        'in' => 1,      //资金流入
        'out' => 2,     //资金流出
    ];
    const STATUS = [
        'done' => 1,  //已完成
    ];
    const TIP = [
        'member_charge' => '个人充值',           //个人充值
        'member_consume' => '个人消费',          //个人消费
        'company_charge_member' => '公司给个人充值',   //公司给个人充值
    ];
    const METHOD = [
        'wechat' => 1,      //微信支付
        'cash' => 2,     //余额变动
    ];

}

