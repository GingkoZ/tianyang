<?php
namespace Custom\Service\User\Impl;

use Topxia\Service\User\Impl\UserServiceImpl as BaseService;
use Custom\Service\User\UserService;
use Topxia\Common\ArrayToolkit;

class UserServiceImpl extends BaseService implements UserService
{
    public function changeOrganizationId($userId, $organizationId)
    {
        $user = $this->getUser($userId);
        if (empty($user)) {
            throw $this->createServiceException('用户不存在，设置帐号失败！');
        }

        $this->getCustomUserDao()->updateUser($userId, array('tyjh_organizationId' => $organizationId));
    }

    public function findProfilesByTruename($truename)
    {
        $userProfiles = $this->getCustomProfileDao()->findProfilesByTruename($truename);
        return  ArrayToolkit::index($userProfiles, 'id');
    }


    public function searchUsers(array $conditions, array $orderBy, $start, $limit)
    {
        $users = $this->getCustomUserDao()->searchUsers($conditions, $orderBy, $start, $limit);
        return UserSerialize::unserializes($users);
    }

    public function searchUserCount(array $conditions)
    {
        return $this->getUserDao()->searchUserCount($conditions);
    }

    private function getCustomUserDao()
    {
        return $this->createDao('Custom:User.UserDao');
    }

    private function getCustomProfileDao()
    {
        return $this->createDao('Custom:User.UserProfileDao');
    }
}


class UserSerialize
{
    public static function serialize(array $user)
    {
        $user['roles'] = empty($user['roles']) ? '' :  '|' . implode('|', $user['roles']) . '|';
        return $user;
    }

    public static function unserialize(array $user = null)
    {
        if (empty($user)) {
            return null;
        }
        $user['roles'] = empty($user['roles']) ? array() : explode('|', trim($user['roles'], '|')) ;
        return $user;
    }

    public static function unserializes(array $users)
    {
        return array_map(function($user) {
            return UserSerialize::unserialize($user);
        }, $users);
    }
}