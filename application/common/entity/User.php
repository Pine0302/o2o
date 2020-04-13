<?php
namespace app\common\entity;

/**
 * Class User
 * @package app\common\entity
 */
class User
{
    const TABLE_NAME = 'tp_users';
    const SHORT_TABLE_NAME = 'users';
    const TYPE = [
        'user' => 1,
        'merch' => 2,
    ];
}
