<?php
namespace app\common\entity;

/**
 * Class User
 * @package app\common\entity
 */
class StoreSub
{
    const TABLE_NAME = 'tp_store_sub';
    const SHORT_TABLE_NAME = 'store_sub';
    const STORE_STATE = [
        'OPEN' => 1,           //门店开启
        'CLOSED' => 0,          //门店关闭
        'AUDIT' => 2,          //门店审核中

    ];
}
