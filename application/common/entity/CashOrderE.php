<?php
namespace app\common\entity;

/**
 * Class User
 * @package app\common\entity
 */
class CashOrderE
{
    const TABLE_NAME = 'tp_cash_order';
    const SHORT_TABLE_NAME = 'cash_order';
    const METHOD = [
        'mini_charge' => 1,           //用户在小程序端充值
        'back_charge' => 2,          //管理员后台充值
    ];
    const PAY_TYPE = [
        'wechat' => 1,           //微信支付
        'back' => 2,          //后台支付
    ];
    const STATUS = [
        'unpaid' => 0,           //未支付
        'paid' => 1,          //已支付
        'dnone' => 2,          //已完成
    ];


}

