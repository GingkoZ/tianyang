<?php

namespace Topxia\Service\Course\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Course\Dao\CourseDraftDao;

class CourseDraftDaoImpl extends BaseDao implements CourseDraftDao
{
        protected $draftTable = 'course_draft';

        public function getCourseDraft($id)
    {
        $sql = "SELECT * FROM {$this->draftTable} WHERE id = ? LIMIT 1";
        return  $draft = $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function findCourseDraft($courseId,$lessonId, $userId)
    {
        $sql = "SELECT * FROM {$this->draftTable} WHERE courseId = ? AND lessonId = ? AND userId = ?";
        return $this->getConnection()->fetchAssoc($sql, array($courseId,$lessonId, $userId)) ? : null;
    }

    public function addCourseDraft($draft)
    {
        $affected = $this->getConnection()->insert($this->draftTable, $draft);
        if ($affected <= 0) {
            throw $this->createDaoException('Insert draft error.');
        }
        return $this->getCourseDraft($this->getConnection()->lastInsertId());
    }

    public function updateCourseDraft($courseId,$lessonId, $userId,$fields)
     {
        $this->getConnection()->update($this->draftTable, $fields, array('courseId' => $courseId,'lessonId' => $lessonId,'userId' => $userId));
        return $this->findCourseDraft($courseId,$lessonId, $userId);
    }

    public function searchDraftCount($conditions)
    {
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('COUNT(id)');
        return $builder->execute()->fetchColumn(0);
    }

    public function searchDrafts($conditions, $orderBy, $start, $limit)
    {
        $this->filterStartLimit($start, $limit);
        $builder = $this->_createSearchQueryBuilder($conditions)
        ->select('*')
        ->orderBy($orderBy[0], $orderBy[1])
        ->setFirstResult($start)
        ->setMaxResults($limit);
        return $builder->execute()->fetchAll() ? : array();
    }

    public function deleteCourseDrafts($courseId,$lessonId, $userId)
    {
        $sql = "DELETE FROM {$this->draftTable} WHERE courseId = ? AND lessonId = ? AND userId = ?";
        return $this->getConnection()->executeUpdate($sql, array($courseId,$lessonId, $userId));
    }

    public function deleteDraft($id)
    {
        return $this->getConnection()->delete($this->draftTable, array('id' => $id));
    }

    protected function _createSearchQueryBuilder($conditions)
    {   
        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'course_draft')
            ->andWhere('courseId = :courseId');
        return $builder;
    }

}