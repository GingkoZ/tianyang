<?php

namespace Custom\Service\User\Dao\Impl;

use Topxia\Service\User\Dao\Impl\UserDaoImpl as BaseDao;
use Custom\Service\User\Dao\UserDao;

class UserDaoImpl extends BaseDao implements UserDao
{
	public function searchUsers($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createCustomUserQueryBuilder($conditions)
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

    private function createCustomUserQueryBuilder($conditions)
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

        if (isset($conditions['nickname'])) {
            $conditions['nickname'] = "%{$conditions['nickname']}%";
        }

        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'user')
            ->andWhere('promoted = :promoted')
            ->andWhere('roles LIKE :roles')
            ->andWhere('roles = :role')
            ->andWhere('nickname LIKE :nickname')
            ->andWhere('loginIp = :loginIp')
            ->andWhere('createdIp = :createdIp')
            ->andWhere('approvalStatus = :approvalStatus')
            ->andWhere('email = :email')
            ->andWhere('level = :level')
            ->andWhere('createdTime >= :startTime')
            ->andWhere('createdTime <= :endTime')
            ->andWhere('locked = :locked')
            ->andWhere('level >= :greatLevel');

        if (isset($conditions['ids'])) {
            $ids = array();
            foreach ($conditions['ids'] as $id) {
                if (ctype_digit((string)abs($id))) {
                    $ids[] = $id;
                }
            }
            if ($ids) {
                $ids = join(',', $ids);
                $builder->andStaticWhere("id IN ($ids)");
            }
        }

        if (isset($conditions['tyjh_organizationIds'])) {
            $tyjh_organizationIds = array();
            foreach ($conditions['tyjh_organizationIds'] as $tyjh_organizationId) {
                if (ctype_digit((string)abs($tyjh_organizationId))) {
                    $tyjh_organizationIds[] = $tyjh_organizationId;
                }
            }
            if ($tyjh_organizationIds) {
                $tyjh_organizationIds = join(',', $tyjh_organizationIds);
                $builder->andStaticWhere("tyjh_organizationId IN ($tyjh_organizationIds)");
            }
        }
        
        return $builder;
    }
}