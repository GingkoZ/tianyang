<?php

namespace Custom\Service\User\Dao\Impl;

use Topxia\Service\User\Dao\Impl\UserProfileDaoImpl as BaseDao;
use Custom\Service\User\Dao\UserProfileDao;

class UserProfileDaoImpl extends BaseDao implements UserProfileDao
{
    public function findProfilesByTruename($truename)
    {
        $sql ="SELECT * FROM {$this->table} WHERE truename LIKE ?;";
        return $this->getConnection()->fetchAll($sql, array('%'.$truename.'%'));
    }
}