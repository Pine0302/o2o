<?php
namespace app\common\entity;

/**
 * Class User
 * @package app\common\entity
 */
class MerchCashLogE
{
    const TABLE_NAME = 'tp_merch_cash_log';
    const SHORT_TABLE_NAME = 'merch_cash_log';
    const TYPE = [
        'merch_order' => 1,           //商家订单成交增加记录
        'merch_withdraw' => 2,          //商家提现减少记录

    ];
    const WAY = [
        'in' => 1,      //资金流入
        'out' => 2,     //资金流出
    ];
    const STATUS = [
        'frozening'=>0,//冻结中
        'done' => 1,  //冻结结束已到账
        'withdraw'=>2,//提现中
        'withdraw_done'=>3,//提现到账
        'withdraw_undone'=>4,//提现失败被拒
    ];
    const TIP = [
        'merch_order' => '订单收益',           //商家订单成交增加余额
        'merch_withdraw' => '提现',          //商家提现减少余额
    ];

    const STATUS_CH = [
        '0'=>'冻结中',//冻结中
        '1' => '已到帐',  //冻结结束已到账
        '2'=>'提现中',//提现中
        '3'=>'提现到账',//提现到账
        '4'=>'提现失败',//提现失败被拒
    ];

}

