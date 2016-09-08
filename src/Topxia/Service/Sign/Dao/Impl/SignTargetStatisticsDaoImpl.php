<?php

namespace Topxia\Service\Sign\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Sign\Dao\SignTargetStatisticsDao;

class SignTargetStatisticsDaoImpl extends BaseDao implements SignTargetStatisticsDao
{
	protected $table = 'sign_target_statistics';

	public function addStatistics($statistics)
	{
        $affected = $this->getConnection()->insert($this->table, $statistics);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert class sign Statistics error.');
        }
        return $this->getStatisticsById($this->getConnection()->lastInsertId());
	}

	public function getStatisticsById($id)
	{
		$sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
	}

	public function getStatistics($targetType, $targetId, $date)
	{
		$sql = "SELECT * FROM {$this->table} WHERE targetType = ? AND targetId = ? AND date = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($targetType, $targetId, $date)) ? : null;
	}

	public function updateStatistics($id, $fields)
	{
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getStatisticsById($id);
	}

}
