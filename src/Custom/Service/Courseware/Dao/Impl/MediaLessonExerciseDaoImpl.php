<?php

namespace Custom\Service\Courseware\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Custom\Service\Courseware\Dao\MediaLessonExerciseDao;

class MediaLessonExerciseDaoImpl extends BaseDao implements MediaLessonExerciseDao
{
    protected $table = 'media_lesson_exercise';

    public function add($lessonId,$questionId,$showtime,$createdTime)
    {      
        $str="";

        foreach($questionId as $key=>$values)
        {
            $str.="('".$lessonId."','".$values."','".$showtime."','".$createdTime."'),";
        }

        $str=rtrim($str,",");
        $sql="INSERT INTO {$this->table}(lessonId,questionId,showtime,createdTime)values".$str."";
        return $this->getConnection()->executeQuery($sql);

    }

    public function delete($id)
    {
         return $this->getConnection()->delete($this->table, array('id' => $id));
    }

    public function searchMediaLessonExerciseCount($fields)
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE  lessonId = {$fields['lessonId'] } AND showtime = {$fields['showtime']}";
        $result=$this->getConnection()->fetchAll($sql);
        return (int)$result[0]['COUNT(*)'];
    }
    public function findByLessonId($lessonId)
    {
        $sql = "SELECT * FROM {$this->table} WHERE lessonId = ? ORDER BY showtime";
        return $this->getConnection()->fetchAll($sql, array($lessonId)) ? : array();
    }

    public function findMediaLessonExercises($lessonId,$showtime)
    {
      $sql = " SELECT * FROM {$this->table} WHERE lessonId = ? AND showtime = ? ORDER BY showtime";
      return $this->getConnection()->fetchAll($sql, array($lessonId,$showtime)) ? : array();
    }

}