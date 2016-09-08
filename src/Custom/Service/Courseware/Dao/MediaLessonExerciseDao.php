<?php

namespace Custom\Service\Courseware\Dao;

interface MediaLessonExerciseDao
{
	public function add($lessonId,$questionId,$showtime,$createdTime);
	public function delete($id);
	public function searchMediaLessonExerciseCount($fields);
	public function findByLessonId($lessonId);
	public function findMediaLessonExercises($lessonId,$showtime);
}