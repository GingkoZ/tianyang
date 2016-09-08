<?php
namespace Topxia\Service\Common;

use Doctrine\DBAL\Query\QueryBuilder,
	Doctrine\DBAL\Connection;

class DynamicQueryBuilder extends QueryBuilder
{
	protected $conditions;

    public function __construct(Connection $connection, $conditions)
    {
    	parent::__construct($connection);
    	$this->conditions = $conditions;
    }

    public function where($where)
    {
    	if (!$this->isWhereInConditions($where)) {
    		return $this;
    	}
    	return parent::where($where);
    }

    public function andWhere($where)
    {
    	if (!$this->isWhereInConditions($where)) {
    		return $this;
    	}

        if($this->isInCondition($where)) {
            return $this->addWhereIn($where);
        }

        return parent::andWhere($where);

    }

    public function andStaticWhere($where)
    {
    	return parent::andWhere($where);
    }

    protected function addWhereIn($where)
    {
        $conditionName = $this->getConditionName($where);
        if (empty($this->conditions[$conditionName]) || !is_array($this->conditions[$conditionName])) {
            return $this;
        }

        $marks = array();
        foreach (array_values($this->conditions[$conditionName]) as $index => $value) {
            $marks[] = ":{$conditionName}_{$index}";
             $this->conditions["{$conditionName}_{$index}"] = $value;
        }
        
        $where = str_replace(":{$conditionName}", join(',', $marks), $where);

        return parent::andWhere($where);
    }

    public function execute()
    {
    	foreach ($this->conditions as $field => $value) {
    		$this->setParameter(":{$field}", $value);
    	}
    	return parent::execute();
    }

    protected function isInCondition($where)
    {
        $matched = preg_match('/\s+(IN)\s+/', $where, $matches);
        if (empty($matched)) {
            return false;
        } else {
            return true;
        }
    }

    protected function getConditionName($where) {
        $matched = preg_match('/:([a-zA-z0-9_]+)/', $where, $matches);
        if (empty($matched)) {
            return false;
        }
        return $matches[1];
    }

    protected function isWhereInConditions($where)
    {
        $conditionName = $this->getConditionName($where);
    	if (!$conditionName) {
    		return false;
    	}

        return array_key_exists($conditionName, $this->conditions) && !is_null($this->conditions[$conditionName]);
    }
}