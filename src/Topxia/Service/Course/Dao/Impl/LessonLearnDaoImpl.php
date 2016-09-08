<?php

namespace Topxia\Service\Course\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Course\Dao\LessonLearnDao;

class LessonLearnDaoImpl extends BaseDao implements LessonLearnDao
{
    protected $table = 'course_lesson_learn';

	public function getLearn($id)
	{
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
	}

	public function getLearnByUserIdAndLessonId($userId, $lessonId)
	{
        $sql ="SELECT * FROM {$this->table} WHERE userId=? AND lessonId=?";
        return $this->getConnection()->fetchAssoc($sql, array($userId, $lessonId)) ? : null;
	}

	public function findLearnsByUserIdAndCourseId($userId, $courseId)
	{
        $sql ="SELECT * FROM {$this->table} WHERE userId=? AND courseId=?";
        return $this->getConnection()->fetchAll($sql, array($userId, $courseId)) ? : array();
	}

	public function findLearnsByUserIdAndCourseIdAndStatus($userId, $courseId, $status)
	{
        $sql ="SELECT * FROM {$this->table} WHERE userId=? AND courseId=? AND status = ?";
        return $this->getConnection()->fetchAll($sql, array($userId, $courseId, $status)) ? : array();
	}

	public function getLearnCountByUserIdAndCourseIdAndStatus($userId, $courseId, $status)
	{
        $sql ="SELECT COUNT(*) FROM {$this->table} WHERE userId = ? AND courseId = ? AND status = ?";
        return $this->getConnection()->fetchColumn($sql, array($userId, $courseId, $status));
	}

    public function findLearnsByLessonId($lessonId, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $sql = "SELECT * FROM {$this->table} WHERE lessonId = ? ORDER BY startTime DESC LIMIT {$start}, {$limit}";
        return $this->getConnection()->fetchAll($sql, array($lessonId));
    }

    public function findLearnsCountByLessonId($lessonId)
    {
        $sql ="SELECT COUNT(*) FROM {$this->table} WHERE lessonId = ?";
        return $this->getConnection()->fetchColumn($sql, array($lessonId));
    }

    public function findLatestFinishedLearns($start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $sql = "SELECT * FROM {$this->table} WHERE status = 'finished' ORDER BY finishedTime DESC LIMIT {$start}, {$limit}";
        return $this->getConnection()->fetchAll($sql);
    }

	public function addLearn($learn)
	{
        $affected = $this->getConnection()->insert($this->table, $learn);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert learn error.');
        }
        return $this->getLearn($this->getConnection()->lastInsertId());
	}

	public function updateLearn($id, $fields)
	{
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getLearn($id);
	}

    public function deleteLearnsByLessonId($lessonId)
    {
        $sql = "DELETE FROM {$this->table} WHERE lessonId = ?";
        return $this->getConnection()->executeUpdate($sql, array($lessonId));
    }

    public function searchLearnCount($conditions)
    {
        $builder=$this->_createSearchQueryBuilder($conditions)
            ->select('count(id)');

        return $builder->execute()->fetchColumn(0);
    }

    public function searchLearnTime($conditions)
    {
        $builder=$this->_createSearchQueryBuilder($conditions)
            ->select('sum(learnTime)');

        return $builder->execute()->fetchColumn(0);
    }

    public function searchWatchTime($conditions)
    {
        $builder=$this->_createSearchQueryBuilder($conditions)
            ->select('sum(watchTime)');

        return $builder->execute()->fetchColumn(0);
    }

    public function searchLearns($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array(); 
    }


    protected function _createSearchQueryBuilder($conditions)
    {
        if (isset($conditions['targetType'])) {
            $builder=$this->createDynamicQueryBuilder($conditions)
            ->from($this->table,$this->table)
            ->andWhere("status = :status")
            ->andWhere("finishedTime >= :startTime")
            ->andWhere("finishedTime <= :endTime");
        }else{
             $builder=$this->createDynamicQueryBuilder($conditions)
            ->from($this->table,$this->table)
            ->andWhere("status = :status")
            ->andWhere("userId = :userId")
            ->andWhere("lessonId = :lessonId")
            ->andWhere("courseId = :courseId")
            ->andWhere("finishedTime >= :startTime")
            ->andWhere("finishedTime <= :endTime");

        }

        $builder->andWhere("courseId IN (:courseIds)")
                ->andWhere('lessonId IN (:lessonIds)');

        return $builder;
    }

    public function analysisLessonFinishedDataByTime($startTime,$endTime)
    {
        $sql="SELECT count(id) as count, from_unixtime(finishedTime,'%Y-%m-%d') as date FROM `{$this->table}` WHERE`finishedTime`>=? AND `finishedTime`<=? AND `status`='finished'  group by from_unixtime(`finishedTime`,'%Y-%m-%d') order by date ASC ";
        return $this->getConnection()->fetchAll($sql, array($startTime,$endTime));
    }

    public function deleteLearn($id)
    {
        return $this->getConnection()->delete($this->table, array('id' => $id));
    }
}