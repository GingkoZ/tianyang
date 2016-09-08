<?php
namespace Topxia\MobileBundleV2\Processor\Impl;

use Topxia\MobileBundleV2\Processor\BaseProcessor;
use Topxia\MobileBundleV2\Processor\CourseProcessor;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Service\Common\ServiceException;
use Topxia\Service\Util\EdusohoLiveClient;

class CourseProcessorImpl extends BaseProcessor implements CourseProcessor
{
    public function getVersion()
    {
        var_dump("CourseProcessorImpl->getVersion");
        return $this->formData;
    }
    
    public function getCourseNotices()
    {
        $start    = (int) $this->getParam("start", 0);
        $limit    = (int) $this->getParam("limit", 10);
        $courseId = $this->getParam("courseId");
        if (empty($courseId)) {
            return array();
        }

        $conditions = array(
            'targetType' => "course",
            'targetId' => $courseId
        );

        $announcements = $this->getAnnouncementService()->searchAnnouncements($conditions, array('createdTime','DESC'), $start, $limit);
        $announcements = array_values($announcements);
        return $this->filterAnnouncements($announcements);
    }
    
    public function getLessonNote()
    {
        $courseId = $this->getParam("courseId");
        $lessonId = $this->getParam("lessonId");
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能查看笔记！");
        }
        
        $lessonNote = $this->controller->getNoteService()->getUserLessonNote($user['id'], $lessonId);
        if (empty($lessonNote)) {
            return null;
        }
        $lesson  = $this->controller->getCourseService()->getCourseLesson($courseId, $lessonId);
        $lessonNote['lessonTitle'] = $lesson['title'];
        $lessonNote['lessonNum']   = $lesson['number'];
        $content  = $this->controller->convertAbsoluteUrl($this->request, $lessonNote['content']);
        $content  = $this->filterNote($content);
        $lessonNote['content'] = $content;
        return $lessonNote;
    }
    
    public function getCourseMember()
    {
        $courseId = $this->getParam("courseId");
        $user     = $this->controller->getUserByToken($this->request);
        if (empty($courseId)) {
            return null;
        }
        $member = $user->isLogin() ? $this->controller->getCourseService()->getCourseMember($courseId, $user['id']) : null;
        $member = $this->previewAsMember($member, $courseId, $user);
        
        if ($member && $member['locked']) {
            return null;
        }
        $member = $this->checkMemberStatus($member);
        return empty($member) ? new Response("null") : $member;
    }
    
    public function postThread()
    {
        $courseId = $this->getParam("courseId", 0);
        $threadId = $this->getParam("threadId", 0);
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能回复！");
        }
        $thread = $this->controller->getThreadService()->getThread($courseId, $threadId);
        if (empty($thread)) {
            return $this->createErrorResponse('not_thread', "问答不存在或已删除");
        }

        $content = $this->getParam("content", '');
        $content = $this->uploadImage($content);

        $formData = $this->formData;
        $formData['content'] = $content;
        unset($formData['imageCount']);
        $post = $this->controller->getThreadService()->createPost($formData);
        $threadTitle = $thread['title'];
        //PushService::sendMsg($thread['userId'],"1|$courseId|$threadTitle|$threadId");
        return $post;
    }
    
    /*
     *更新回复
     */
    public function updatePost()
    {
        $courseId = $this->getParam("courseId", 0);
        $threadId = $this->getParam("threadId", 0);
        $postId   = $this->getParam('postId', 0);
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能评价课程！");
        }
        
        if (!empty($postId)) {
            $post = $this->controller->getThreadService()->getPost($courseId, $postId);
            if (empty($post)) {
                return $this->createErrorResponse('postId_not_exist', "postId不存在！");
            }
        } else {
            return $this->createErrorResponse('wrong_postId_param', "postId参数错误！");
        }
        
        $content = $this->getParam("content", '');
        if (empty($content)) {
            return $this->createErrorResponse('wrong_content_param', "回复内容不能为空！");
        }
        
        $content = $this->uploadImage($content);
        
        $formData            = $this->formData;
        $formData['content'] = $content;
        unset($formData['imageCount']);
        
        $post = $this->controller->getThreadService()->updatePost($courseId, $postId, $formData);
        
        $threadInfo = $this->controller->getThreadService()->getThread($courseId, $threadId);
        return $post;
    }
    
    /**
     * add need param (courseId, lessonId, title, content, type="question")
     * update need param (courseId, threadId, title, content, type)
     */
    public function updateThread()
    {
        $courseId   = $this->getParam("courseId", 0);
        $threadId   = $this->getParam("threadId", 0);
        $title      = $this->getParam("title", "");
        $content    = $this->getParam("content", "");
        $action     = $this->getParam("action", "update");
        $imageCount = $this->getParam("imageCount", 0);
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，修改该课时');
        }
        
        if ($imageCount > 0) {
            $content = $this->uploadImage($content);
        }
        
        $formData            = $this->formData;
        $formData['content'] = $content;
        unset($formData['imageCount']);
        unset($formData['action']);
        unset($formData['threadId']);
        
        $result = array();
        if ($action == "add") {
            $result = $this->controller->getThreadService()->createThread($formData);
        } else {
            $fields = array(
                "title" => $title,
                "content" => $content
            );
            $result = $this->controller->getThreadService()->updateThread($courseId, $threadId, $fields);
        }
        $result['content']        = $this->filterSpace($this->controller->convertAbsoluteUrl($this->controller->request, $result['content']));
        $result['latestPostTime'] = Date('c', $result['latestPostTime']);
        $result['createdTime']    = Date('c', $result['createdTime']);
        return $result;
    }
    
    private function uploadImage($content)
    {
        $url      = "none";
        $urlArray = array();
        $files    = $file = $this->request->files;
        foreach ($files as $key => $value) {
            try {
                $group  = $this->getParam("group", 'course');
                $record = $this->getFileService()->uploadFile($group, $value);
                $url    = $this->controller->get('topxia.twig.web_extension')->getFilePath($record['uri']);
                
            }
            catch (\Exception $e) {
                $url = "error";
            }
            $urlArray[$key] = $url;
        }
        
        $baseUrl = $this->request->getSchemeAndHttpHost();
        $content = preg_replace_callback('/src=[\'\"](.*?)[\'\"]/', function($matches) use ($baseUrl, $urlArray)
        {
            if (strpos($matches[1], "http") !== false) {
                return "src=\"$matches[1]\"";
            } else {
                return "src=\"{$urlArray[$matches[1]]}\"";
            }
        }, $content);
        return $content;
    }
    
    public function commitCourse()
    {
        $courseId = $this->getParam("courseId", 0);
        $user     = $this->controller->getUserByToken($this->request);
        
        if (!$user->isLogin()) {
            return $this->createErrorResponse($request, 'not_login', "您尚未登录，不能评价课程！");
        }
        
        $course = $this->controller->getCourseService()->getCourse($courseId);
        if (empty($course)) {
            return $this->createErrorResponse('not_found', "课程#{$courseId}不存在，不能评价！");
        }
        
        if (!$this->controller->getCourseService()->canTakeCourse($course)) {
            return $this->createErrorResponse('access_denied', "您不是课程《{$course['title']}》学员，不能评价课程！");
        }
        
        $review             = array();
        $review['courseId'] = $course['id'];
        $review['userId']   = $user['id'];
        $review['rating']   = (float) $this->getParam("rating", 0);
        $review['content']  = $this->getParam("content", '');
        
        $review = $this->controller->getReviewService()->saveReview($review);
        $review = $this->controller->filterReview($review);
        
        return $review;
    }

    public function getThreadsByUserCourseIds(){
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能获取问答！");
        }
        $type = $this->getParam("type", "question");
        $start = (int) $this->getParam("start", 0);
        $limit = (int) $this->getParam("limit", 10);

        $learningCourseTotal = $this->controller->getCourseService()->findUserLeaningCourseCount($user['id']);
        $learningCourses = $this->controller->getCourseService()->findUserLeaningCourses($user['id'],0,$learningCourseTotal);
        $resultLearning = $this->controller->filterCourses($learningCourses);

        $learnedCourseTotal = $this->controller->getCourseService()->findUserLeanedCourseCount($user['id']);
        $learnedCourses = $this->controller->getCourseService()->findUserLeanedCourses($user['id'], 0, $learnedCourseTotal);
        $resultLearned = $this->controller->filterCourses($learnedCourses);
        $courseIds = ArrayToolkit::column($resultLearning + $resultLearned, 'id');

        $threadsByUserCourseIds = array();
        if(sizeof($courseIds) > 0){
            $conditions     = array(
                'courseIds' => $courseIds,
                'type' => $type
            );

            $threadsByUserCourseIds = $this->controller->getThreadService()->searchThreadInCourseIds($conditions, 'postedNotStick', $start,  $limit);
            $controller = $this;
            $threadsByUserCourseIds = array_map(function($thread) use ($controller)
            {
                $thread['content'] = $controller->filterSpace($controller->controller->convertAbsoluteUrl($controller->request, $thread['content']));
                return $thread;
            }, $threadsByUserCourseIds);

            $courses = $this->controller->getCourseService()->findCoursesByIds(ArrayToolkit::column($threadsByUserCourseIds, 'courseId'));
            
            $posts = array();
            foreach ($threadsByUserCourseIds as $key => $thread) {
                $post = $this->controller->getThreadService()->findThreadPosts($thread["courseId"], $thread["id"], "elite", 0, 1);
                if (!empty($post)) {
                    $posts[$post[0]["threadId"]] = $post[0];
                }
            }
            
            $threadsByUserCourseIds = array_map(function($thread) use ($posts)
            {
                if (isset($posts[$thread["id"]])) {
                    $thread["latestPostContent"] = $posts[$thread["id"]]["content"];
                }
                return $thread;
            }, $threadsByUserCourseIds);

            $users = $this->controller->getUserService()->findUsersByIds(ArrayToolkit::column($threadsByUserCourseIds, 'userId'));
            $threadsByUserCourseIds = $this->filterThreads($threadsByUserCourseIds, $courses, $users);
        }
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => count($threadsByUserCourseIds),
            'threads' => $threadsByUserCourseIds
        );
    }
    
    public function getCourseThreads()
    {
        $user     = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        $type     = $this->getParam("type", "question");
        $lessonId = $this->getParam("lessonId", "0");
        
        if ($lessonId == "0") {
            $conditions = array(
                'userId' => $user['id'],
                'type' => $type
            );
        } else {
            $conditions = array(
                'lessonId' => $lessonId,
                'type' => $type
            );
        }
        
        $start = (int) $this->getParam("start", 0);
        $limit = (int) $this->getParam("limit", 10);
        $total = $this->controller->getThreadService()->searchThreadCount($conditions);
        
        $threads    = $this->controller->getThreadService()->searchThreads($conditions, 'postedNotStick', $start, $limit);
        $controller = $this;
        $threads = array_map(function($thread) use ($controller)
        {
            $thread['content'] = $controller->filterSpace($controller->controller->convertAbsoluteUrl($controller->request, $thread['content']));
            return $thread;
        }, $threads);

        $courses = $this->controller->getCourseService()->findCoursesByIds(ArrayToolkit::column($threads, 'courseId'));
        
        $posts = array();
        foreach ($threads as $key => $thread) {
            $post = $this->controller->getThreadService()->findThreadPosts($thread["courseId"], $thread["id"], "elite", 0, 1);
            if (!empty($post)) {
                $posts[$post[0]["threadId"]] = $post[0];
            }
        }
        
        $threads = array_map(function($thread) use ($posts)
        {
            if (isset($posts[$thread["id"]])) {
                $thread["latestPostContent"] = $posts[$thread["id"]]["content"];
            }
            return $thread;
        }, $threads);

        $users = $this->controller->getUserService()->findUsersByIds(ArrayToolkit::column($threads, 'userId'));
        $threads = $this->filterThreads($threads, $courses, $this->filterUsersFiled($users));
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => count($threads),
            'data' => $threads
        );
    }

    public function getCourseNotes()
    {
        $start    = $this->getParam("start", 0);
        $limit    = $this->getParam("limit", 10);
        $courseId = $this->getParam("courseId");
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能查看笔记！");
        }
        $conditions = array(
            'userId' => $user['id'],
            'courseId' => $courseId,
            'noteNumGreaterThan' => 0
        );
        
        $courseNotes = $this->controller->getNoteService()->searchNotes($conditions, array('createdTime' => 'DESC'), $start, $limit);
        $lessons     = $this->controller->getCourseService()->findLessonsByIds(ArrayToolkit::column($courseNotes, 'lessonId'));
        for ($i = 0; $i < count($courseNotes); $i++) {
            $courseNote  = $courseNotes[$i];
            $lesson = $lessons[$courseNote['lessonId']];
            $courseNote['lessonTitle'] = $lesson['title'];
            $courseNote['lessonNum'] = $lesson['number'];
            $content = $this->controller->convertAbsoluteUrl($this->request, $courseNote['content']);
            $content = $this->filterNote($content);
            $courseNote['content'] = $content;
            $courseNotes[$i] = $courseNote;
        }
        return $courseNotes;
    }
    
    private function filterNote($note)
    {
        return preg_replace_callback('/<img [^>]+\\/?>/', function($matches)
        {
            return "<p>" . $matches[0] . "</p>";
        }, $note);
    }
    
    public function getNoteList()
    {
        $user  = $this->controller->getUserByToken($this->request);
        $start = $this->getParam("start", 0);
        $limit = $this->getParam("limit", 10);
        
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能查看笔记！");
        }
        
        $conditions = array(
            'userId' => $user['id']
        );

        $total = $this->controller->getNoteService()->searchNoteCount($conditions);
        $noteInfos = $this->controller->getNoteService()->searchNotes($conditions, array("updatedTime" => "DESC") , $start, $limit);
        $lessonIds = ArrayToolkit::column($noteInfos , "lessonId");
        $lessons = $this->getCourseService()->findLessonsByIds($lessonIds);
        for ($i = 0; $i < count($noteInfos); $i++) {
            $note = $noteInfos[$i];
            $noteInfos[$i]["updatedTime"] = date('c', $note['createdTime']);
            $noteInfos[$i]["createdTime"] = date('c', $note['createdTime']);
            $noteInfos[$i]["lessonTitle"] = $lessons[$note["lessonId"]]["title"];
        }
        return $noteInfos;
    }
    
    public function getOneNote()
    {
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能查看笔记！");
        }
        $noteId = $this->getParam("noteId", 0);
        $noteInfo = $this->controller->getNoteService()->getNote($noteId);
        $lessonInfo = $this->controller->getCourseService()->getCourseLesson($noteInfo['courseId'], $noteInfo['lessonId']);
        $lessonStatus = $this->controller->getCourseService()->getUserLearnLessonStatus($user['id'], $noteInfo['courseId'], $noteInfo['lessonId']);
        $noteContent = $this->filterSpace($this->controller->convertAbsoluteUrl($this->request, $noteInfo['content']));
        $noteInfos = array(
            "courseId" => $noteInfo['courseId'],
            "courseTitle" => null,
            "noteLastUpdateTime" => null,
            "lessonId" => $lessonInfo['id'],
            "lessonTitle" => $lessonInfo['title'],
            "learnStatus" => $lessonStatus,
            "content" => $noteContent,
            "createdTime" => date('c', $noteInfo['createdTime']),
            "noteNum" => null,
            "largePicture" => null
        );
        return $noteInfos;
    }
    
    public function AddNote()
    {
        $courseId = $this->getParam("courseId", 0);
        $lessonId = $this->getParam("lessonId", 0);
        $content  = $this->getParam("content", "");
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能查看笔记！");
        }
        
        $noteInfo = array(
            'content' => $content,
            'lessonId' => $lessonId,
            'courseId' => $courseId
        );
        
        $content = $this->getParam("content", '');
        if (empty($content)) {
            return $this->createErrorResponse('wrong_content_param', "笔记内容不能为空！");
        }
        
        $noteInfo['content'] = $this->uploadImage($content);
        
        $result                = $this->controller->getNoteService()->saveNote($noteInfo);
        $result['content'] = $this->controller->convertAbsoluteUrl($this->request, $result['content']);
        if($result['updatedTime'] == 0){
            $result['updatedTime'] = $result['createdTime'];
        }
        $result['createdTime'] = date('c', $result['createdTime']);
        $result['updatedTime'] = date('c', $result['updatedTime']);
        
        return $result;
    }
    
    public function DeleteNote()
    {
        $id   = $this->getParam("id", 0);
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能查看笔记！");
        }
        return $this->controller->getNoteService()->deleteNote($id);
    }
    
    private function filterThreads($threads, $courses, $users)
    {
        if (empty($threads)) {
            return array();
        }
        
        for ($i = 0; $i < count($threads); $i++) {
            $thread = $threads[$i];
            if (!isset($courses[$thread["courseId"]])) {
                unset($threads[$i]);
                continue;
            }
            $course = $courses[$thread['courseId']];
            if ($thread["lessonId"] != 0) {
                $lessonInfo = $this->controller->getCourseService()->findLessonsByIds(array(
                    $thread["lessonId"]
                ));
                $thread["number"] = $lessonInfo[$thread["lessonId"]]["number"];
            } else {
                $thread["number"] = 0;
            }
            $threads[$i] = $this->filterThread($thread, $course, $users[$thread["userId"]]);
        }
        return $threads;
    }
    
    private function filterThread($thread, $course, $user)
    {
        $thread["courseTitle"] = $course["title"];
        
        $thread['coursePicture'] = $this->controller->coverPath($course["largePicture"], 'course-large.png');
        
        $isTeacherPost            = $this->controller->getThreadService()->findThreadElitePosts($course['id'], $thread['id'], 0, 100);
        $thread['isTeacherPost']  = empty($isTeacherPost) ? false : true;
        $user['smallAvatar']      = $this->controller->getContainer()->get('topxia.twig.web_extension')->getFilePath($user['smallAvatar'], 'course-large.png', true);
        $user['mediumAvatar']     = $this->controller->getContainer()->get('topxia.twig.web_extension')->getFilePath($user['mediumAvatar'], 'course-large.png', true);
        $user['largeAvatar']      = $this->controller->getContainer()->get('topxia.twig.web_extension')->getFilePath($user['largeAvatar'], 'course-large.png', true);
        $thread['user']           = $user;
        $thread['createdTime']    = date('c', $thread['createdTime']);
        $thread['latestPostTime'] = date('c', $thread['latestPostTime']);
        
        return $thread;
    }
    
    public function getThreadPost()
    {
        $courseId = $this->getParam("courseId", 0);
        $threadId = $this->getParam("threadId", 0);
        $start    = (int) $this->getParam("start", 0);
        $limit    = (int) $this->getParam("limit", 10);
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        
        $total = $this->controller->getThreadService()->getThreadPostCount($courseId, $threadId);
        $posts = $this->controller->getThreadService()->findThreadPosts($courseId, $threadId, 'elite', $start, $limit);
        $users = $this->controller->getUserService()->findUsersByIds(ArrayToolkit::column($posts, 'userId'));
        
        $controller = $this;
        $posts = array_map(function($post) use ($controller)
        {
            $post['content'] = $controller->filterSpace($controller->controller->convertAbsoluteUrl($controller->request, $post['content']));
            return $post;
        }, $posts);
        
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->filterPosts($posts, $this->controller->filterUsers($users))
        );
    }

    public function getOneThreadPost(){
        $courseId = $this->getParam("courseId", 0);
        $postId = $this->getParam("postId", 0);
        $user =  $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        $post = $this->controller->getThreadService()->getPost($courseId, $postId);
        if($post == null){
            return $this->createErrorResponse('no_post', '没有找到指定回复!');
        }else{
            $post['createdTime'] = Date('c', $post['createdTime']);
        }
        return $post;
    }
    
    public function getThread()
    {
        $courseId = $this->getParam("courseId", 0);
        $threadId = $this->getParam("threadId", 0);
        $user     = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        
        $thread = $this->controller->getThreadService()->getThread($courseId, $threadId);
        if (empty($thread)) {
            return $this->createErrorResponse('no_thread', '没有找到指定问答!');
        }
        
        $course            = $this->controller->getCourseService()->getCourse($thread['courseId']);
        $user              = $this->controller->getUserService()->getUser($thread['userId']);
        
        $user['following'] = $this->controller->getUserService()->findUserFollowingCount($user['id']);
        $user['follower']  = $this->controller->getUserService()->findUserFollowerCount($user['id']);
        $result            = $this->filterThread($thread, $course, $user);
        $result['content'] = $this->filterSpace($this->controller->convertAbsoluteUrl($this->request, $result['content']));
        return $result;
    }
    
    public function getThreadTeacherPost()
    {
        $courseId = $this->getParam("courseId", 0);
        $threadId = $this->getParam("threadId", 0);
        
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        
        $posts = $this->controller->getThreadService()->findThreadElitePosts($courseId, $threadId, 0, 100);
        $users = $this->controller->getUserService()->findUsersByIds(ArrayToolkit::column($posts, 'userId'));
        
        return $this->filterPosts($posts, $this->controller->filterUsers($users));
    }
    
    private function filterPosts($posts, $users)
    {
        return array_map(function($post) use ($users)
        {
            $post['user']        = $users[$post['userId']];
            $post['createdTime'] = date('c', $post['createdTime']);
            return $post;
        }, $posts);
    }
    
    public function getFavoriteLiveCourse()
    {
        $result = $this->getFavoriteCourse();
        $courses = $result["data"];

        $liveCourses = array();
        for ($i=0; $i < count($courses); $i++) {
            $course = $courses[$i];
            if ($course["type"] == "live") {
                $liveCourses[] = $course;
            } 
        }

        $result["data"] = $liveCourses;
        $result["total"] = count($liveCourses);
        return $result;
    }

    public function getFavoriteNormalCourse()
    {
        $result = $this->getFavoriteCourse();
        $courses = $result["data"];

        $normalCourses = array();
        for ($i=0; $i < count($courses); $i++) {
            $course = $courses[$i];
            if ($course["type"] == "normal") {
                $normalCourses[] = $course;
            } 
        }

        $result["data"] = array_values($normalCourses);
        $result["total"] = count($normalCourses);
        return $result;
    }

    public function getFavoriteCourse()
    {
        $user  = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        $start = (int) $this->getParam("start", 0);
        $limit = (int) $this->getParam("limit", 10);
        
        $total   = $this->controller->getCourseService()->findUserFavoritedCourseCount($user['id']);
        $courses = $this->controller->getCourseService()->findUserFavoritedCourses($user['id'], $start, $limit);
        
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->controller->filterCourses($courses)
        );
    }
    
    public function getCourseReviewInfo()
    {
        $courseId = $this->getParam("courseId", 0);
        $course = $this->controller->getCourseService()->getCourse($courseId);
        $total = $this->controller->getReviewService()->getCourseReviewCount($courseId);
        $reviews = $this->controller->getReviewService()->findCourseReviews($courseId, 0, $total);

        $progress = array(0, 0, 0, 0, 0);
        foreach ($reviews as $key => $review) {
            $rating = $review["rating"] < 1 ? 1 : $review["rating"];
            $progress[$review["rating"] - 1] ++;
        }
        return array(
            "info" => array(
                "ratingNum" => $course["ratingNum"],
                "rating" => $course["rating"],
            ),
            "progress" => $progress
        );
    }

    public function getReviews()
    {
        $courseId = $this->getParam("courseId");
        
        $start   = (int) $this->getParam("start", 0);
        $limit   = (int) $this->getParam("limit", 10);
        $total   = $this->controller->getReviewService()->getCourseReviewCount($courseId);
        $reviews = $this->controller->getReviewService()->findCourseReviews($courseId, $start, $limit);
        $reviews = $this->controller->filterReviews($reviews);
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $reviews
        );
    }
    
    
    public function favoriteCourse()
    {
        $user     = $this->controller->getUserByToken($this->request);
        $courseId = $this->getParam("courseId");
        
        if (empty($user) || !$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能收藏课程！");
        }
        
        if (!$this->controller->getCourseService()->hasFavoritedCourse($courseId)) {
            $this->controller->getCourseService()->favoriteCourse($courseId);
        }
        
        return true;
    }
    
    public function getTeacherCourses()
    {
        $userId = $this->getParam("userId");
        if (empty($userId)) {
            return array();
        }
        $courses = $this->controller->getCourseService()->findUserTeachCourses(array('userId'=>$userId), 0, 10);
        $courses = $this->controller->filterCourses($courses);
        return $courses;
    }
    
    public function unFavoriteCourse()
    {
        $user     = $this->controller->getUserByToken($this->request);
        $courseId = $this->getParam("courseId");
        
        if (empty($user) || !$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能收藏课程！");
        }
        
        
        if (!$this->controller->getCourseService()->hasFavoritedCourse($courseId)) {
            return $this->createErrorResponse('runtime_error', "您尚未收藏课程，不能取消收藏！");
        }
        
        try {
            $this->controller->getCourseService()->unfavoriteCourse($courseId);
        }
        catch (ServiceException $e) {
            return $this->createErrorResponse('runtime_error', $e->getMessage());
        }
        
        return true;
    }
    
    public function vipLearn()
    {
        if (!$this->controller->setting('vip.enabled')) {
            return $this->createErrorResponse('error', "网校没有开启vip功能");
        }
        
        $courseId = $this->getParam('courseId');
        $user     = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录，不能收藏课程！");
        }
        
        $vip = $this->controller->getVipService()->getMemberByUserId($user['id']);

        if (empty($vip)) {
            return $this->createErrorResponse('error', "用户不是vip会员!");
        }
        try {
            $this->controller->getCourseService()->becomeStudent($courseId, $user['id'], array(
                'becomeUseMember' => true
            ));
        }
        catch (ServiceException $e) {
            return $this->createErrorResponse('error', $e->getMessage());
        }

        return true;
    }
    
    public function coupon()
    {
        $code       = $this->getParam('code');
        $type       = $this->getParam('type');
        $courseId   = $this->getParam('courseId');
        //判断coupon是否合法，是否存在跟是否过期跟是否可用于当前课程
        $course     = $this->controller->getCourseService()->getCourse($courseId);
        $couponInfo = $this->getCouponService()->checkCouponUseable($code, $type, $courseId, $course['price']);
        
        $result["data"] = null;
        if (empty($couponInfo)) {
            return $this->createErrorResponse('error', "优惠码不存在!");
            return $result;
        } 

        if ($couponInfo["useable"] == "no") {
            return $this->createErrorResponse('error', "优惠码已使用!");
            return $result;
        }

        return $couponInfo;
    }
    
    public function unLearnCourse()
    {
        $courseId = $this->getParam("courseId");
        $user     = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
                return $this->createErrorResponse('no_login', "您尚未登录，不能查看该课时");
        }
        list($course, $member) = $this->controller->getCourseService()->tryTakeCourse($courseId);
        
        if (empty($member)) {
                return $this->createErrorResponse('error', "您不是课程的学员或尚未购买该课程，不能退学。");
        }
        if (!empty($member['orderId'])) {
            $order = $this->getOrderService()->getOrder($member['orderId']);
            if (empty($order)) {
                return $this->createErrorResponse('error', "订单不存在，不能退学。");
            }
            
            $reason = $this->getParam("reason", "");
            $amount = $this->getParam("amount", 0);
            $refund = $this->getCourseOrderService()->applyRefundOrder($member['orderId'], $amount, array(
                "type" => "other",
                "note" => $reason
            ), $this->getContainer());
            if (empty($refund) || $refund['status'] != "success") {
                return $this->createErrorResponse('error', "退出课程失败");
            }
            return true;
        }
        
        try {
            $this->getCourseService()->removeStudent($course['id'], $user['id']);
        } catch(\Exception $e) {
                return $this->createErrorResponse('error', $e->getMessage());
        }
        
        return true;
    }
    
    public function getCourse()
    {
        $user     = $this->controller->getUserByToken($this->request);
        $courseId = $this->getParam("courseId");
        $course   = $this->controller->getCourseService()->getCourse($courseId);
        
        if (empty($course)) {
            return $this->createErrorResponse('not_found', "课程不存在");
        }
        
        $member = $user->isLogin() ? $this->controller->getCourseService()->getCourseMember($course['id'], $user['id']) : null;
        $member = $this->previewAsMember($member, $courseId, $user);
        if ($member && $member['locked']) {
            return $this->createErrorResponse('member_locked', "会员被锁住，不能访问课程，请联系管理员!");
        }
        if ($course['status'] != 'published') {
            if (!$user->isLogin()) {
                return $this->createErrorResponse('course_not_published', "课程未发布或已关闭。");
            }
            if (empty($member)) {
                return $this->createErrorResponse('course_not_published', "课程未发布或已关闭。");
            }
            $deadline    = $member['deadline'];
            $createdTime = $member['createdTime'];
            
            if ($deadline != 0 && ($deadline - $createdTime) < 0) {
                return $this->createErrorResponse('course_not_published', "课程未发布或已关闭。");
            }
        }
        
        if(empty($member)) {
            $member = $this->getCourseService()->becomeStudentByClassroomJoined($courseId, $user["id"]);
            if (empty($member)) {
                $member = null;
            }
        }

        $userFavorited = $user->isLogin() ? $this->controller->getCourseService()->hasFavoritedCourse($courseId) : false;
        $vipLevels     = array();
        if ($this->controller->setting('vip.enabled')) {
            $vipLevels = $this->controller->getLevelService()->searchLevels(array(
                'enabled' => 1
            ), 0, 100);
        }
        $course["source"] = $this->setCourseTarget($course['id']);
        return array(
            "course" => $this->controller->filterCourse($course),
            "userFavorited" => $userFavorited,
            "member" => $this->checkMemberStatus($member),
            "vipLevels" => $vipLevels,
            "discount" => $this->getCourseDiscount($course["discountId"])
        );
    }

    private function setCourseTarget($courseId)
    {
        $classroom = $this->getClassroomService()->findClassroomByCourseId($courseId);

        return empty($classroom) ? null : 'classroom';
    }
    
    private function getCourseDiscount($discountId)
    {
        if ($this->controller->isinstalledPlugin("Discount")) {
            $discount = $this->getDiscountService()->getDiscount($discountId);
            if (empty($discount)) {
                return null;
            }
            $discount["startTime"] = date("c", $discount["startTime"]);
            $discount["endTime"] = date("c", $discount["endTime"]);
            $discount["changeTime"] = date("c", $discount["changeTime"]);
            $discount["auditedTime"] = date("c", $discount["auditedTime"]);
            $discount["createdTime"] = date("c", $discount["createdTime"]);

            return $discount;
        }
        return null;
    }

    public function searchCourse()
    {
        $search = $this->getParam("search", '');
        $tagId = $this->getParam("tagId", '');
        $type = $this->getParam("type", 'normal');
        $categoryId = (int) $this->getParam("categoryId", 0);
        
        if ($categoryId !=0 ) {
            $conditions['categoryId'] = $categoryId;
        }
        
        $conditions['title'] = $search;
        
        if (empty($tagId)) {
            $conditions['title'] = $search;
        } else {
            $conditions['tagId'] = $tagId;
        }
        return $this->findCourseByConditions($conditions, $type);
    }
    
    public function getCourses()
    {
        $categoryId               = (int) $this->getParam("categoryId", 0);
        $conditions               = array();

        if($categoryId != 0) {
            $conditions['categoryId'] = $categoryId;
        }
        return $this->findCourseByConditions($conditions,"normal");
    }
    
    private function findCourseByConditions($conditions, $type)
    {
        $conditions['status'] = 'published';
        $conditions['parentId'] = '0';
        if(empty($type)){
            unset($conditions['type']);
        }else{
            $conditions['type']   = $type;
        }
        
        $start = (int) $this->getParam("start", 0);
        $limit = (int) $this->getParam("limit", 10);

        $total = $this->controller->getCourseService()->searchCourseCount($conditions);
        
        $sort               = $this->getParam("sort", "latest");
        $courses = $this->controller->getCourseService()->searchCourses($conditions, $sort, $start, $limit);
        $result = array(
            "start"=>$start,
            "limit"=>$limit,
            "total"=>$total,
            "data"=>$this->controller->filterCourses($courses)
            );
        return $result;
    }
    
    public function getLearnedCourse()
    {
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }
        
        $start   = (int) $this->getParam("start", 0);
        $limit   = (int) $this->getParam("limit", 10);
        $total   = $this->controller->getCourseService()->findUserLeanedCourseCount($user['id']);
        $courses = $this->controller->getCourseService()->findUserLeanedCourses($user['id'], $start, $limit);
        
        $result = array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->controller->filterCourses($courses)
        );
        return $result;
    }

    public function getLearningCourseWithoutToken(){
        $userId = $this->getParam('userId');
        if (empty($userId)) {
            return $this->createErrorResponse('userId', "userId参数错误");
        }
        
        $start   = (int) $this->getParam("start", 0);
        $limit   = (int) $this->getParam("limit", 10);
        $total   = $this->controller->getCourseService()->findUserLeaningCourseCount($userId);
        $courses = $this->controller->getCourseService()->findUserLeaningCourses($userId, $start, $limit);
        
        $count            = $this->controller->getCourseService()->searchLearnCount(array(
        ));
        $learnStatusArray = $this->controller->getCourseService()->searchLearns(array(
            "userId" => $userId
        ), array(
            "finishedTime",
            "ASC"
        ), 0, $count);
        
        $lessons = $this->controller->getCourseService()->findLessonsByIds(ArrayToolkit::column($learnStatusArray, 'lessonId'));
        
        $tempCourse = array();
        foreach ($courses as $key => $course) {
            $tempCourse[$course["id"]] = $course;
        }
        
        foreach ($lessons as $key => $lesson) {
            $courseId = $lesson["courseId"];
            if (isset($tempCourse[$courseId])) {
                $tempCourse[$courseId]["lastLessonTitle"] = $lesson["title"];
            }
        }
        
        $result = array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->controller->filterCourses(array_values($tempCourse))
        );
        return $result;
    }
    
    public function getUserTeachCourse()
    {
        $userId = $this->getParam("userId", 0);
        $start = $this->getParam('start', 0);
        $limit = $this->getParam('limit', 10);

        $conditions = array(
            'userId' => $userId,
            'parentId' => 0
        );

        $total = $this->controller->getCourseService()->findUserTeachCourseCount($conditions);

        $courses = $this->controller->getCourseService()->findUserTeachCourses(
            $conditions,
            $start,
            $limit
        );

        return array(
            "start"=>$start,
            "total"=>$total,
            "limit"=>$limit,
            "data"=>$this->controller->filterCourses($courses)
        );
    }

    public function getLearningCourse()
    {

        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }
        
        $start   = (int) $this->getParam("start", 0);
        $limit   = (int) $this->getParam("limit", 10);
        $type    = $this->getParam("type","");

        $filter = array();
        if(empty($type)){
            $filter = array("type"=>"normal");
        }
        $total   = $this->controller->getCourseService()->findUserLeaningCourseCount($user['id'], $filter);
        $courses = $this->controller->getCourseService()->findUserLeaningCourses($user['id'], $start, $limit, $filter);
        
        $count = $this->controller->getCourseService()->searchLearnCount(array(
            "userId" => $user["id"]
        ));
        $learnStatusArray = $this->controller->getCourseService()->searchLearns(array(
            "userId" => $user["id"]
        ), array(
            "finishedTime",
            "ASC"
        ), 0, $count);

        $lessons = $this->controller->getCourseService()->findLessonsByIds(ArrayToolkit::column($learnStatusArray, 'lessonId'));
        
        $tempCourses = array();
        foreach ($courses as $key => $course) {
            $tempCourses[$course["id"]] = $course;
        }
        
        $learnStatusArray = $this->coverLearnStatusTime($learnStatusArray);
        foreach ($lessons as $key => $lesson) {
            $courseId = $lesson["courseId"];
            if (isset($tempCourses[$courseId])) {
                $tempCourses[$courseId]["startTime"] = $learnStatusArray[$courseId];
                $tempCourses[$courseId]["lastLessonTitle"] = $lesson["title"];
            }
        }

        $result = array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->controller->filterCourses(array_values($tempCourses))
        );

        return $result;
    }
    
    private function coverLearnStatusTime($learnStatusArray)
    {
        $map = array();
        foreach ($learnStatusArray as $key => $learnStatus) {
            $map[$learnStatus["courseId"]] = date("c", $learnStatus["startTime"]);
        }

        return $map;
    }

    public function getLearnStatus()
    {
        $courseId = $this->getParam("courseId");
        $user     = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }
        
        $course      = $this->controller->getCourseService()->getCourse($courseId);
        $learnStatus = $this->controller->getCourseService()->getUserLearnLessonStatuses($user['id'], $courseId);
        if (!empty($course)) {
            $member   = $this->controller->getCourseService()->getCourseMember($course['id'], $user['id']);
            $progress = $this->calculateUserLearnProgress($course, $member);
        } else {
            $course   = array();
            $progress = array();
        }
        
        foreach ($learnStatus as $key => $value) {
            if ($value == "finished") {
                unset($learnStatus[$key]);
            }
        }
        $keys     = array_keys($learnStatus);
        $lessonId = end($keys);
        $lesson   = $this->controller->getCourseService()->getCourseLesson($courseId, $lessonId);
        return array(
            "data" => $lesson,
            'progress' => $progress
        );
    }
    
    private function calculateUserLearnProgress($course, $member)
    {
        if ($course['lessonNum'] == 0) {
            return array(
                'percent' => '0%',
                'number' => 0,
                'total' => 0
            );
        }
        
        $percent = intval($member['learnedNum'] / $course['lessonNum'] * 100) . '%';
        
        return array(
            'percent' => $percent,
            'number' => $member['learnedNum'],
            'total' => $course['lessonNum']
        );
    }
    
    private function checkMemberStatus($member)
    {
        if ($member) {
            $deadline = $member['deadline'];
            if ($deadline == 0) {
                return $member;
            }
            $remain = $deadline - time();
            if ($remain <= 0) {
                $member['deadline'] = -1;
            } else {
                $member['deadline'] = $remain;
            }
        }
        return $member;
    }

    public function getLiveCourse(){
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }

        $courseId = $this->getParam("courseId", 0);
        $lessonId = $this->getParam("lessonId", 0);
        $lesson = $this->controller->getCourseService()->getCourseLesson($courseId, $lessonId);
        $now = time();
        $params = array();

        $params['email'] = 'live-' . $user['id'] . '@edusoho.net';
        $params['nickname'] = $user['nickname'];

        $params['sign'] = "c{$lesson['courseId']}u{$user['id']}t{$now}";
        $params['sign'] .= 's' . $this->makeSign($params['sign']);

        $params['liveId'] = $lesson['mediaId'];
        $params['provider'] = $lesson["liveProvider"];
        $params['role'] = 'student';


        $params['user'] = $params['email'];

        $client = new EdusohoLiveClient();
        if(isset($lesson['replayStatus']) && $lesson['replayStatus'] == 'generated'){
            $result = $client->entryReplay($params, 'root');
        } else {
            $result = $client->getRoomUrl($params, 'root');

        }

        return array('data' =>
            array(
            'lesson' => $lesson,
            'result' => $result,
        ));
    }


    protected function makeSign($string)
    {
        $secret = $this->controller->getContainer()->getParameter('secret');
        return md5($string . $secret);
    }

    public function getLiveCourses()
    {
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }
        
        $start   = (int) $this->getParam("start", 0);
        $limit   = (int) $this->getParam("limit", 10);

        $courses = $this->controller->getCourseService()->findUserLeaningCourses(
            $user['id'], $start, 1000, array('type'=>'live')
        );
        $courseIds = ArrayToolkit::column($courses, 'id');

        $conditions = array(
            'status' => 'published',
            'startTimeGreaterThan' => time(),
            'courseIds' => $courseIds
        );

        $count = $this->controller->getCourseService()->searchLessonCount($conditions);

        $lessons = $this->controller->getCourseService()->searchLessons(
            $conditions,  
            array('startTime', 'ASC'), 
            $start,
            $limit
        );

        $newCourses = array();

        $courses = ArrayToolkit::index($courses, 'id');

        if (!empty($courses)) {
            foreach ($lessons as $key => &$lesson) {
                $newCourses[$key] = $courses[$lesson['courseId']];
                $newCourses[$key]["liveLessonTitle"] = $lesson["title"];
                $newCourses[$key]["liveStartTime"] = date("c", $lesson["startTime"]);
                $newCourses[$key]["liveEndTime"] = date("c", $lesson["endTime"]);
                unset($courses[$lesson['courseId']]);
            }

            foreach ($courses as $key => &$course) {
                $course["liveLessonTitle"] = '';
                $course["liveStartTime"] = '';
                $course["liveEndTime"] = '';
            }
        }

        $newCourses = array_merge($newCourses, $courses);
        $resultLiveCourses = $this->controller->filterCourses(array_values($newCourses));

        return array(
            "start" => $start + count($resultLiveCourses),
            "limit" => $limit,
            "data" => $resultLiveCourses);
    }

    public function hitThread(){
        $courseId = $this->getParam("courseId", 0);
        $threadId = $this->getParam("threadId", 0);
        if(empty($courseId) || empty($threadId)){
            return $this->createErrorResponse('wrong threadId', "问答不存在或已删除");
        }
        return $this->controller->getThreadService()->hitThread($courseId, $threadId);
    }

    public function getAllLiveCourses(){
        $start = $this->getParam("start", 0);
        $limit = $this->getParam("limit", 10);
        $condition = array(
            'parentId'=> 0,
            'status' => 'published',
            'type' => 'live'
        );

        $total = $this->controller->getCourseService()->searchCourseCount($condition);  
        $liveCourses = $this->controller->getCourseService()->searchCourses($condition, 'lastest',$start, $limit);

        $liveCourses = array_map(function($liveCourse){
            return $liveCourse;
        },$liveCourses);

        $result = array(
            "start" => $start,
            "limit" => $limit,
            "total" => $total,
            "data" => $this->controller->filterCourses($liveCourses));
        return $result;
    }

    public function getModifyInfo()
    {
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }

        $courseSetting = $this->getSettingService()->get('course', array());

        $userinfoFields = array();
        $userInfo = $this->getUserService()->getUserProfile($user['id']);
        foreach ($courseSetting["userinfoFields"] as $key) {
            $field = array();
            switch ($key) {
                case 'truename':
                    $field = array(
                        "name"=>$key,
                        "title"=>"真实姓名"
                        );
                    break;
                case 'mobile':
                    $field = array(
                        "name"=>$key,
                        "title"=>"手机"
                        );
                    break;
                case 'qq':
                    $field = array(
                        "name"=>$key,
                        "title"=>"QQ"
                        );
                    break;
                case 'job':
                    $field = array(
                        "name"=>$key,
                        "title"=>"职业"
                        );
                    break;
                case 'gender':
                    $field = array(
                        "name"=>$key,
                        "title"=>"性别"
                        );
                    break;
                case 'idcard':
                    $field = array(
                        "name"=>$key,
                        "title"=>"身份证"
                        );
                    break;
                case 'weibo':
                    $field = array(
                        "name"=>$key,
                        "title"=>"微博"
                        );
                    break;
                case 'weixin':
                    $field = array(
                        "name"=>$key,
                        "title"=>"微信"
                        );
                    break;
            }
            $field["content"] = $userInfo[$key];
            $userinfoFields[] = $field;
        }
        return array(
            "buy_fill_userinfo"=>$courseSetting["buy_fill_userinfo"] ? true : false,
            "modifyInfos"=>$userinfoFields
        );
    }

    public function updateModifyInfo()
    {
        $fields = $this->request->request->all();
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }

        $course = $this->getCourseService()->getCourse($fields['targetId']);
        if (empty($course)) {
            return $this->createErrorResponse('error', '课程不存在，不能购买。');
        }

        $userInfo = ArrayToolkit::parts($fields, array(
            'truename',
            'mobile',
            'qq',
            'company',
            'weixin',
            'weibo',
            'idcard',
            'gender',
            'job',
            'intField1','intField2','intField3','intField4','intField5',
            'floatField1','floatField2','floatField3','floatField4','floatField5',
            'dateField1','dateField2','dateField3','dateField4','dateField5',
            'varcharField1','varcharField2','varcharField3','varcharField4','varcharField5','varcharField10','varcharField6','varcharField7','varcharField8','varcharField9',
            'textField1','textField2','textField3','textField4','textField5', 'textField6','textField7','textField8','textField9','textField10',
        ));

        try {
            $userInfo = $this->getUserService()->updateUserProfile($user['id'], $userInfo);
        } catch(\Exception $e) {
            return $this->createErrorResponse('error', $e->getMessage());
        }
        
        return true;
    }

    protected function getDiscountService() 
    {
        return $this->controller->getService('Discount:Discount.DiscountService');
    }

    private function getClassroomService() 
    {
        return $this->controller->getService('Classroom:Classroom.ClassroomService');
    }
}