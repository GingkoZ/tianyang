<?php

namespace Custom\Service\Courseware\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Custom\Service\Courseware\Dao\CoursewareDao;

class CoursewareDaoImpl extends BaseDao implements CoursewareDao
{
    protected $table = 'course';

    public function getCourseware($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id)) ? : null;
    }

    
}