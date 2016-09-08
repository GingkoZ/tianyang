<?php 
namespace Custom\Service\Courseware;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface CoursewareService
{
    public function getCourseware($id);
    public function createMediaLessonExercise($lessonId,$questionId,$showtime);
    public function searchMediaLessonExerciseCount($fields);
    public function findMediaLessonExercisesByLessonId($lessonId);
    public function findMediaLessonExercises($lessonId,$showtime);
    public function deleteMediaLessonExerciseById($id);
    public function findQuestions($fields);
    public function findMediaLessonExerciseAction($lessonId);
}