<?php
namespace Topxia\Service\Common;

use PDO,
    Topxia\Common\DaoException;

abstract class BaseDao
{
    protected $connection;

    protected $table = null;

    protected $primaryKey = 'id';

    private static $cachedSerializer = array();

    protected $dataCache = array();

    protected function wave ($id, $fields) 
    {
        $sql = "UPDATE {$this->getTable()} SET ";
        $fieldStmts = array();
        foreach (array_keys($fields) as $field) {
            $fieldStmts[] = "{$field} = {$field} + ? ";
        }
        $sql .= join(',', $fieldStmts);
        $sql .= "WHERE id = ?";

        $params = array_merge(array_values($fields), array($id));
        return $this->getConnection()->executeUpdate($sql, $params);
    }

    public function getTable()
    {
        if($this->table){
            return $this->table;
        }else{
            return self::TABLENAME;
        }
    }

    public function getConnection ()
    {
        return $this->connection;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    protected function fetchCached()
    {
        $args = func_get_args();
        $callback = array_pop($args);

        $key = implode(':', $args);
        if (isset($this->dataCached[$key])) {
            return $this->dataCached[$key];
        }

        array_shift($args);
        $this->dataCached[$key] = call_user_func_array($callback, $args);

        return $this->dataCached[$key];
    }

    protected function clearCached()
    {
        $this->dataCached = array();
    }

    protected function createDaoException($message = null, $code = 0) 
    {
        return new DaoException($message, $code);
    }

    protected function createDynamicQueryBuilder($conditions)
    {
        return new DynamicQueryBuilder($this->getConnection(), $conditions);
    }

    public function createSerializer()
    {
        if (!isset(self::$cachedSerializer['field_serializer'])) {
            self::$cachedSerializer['field_serializer'] = new FieldSerializer();
        }
        return self::$cachedSerializer['field_serializer'];
    }

    protected function filterStartLimit(&$start, &$limit)
    {
       $start = (int) $start;
       $limit = (int) $limit; 
    }

    protected function addOrderBy($builder, $orderBy)
    {
        foreach ($orderBy as $column => $order) {
            if (in_array($column, array('createdTime', 'ups')) && in_array($order, array('DESC', 'ASC'))) {
                $builder->addOrderBy($column, $order);
            }
        }

        return $builder;
    }

    protected function validateOrderBy(array $orderBy, $allowedOrderByFields)
    {
        $keys = array_keys($orderBy);
        foreach ($orderBy as $field => $order) {
            if (!in_array($field, $allowedOrderByFields)) {
                throw new \RuntimeException("不允许对{$field}字段进行排序", 1);
            }
            
            if (!in_array($order, array('ASC','DESC'))){
                throw new \RuntimeException("orderBy排序方式错误", 1);
            }
        }
    }

    protected function checkOrderBy (array $orderBy, array $allowedOrderByFields)
    {
        if (empty($orderBy[0]) || empty($orderBy[1])) {
            throw new \RuntimeException('orderBy参数不正确');
        }
        
        if (!in_array($orderBy[0], $allowedOrderByFields)){
            throw new \RuntimeException("不允许对{$orderBy[0]}字段进行排序", 1);
        }
        if (!in_array(strtoupper($orderBy[1]), array('ASC','DESC'))){
            throw new \RuntimeException("orderBy排序方式错误", 1);
        }

        return $orderBy;
    }

}