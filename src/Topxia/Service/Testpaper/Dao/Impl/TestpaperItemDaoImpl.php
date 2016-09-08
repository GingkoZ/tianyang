<?php

namespace Topxia\Service\Testpaper\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Testpaper\Dao\TestpaperItemDao;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Connection;

class TestpaperItemDaoImpl extends BaseDao implements TestpaperItemDao
{
    protected $table = 'testpaper_item';

    public function getItem($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    public function addItem($item)
    {
        $item = $this->getConnection()->insert($this->table, $item);
        if ($item <= 0) {
            throw $this->createDaoException('Insert item error.');
        }
        return $this->getItem($this->getConnection()->lastInsertId());
    }

    public function updateItem($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        return $this->getItem($id);
    }

    public function deleteItem($id)
    {
        return $this->getConnection()->delete($this->table, array('id' => $id));
    } 

    public function deleteItemsByParentId($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE parentId = ?";
        return $this->getConnection()->executeUpdate($sql, array($id));
    }

    public function deleteItemsByTestpaperId($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE testId = ? ";
        return $this->getConnection()->executeUpdate($sql, array($id));
    }

    public function findItemByIds(array $ids)
    {
        if(empty($ids)){ 
            return array(); 
        }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="SELECT * FROM {$this->table} WHERE id IN ({$marks});";
        return $this->getConnection()->fetchAll($sql, $ids);
    }

    public function findItemsByTestPaperId($testPaperId)
    {
        $sql ="SELECT * FROM {$this->table} WHERE testId = ? order by `seq` asc ";
        return $this->getConnection()->fetchAll($sql, array($testPaperId)) ? : array();
    }

    public function getItemsCountByTestId($testId)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE testId = ? ";
        return $this->getConnection()->fetchColumn($sql, array($testId));
    }

    public function getItemsCountByTestIdAndParentId($testId, $parentId)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE `testId` = ? AND `parentId` = ?";
        return $this->getConnection()->fetchColumn($sql, array($testId, $parentId));
    }

    public function getItemsCountByTestIdAndQuestionType($testId, $questionType)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE `testId` = ? AND `questionType` = ? ";
        return $this->getConnection()->fetchColumn($sql, array($testId, $questionType));
    }

    public function deleteItemByIds(array $ids)
    {
        if(empty($ids)){ 
            return array(); 
        }
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="DELETE FROM {$this->table} WHERE id IN ({$marks});";
        return $this->getConnection()->executeUpdate($sql, $ids);
    }

    public function updateItemsMissScoreByPaperIds(array $ids, $missScore)
    {
        if(empty($ids)){ 
            return array(); 
        }
        $params = array_merge(array($missScore), $ids);
        $marks = str_repeat('?,', count($ids) - 1) . '?';
        $sql ="UPDATE {$this->table} SET missScore = ? WHERE testId IN ({$marks})";
        return $this->getConnection()->executeUpdate($sql, $params);
    }

    public function getItemsCountByParams(array $conditions, $groupBy='')
    {
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('count(id) as num, sum(score) as score,questionType');

        if (!empty($groupBy)) {
            $builder->addGroupBy($groupBy);
        }


         return $builder->execute()->fetchAll() ? : array();
    }

    private function _createSearchQueryBuilder($conditions)
    {
        $conditions = array_filter($conditions, function($value) {
            if ($value === '' or is_null($value)) {
                return false;
            }
            return true;
        });


        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table, 'questions')
            ->andWhere('testId = :testId')
            ->andWhere("questionType IN ( :questionTypes )")
            ->andWhere('parentId = :parentIdDefault')
            ->andWhere('parentId > :parentId');

        
        return $builder;
    }

    public function findTestpaperItemsByPIdAndLockedTestIds($pId,$testIds)
    {
        if(empty($testIds)){ 
            return array(); 
        }
        $params = array_merge(array($pId), $testIds);
        $marks = str_repeat('?,', count($testIds) - 1) . '?';
        $sql = "SELECT * FROM {$this->table} WHERE pId = ?  AND testId IN ({$marks})";
        return $this->getConnection()->fetchAll($sql,$params);
    }

}