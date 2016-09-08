<?php

namespace Topxia\Service\User\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\User\Dao\UserDao;
use Topxia\Common\DaoException;
use PDO;

class UserDaoImpl extends BaseDao implements UserDao
{
    protected $table = 'user';

    public function getUser($id, $lock = false)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        if ($lock) {
            $sql .= " FOR UPDATE";
        }
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function findUserByEmail($email)
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($email));
    }

    public function findUserByNickname($nickname)
    {
        $sql = "SELECT * FROM {$this->table} WHERE nickname = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($nickname));
    }

    public function findUserByVerifiedMobile($mobile)
    {
        $sql = "SELECT * FROM {$this->table} WHERE verifiedMobile = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($mobile));
    }

    public function findUsersByNicknames(array $nicknames)
    {
        if(empty($nicknames)) { 
            return array(); 
        }

        $marks = str_repeat('?,', count($nicknames) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE nickname IN ({$marks});";
        
        return $this->getConnection()->fetchAll($sql, $nicknames);
    }

    public function findUsersByIds(array $ids)
    {
        if(empty($ids)){
            return array();
        }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE id IN ({$marks});";
        
        return $this->getConnection()->fetchAll($sql, $ids);
    }

    public function searchUsers($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createUserQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array();
    }

    public function searchUserCount($conditions)
    {
        $builder = $this->createUserQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    protected function createUserQueryBuilder($conditions)
    {
        $conditions = array_filter($conditions,function($v){
            if($v === 0){
                return true;
            }
                
            if(empty($v)){
                return false;
            }
            return true;
        });
        if (isset($conditions['roles'])) {
            $conditions['roles'] = "%{$conditions['roles']}%";
        }

        if (isset($conditions['role'])) {
            $conditions['role'] = "|{$conditions['role']}|";
        }

        if(isset($conditions['keywordType']) && isset($conditions['keyword'])) {
            $conditions[$conditions['keywordType']]=$conditions['keyword'];
            unset($conditions['keywordType']);
            unset($conditions['keyword']);
        }

        if (isset($conditions['keywordUserType'])) {
            $conditions['type'] = "%{$conditions['keywordUserType']}%";
            unset($conditions['keywordUserType']);
        }

        if (isset($conditions['nickname'])) {
            $conditions['nickname'] = "%{$conditions['nickname']}%";
        }

        if(!empty($conditions['datePicker'])&& $conditions['datePicker'] == 'longinDate'){
            if(isset($conditions['startDate'])){
                $conditions['loginStartTime'] = strtotime($conditions['startDate']);
            }
            if(isset($conditions['endDate'])){
                $conditions['loginEndTime'] = strtotime($conditions['endDate']);
            }
        }
        if(!empty($conditions['datePicker'])&& $conditions['datePicker'] == 'registerDate'){
            if(isset($conditions['startDate'])){
                $conditions['startTime'] = strtotime($conditions['startDate']);
            }
            if(isset($conditions['endDate'])){
                $conditions['endTime'] = strtotime($conditions['endDate']);
            }
        }

        $conditions['verifiedMobileNull'] = "";

        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'user')
            ->andWhere('promoted = :promoted')
            ->andWhere('roles LIKE :roles')
            ->andWhere('roles = :role')
            ->andWhere('UPPER(nickname) LIKE :nickname')
            ->andWhere('loginIp = :loginIp')
            ->andWhere('createdIp = :createdIp')
            ->andWhere('approvalStatus = :approvalStatus')
            ->andWhere('email = :email')
            ->andWhere('level = :level')
            ->andWhere('createdTime >= :startTime')
            ->andWhere('createdTime <= :endTime')
            ->andWhere('approvalTime >= :startApprovalTime')
            ->andWhere('approvalTime <= :endApprovalTime')
            ->andWhere('loginTime >= :loginStartTime')
            ->andWhere('loginTime <= :loginEndTime')
            ->andWhere('locked = :locked')
            ->andWhere('level >= :greatLevel')
            ->andWhere('verifiedMobile = :verifiedMobile')
            ->andWhere('type LIKE :type')
            ->andWhere('id IN ( :userIds)')
            ->andWhere('id NOT IN ( :excludeIds )');
            
        if (array_key_exists('hasVerifiedMobile', $conditions)) {
            $builder = $builder->andWhere('verifiedMobile != :verifiedMobileNull');
        }
        return $builder;
    }

    public function addUser($user)
    {
        $affected = $this->getConnection()->insert($this->table, $user);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert user error.');
        }
        return $this->getUser($this->getConnection()->lastInsertId());
    }

    public function updateUser($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getUser($id);
    }

    public function waveCounterById($id, $name, $number)
    {
        $names = array('newMessageNum', 'newNotificationNum');
        if (!in_array($name, $names)) {
            throw $this->createDaoException('counter name error');
        }
        $sql = "UPDATE {$this->table} SET {$name} = {$name} + ? WHERE id = ? LIMIT 1";
        return $this->getConnection()->executeQuery($sql, array($number, $id));
    }

    public function clearCounterById($id, $name)
    {
        $names = array('newMessageNum', 'newNotificationNum');
        if (!in_array($name, $names)) {
            throw $this->createDaoException('counter name error');
        }
        $sql = "UPDATE {$this->table} SET {$name} = 0 WHERE id = ? LIMIT 1";
        return $this->getConnection()->executeQuery($sql, array($id));
    }

    public function analysisRegisterDataByTime($startTime,$endTime)
    {
        $sql="SELECT count(id) as count, from_unixtime(createdTime,'%Y-%m-%d') as date FROM `{$this->table}` WHERE`createdTime`>=? AND `createdTime`<=? group by from_unixtime(`createdTime`,'%Y-%m-%d') order by date ASC ";
        return $this->getConnection()->fetchAll($sql, array($startTime, $endTime));
    }

    public function analysisUserSumByTime($endTime)
    {
         $sql="select date, count(*) as count from (SELECT from_unixtime(o.createdTime,'%Y-%m-%d') as date from user o where o.createdTime<=? ) dates group by dates.date order by date desc";
         return $this->getConnection()->fetchAll($sql, array($endTime));
    }

    public function findUsersCountByLessThanCreatedTime($endTime)
    {
        $sql="SELECT count(id) as count FROM `{$this->table}` WHERE  `createdTime`<=?  ";
        return $this->getConnection()->fetchColumn($sql, array($endTime));
    }
}