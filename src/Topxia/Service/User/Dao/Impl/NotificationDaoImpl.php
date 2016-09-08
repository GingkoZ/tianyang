<?php

namespace Topxia\Service\User\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\User\Dao\NotificationDao;

class NotificationDaoImpl extends BaseDao implements NotificationDao
{
    protected $table = 'notification';

    public function getNotification($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function addNotification($notification)
    {
        $affected = $this->getConnection()->insert($this->table, $notification);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert notification error.');
        }
        return $this->getNotification($this->getConnection()->lastInsertId());
    }

    public function updateNotification($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getNotification($id);
    }

    public function findNotificationsByUserId($userId, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $sql = "SELECT * FROM {$this->table} WHERE userId = ? ORDER BY createdTime DESC LIMIT {$start}, {$limit}";
        return $this->getConnection()->fetchAll($sql, array($userId));
    }

    public function getNotificationCountByUserId($userId)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE  userId = ? ";
        return $this->getConnection()->fetchColumn($sql, array($userId));
    }

    public function searchNotifications($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->createNotificationQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array();
    }

    public function searchNotificationCount($conditions)
    {
        $builder = $this->createNotificationQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    public function deleteNotification($id)
    {
        return $this->getConnection()->delete($this->table, array('id' => $id));
    }

    protected function createNotificationQueryBuilder($conditions)
    {
        return  $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'notification')
            ->andWhere('userId = :userId')
            ->andWhere('type = :type');
    }
    
}