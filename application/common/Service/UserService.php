<?php

namespace app\common\service;

use app\common\repository\UserRepository;
use app\common\service\GoodsService;
/**
 * Class User
 * @package app\common\Service
 */
class UserService
{


    /**
     * @var UserRepository
     */
    private $userRepository;



    public function __construct(
        UserRepository $userRepository
    )
    {
        $this->userRepository = $userRepository;
    }
    public function updateUserByFilter($data,$map){
        $this->userRepository->updateUserByFilter($data,$map);
    }

}

