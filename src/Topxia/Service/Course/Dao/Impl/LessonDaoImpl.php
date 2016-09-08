<?php

namespace Topxia\Service\Course\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Course\Dao\LessonDao;

class LessonDaoImpl extends BaseDao implements LessonDao
{
    protected $table = 'course_lesson';

    public function getLesson($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function getLessonByCourseIdAndNumber($courseId, $number)
    {
        $sql = "SELECT * FROM {$this->table} WHERE courseId = ? AND number = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($courseId, $number)) ? : null;
    }

    public function findLessonsByIds(array $ids)
    {
        if(empty($ids)){
            return array();
        }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE id IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $ids);
    }

    public function findLessonsByCopyIdAndLockedCourseIds($copyId ,array $courseIds)
    {
        if(empty($courseIds)){
            return array();
        }
       
       $marks = str_repeat('?,', count($courseIds) - 1) . '?';
       
       $parmaters = array_merge(array($copyId), $courseIds);
             
       $sql = "SELECT * FROM {$this->table} WHERE  copyId = ? AND courseId IN ({$marks})";
       
       return $this->getConnection()->fetchAll($sql,$parmaters); 
    }

    public function findLessonsByTypeAndMediaId($type, $mediaId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE type = ? AND mediaId = ?";
        return $this->getConnection()->fetchAll($sql, array($type, $mediaId));
    }

    public function findLessonsByCourseId($courseId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE courseId = ? ORDER BY seq ASC";
        return $this->getConnection()->fetchAll($sql, array($courseId));
    }

    public function findMinStartTimeByCourseId($courseId)
    {
        $sql = "select min(`startTime`) as startTime from `course_lesson` where courseId =?;";
        return $this->getConnection()->fetchAll($sql,array($courseId));
    }

    public function findLessonIdsByCourseId($courseId)
    {
        $sql = "SELECT id FROM {$this->table} WHERE  courseId = ? ORDER BY number ASC";
        return $this->getConnection()->executeQuery($sql, array($courseId))->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getLessonCountByCourseId($courseId)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE courseId = ? ";
        return $this->getConnection()->fetchColumn($sql, array($courseId));
    }

    public function searchLessons($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array(); 
    }

    public function searchLessonCount($conditions)
    {
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    public function getLessonMaxSeqByCourseId($courseId)
    {
        $sql = "SELECT MAX(seq) FROM {$this->table} WHERE  courseId = ?";
        return $this->getConnection()->fetchColumn($sql, array($courseId));
    }

    public function findTimeSlotOccupiedLessonsByCourseId($courseId,$startTime,$endTime,$excludeLessonId=0)
    {
        $addtionalCondition = ";";

        $params = array($courseId,$startTime,$startTime,$startTime,$endTime);
        if (!empty($excludeLessonId)) {
            $addtionalCondition = "and id != ? ;";
            $params[] = $excludeLessonId;
        }

        $sql = "SELECT * FROM {$this->table} WHERE courseId = ? AND ((startTime  < ? AND endTime > ?) OR  (startTime between ? AND ?)) ".$addtionalCondition;
        

        return $this->getConnection()->fetchAll($sql, $params);
    }

    public function findTimeSlotOccupiedLessons($startTime,$endTime,$excludeLessonId=0)
    {
        $addtionalCondition = ";";

        $params = array($startTime,$startTime,$startTime, $endTime);
        if (!empty($excludeLessonId)) {
            $addtionalCondition = "and id != ? ;";
            $params[] = $excludeLessonId;
        }

        $sql = "SELECT * FROM {$this->table} WHERE ((startTime  < ? AND endTime > ?) OR  (startTime between ? AND ?)) ".$addtionalCondition;
        
        return $this->getConnection()->fetchAll($sql, $params);
    }

    public function findLessonsByChapterId($chapterId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE chapterId = ? ORDER BY seq ASC";
        return $this->getConnection()->fetchAll($sql, array($chapterId));
    }

    public function addLesson($lesson)
    {
        $affected = $this->getConnection()->insert($this->table, $lesson);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert course lesson error.');
        }
        return $this->getLesson($this->getConnection()->lastInsertId());
    }

    public function updateLesson($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getLesson($id);
    }

    public function deleteLessonsByCourseId($courseId)
    {
        $sql = "DELETE FROM {$this->table} WHERE courseId = ?";
        return $this->getConnection()->executeUpdate($sql, array($courseId));
    }

    public function deleteLesson($id)
    {
        return $this->getConnection()->delete($this->table, array('id' => $id));
    }

    public function sumLessonGiveCreditByCourseId($courseId)
    {
        $sql = "SELECT SUM(giveCredit) FROM {$this->table} WHERE  courseId = ?";
        return $this->getConnection()->fetchColumn($sql, array($courseId)) ? : 0;
    }

    public function sumLessonGiveCreditByLessonIds(array $lessonIds)
    {
        if(empty($lessonIds)){ 
            return 0; 
        }
        $marks = str_repeat('?,', count($lessonIds) - 1) . '?';
        $sql ="SELECT SUM(giveCredit) FROM {$this->table} WHERE id IN ({$marks});";
        return $this->getConnection()->fetchColumn($sql, $lessonIds) ? : 0;
    }

    protected function _createSearchQueryBuilder($conditions)
    {
        if (isset($conditions['title'])) {
            $conditions['titleLike'] = "%{$conditions['title']}%";
            unset($conditions['title']);
        }

        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, $this->table)
            ->andWhere('courseId = :courseId')
            ->andWhere('status = :status')
            ->andWhere('type = :type')
            ->andWhere('free = :free')
            ->andWhere('userId = :userId')
            ->andWhere('mediaId = :mediaId')
            ->andWhere('startTime >= :startTimeGreaterThan')
            ->andWhere('endTime < :endTimeLessThan')
            ->andWhere('startTime <= :startTimeLessThan')
            ->andWhere('endTime > :endTimeGreaterThan')
            ->andWhere('title LIKE :titleLike')
            ->andWhere('createdTime >= :startTime')
            ->andWhere('createdTime <= :endTime')
            ->andWhere('courseId IN ( :courseIds )');
        if(isset($conditions['notLearnedIds'])){
            $builder->andWhere('id NOT IN ( :notLearnedIds)');
        }
        return $builder;
    }

    public function analysisLessonNumByTime($startTime,$endTime)
    {
        $sql="SELECT count(id)  as num FROM `{$this->table}` WHERE  `createdTime`>=? AND `createdTime`<=?  ";
        return $this->getConnection()->fetchColumn($sql, array($startTime, $endTime));
    }

    public function analysisLessonDataByTime($startTime,$endTime)
    {
             $sql="SELECT count( id) as count, from_unixtime(createdTime,'%Y-%m-%d') as date FROM `{$this->table}` WHERE  `createdTime`>=? AND `createdTime`<=? group by from_unixtime(`createdTime`,'%Y-%m-%d') order by date ASC ";

            return $this->getConnection()->fetchAll($sql, array($startTime, $endTime));
    }
}