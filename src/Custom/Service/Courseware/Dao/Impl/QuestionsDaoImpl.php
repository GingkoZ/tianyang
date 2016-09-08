<?php

namespace Custom\Service\Courseware\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Custom\Service\Courseware\Dao\QuestionsDao;

class QuestionsDaoImpl extends BaseDao implements QuestionsDao
{
    protected $table = 'question';

    public function findQuestions($fields)
    {
        $str="";

    	foreach($fields["types"] as $field)
    	{
    		$str.='"'.$field.'",';
    	}

    	$str=rtrim($str,",");

        if(array_key_exists("stem", $fields)){

           $sql = "SELECT * FROM {$this->table} WHERE type IN ($str) AND `target` IN ('course-{$fields['courseId']}/lesson-{$fields['lessonId']}' , 'course-{$fields['courseId']}') AND `stem` LIKE '%{$fields['stem']}%' ORDER BY createdTime DESC";  
        }else{
           $sql = "SELECT * FROM {$this->table} WHERE type IN ($str) AND `target` IN ('course-{$fields['courseId']}/lesson-{$fields['lessonId']}' , 'course-{$fields['courseId']}') ORDER BY createdTime DESC";
        }
 
        return $this->getConnection()->fetchAll($sql);;

    }
    
}