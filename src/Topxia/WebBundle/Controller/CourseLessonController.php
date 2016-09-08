<?php
namespace Topxia\WebBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\FileToolkit;
use Topxia\Common\ArrayToolkit;
use Topxia\Service\Util\CloudClientFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseLessonController extends BaseController
{
    //加载播放器的地址
    public function playerAction(Request $request, $courseId, $lessonId = 0)
    {
        $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        if (empty($lesson)) {
            throw $this->createNotFoundException();
        }

        $isPreview = $request->query->get('isPreview', '');
        $timeLimit = $this->setting('magic.lesson_watch_time_limit');

        if (($isPreview && $lesson["free"]) || !empty($timeLimit)) {
            return $this->forward('TopxiaWebBundle:Player:show', array(
                'id' => $lesson["mediaId"]
            ));
        }

        list($course, $member) = $this->getCourseService()->tryTakeCourse($courseId);
        $context               = array();
        $context['starttime']  = $request->query->get('starttime');

        return $this->forward('TopxiaWebBundle:Player:show', array(
            'id'      => $lesson["mediaId"],
            'context' => $context
        ));
    }

    public function previewAction(Request $request, $courseId, $lessonId = 0)
    {
        $course = $this->getCourseService()->getCourse($courseId);

        if (empty($lessonId)) {
            $lessonId = $request->query->get('lessonId');
        }

        $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        if (empty($lesson)) {
            throw $this->createNotFoundException();
        }

        //开启限制加入

        if (empty($lesson['free']) && empty($course['buyable'])) {
            return $this->render('TopxiaWebBundle:CourseLesson:preview-notice-modal.html.twig', array('course' => $course));
        }

        if (!empty($course['status']) && $course['status'] == 'closed') {
            return $this->render('TopxiaWebBundle:CourseLesson:preview-notice-modal.html.twig', array('course' => $course));
        }

        $timelimit = $this->setting('magic.lesson_watch_time_limit');

        $user = $this->getCurrentUser();

        //课时不免费并且不满足1.有时间限制设置2.课时为视频课时3.视频课时非优酷等外链视频时提示购买

        if (empty($lesson['free']) && !($timelimit && $lesson['type'] == 'video' && $lesson['mediaSource'] == 'self')) {
            if (!$user->isLogin()) {
                throw $this->createAccessDeniedException();
            }

            if ($course["parentId"] > 0) {
                return $this->redirect($this->generateUrl('classroom_buy_hint', array('courseId' => $course["id"])));
            }

            return $this->forward('TopxiaWebBundle:CourseOrder:buy', array('id' => $courseId), array('preview' => true, 'lessonId' => $lesson['id']));
        }

        //在可预览情况下查看网站设置是否可匿名预览
        $allowAnonymousPreview = $this->setting('course.allowAnonymousPreview', 1);

        if (empty($allowAnonymousPreview) && !$user->isLogin()) {
            throw $this->createAccessDeniedException();
        }

        $hasVideoWatermarkEmbedded = 0;

        if ($lesson['type'] == 'video' && $lesson['mediaSource'] == 'self') {
            $file = $this->getUploadFileService()->getFile($lesson['mediaId']);

            if (empty($lesson['free']) && empty($timelimit) && $file['storage'] != 'cloud') {
                if (!$user->isLogin()) {
                    throw $this->createAccessDeniedException();
                }

                return $this->forward('TopxiaWebBundle:CourseOrder:buy', array('id' => $courseId), array('preview' => true, 'lessonId' => $lesson['id']));
            }

            if (!empty($file['metas2']) && !empty($file['metas2']['sd']['key'])) {
                $factory = new CloudClientFactory();
                $client  = $factory->createClient();
                $hls     = $client->generateHLSQualitiyListUrl($file['metas2'], 3600);

                if (isset($file['convertParams']['convertor']) && ($file['convertParams']['convertor'] == 'HLSEncryptedVideo')) {
                    $token = $this->getTokenService()->makeToken('hls.playlist', array(
                        'data'     => array(
                            'id' => $file['id']
                        ),
                        'times'    => $this->agentInWhiteList($request->headers->get("user-agent")) ? 0 : 3,
                        'duration' => 3600
                    ));

                    $hls = array(
                        'url' => $this->generateUrl('hls_playlist', array(
                            'id'    => $file['id'],
                            'token' => $token['token'],
                            'line'  => $request->query->get('line')
                        ), true)
                    );
                } else {
                    $hls = $client->generateHLSQualitiyListUrl($file['metas2'], 3600);
                }
            }

            if (!empty($file['convertParams']['hasVideoWatermark'])) {
                $hasVideoWatermarkEmbedded = 1;
            }
        } elseif ($lesson['mediaSource'] == 'youku') {
            $matched = preg_match('/\/sid\/(.*?)\/v\.swf/s', $lesson['mediaUri'], $matches);

            if ($matched) {
                $lesson['mediaUri']    = "http://player.youku.com/embed/{$matches[1]}";
                $lesson['mediaSource'] = 'iframe';
            } else {
                $lesson['mediaUri'] = $lesson['mediaUri'];
            }
        } elseif ($lesson['mediaSource'] == 'tudou') {
            $matched = preg_match('/\/v\/(.*?)\/v\.swf/s', $lesson['mediaUri'], $matches);

            if ($matched) {
                $lesson['mediaUri']    = "http://www.tudou.com/programs/view/html5embed.action?code={$matches[1]}";
                $lesson['mediaSource'] = 'iframe';
            } else {
                $lesson['mediaUri'] = $lesson['mediaUri'];
            }
        }

        //判断用户是否为VIP
        $vipStatus = $courseVip = null;

        if ($this->isPluginInstalled('Vip') && $this->setting('vip.enabled')) {
            $courseVip = $course['vipLevelId'] > 0 ? $this->getLevelService()->getLevel($course['vipLevelId']) : null;

            if ($courseVip) {
                $vipStatus = $this->getVipService()->checkUserInMemberLevel($user['id'], $courseVip['id']);
            }
        }

        return $this->render('TopxiaWebBundle:CourseLesson:preview-modal.html.twig', array(
            'user'                      => $user,
            'course'                    => $course,
            'lesson'                    => $lesson,
            'hasVideoWatermarkEmbedded' => $hasVideoWatermarkEmbedded,
            'hlsUrl'                    => (isset($hls) && is_array($hls) && !empty($hls['url'])) ? $hls['url'] : '',
            'vipStatus'                 => $vipStatus
        ));
    }

    public function showAction(Request $request, $courseId, $lessonId)
    {
        list($course, $member) = $this->getCourseService()->tryTakeCourse($courseId);

        $lesson         = $this->getCourseService()->getCourseLesson($courseId, $lessonId);
        $json           = array();
        $json['number'] = $lesson['number'];
        $chapter        = empty($lesson['chapterId']) ? null : $this->getCourseService()->getChapter($course['id'], $lesson['chapterId']);

        if ($chapter['type'] == 'unit') {
            $unit               = $chapter;
            $json['unit']       = $unit;
            $json['unitNumber'] = $unit['number'];

            $chapter               = $this->getCourseService()->getChapter($course['id'], $unit['parentId']);
            $json['chapter']       = $chapter;
            $json['chapterNumber'] = empty($chapter) ? 0 : $chapter['number'];
        } else {
            $json['chapterNumber'] = empty($chapter) ? 0 : $chapter['number'];
            $json['unitNumber']    = 0;
        }

        $json['title']                  = $lesson['title'];
        $json['summary']                = $lesson['summary'];
        $json['type']                   = $lesson['type'];
        $json['content']                = $lesson['content'];
        $json['status']                 = $lesson['status'];
        $json['quizNum']                = $lesson['quizNum'];
        $json['materialNum']            = $lesson['materialNum'];
        $json['mediaId']                = $lesson['mediaId'];
        $json['mediaSource']            = $lesson['mediaSource'];
        $json['startTimeFormat']        = date("m-d H:i", $lesson['startTime']);
        $json['endTimeFormat']          = date("H:i", $lesson['endTime']);
        $json['startTime']              = $lesson['startTime'];
        $json['endTime']                = $lesson['endTime'];
        $json['id']                     = $lesson['id'];
        $json['courseId']               = $lesson['courseId'];
        $json['videoWatermarkEmbedded'] = 0;
        $json['liveProvider']           = $lesson["liveProvider"];
        $json['nowDate']                = time();
        $json['testMode']               = $lesson['testMode'];
        $json['testStartTime']          = $lesson['testStartTime'];
        $json['testStartTimeFormat']    = date("m-d H:i", $lesson['testStartTime']);

        if ($lesson['testMode'] == 'realTime') {
            $testpaper                 = $this->getTestpaperService()->getTestpaper($lesson['mediaId']);
            $json['limitedTime']       = $testpaper['limitedTime'];
            $minute                    = '+'.$testpaper['limitedTime'].'minute';
            $json['testEndTime']       = strtotime($minute, $lesson['testStartTime']);
            $json['testEndTimeFormat'] = date("m-d H:i", $json['testEndTime']);
        }

        $app = $this->getAppService()->findInstallApp('Homework');

        if (!empty($app)) {
            $homework                      = $this->getHomeworkService()->getHomeworkByLessonId($lesson['id']);
            $exercise                      = $this->getExerciseService()->getExerciseByLessonId($lesson['id']);
            $json['homeworkOrExerciseNum'] = $homework['itemCount'] + $exercise['itemCount'];
        } else {
            $json['homeworkOrExerciseNum'] = 0;
        }

        $json['isTeacher'] = $this->getCourseService()->isCourseTeacher($courseId, $this->getCurrentUser()->id);

        if ($lesson['type'] == 'live' && $lesson['replayStatus'] == 'generated') {
            $replaysLesson  = $this->getCourseService()->getCourseLessonReplayByLessonId($lesson['id']);
            $visableReplays = array();

            foreach ($replaysLesson as $key => $value) {
                if ($value['hidden'] == 0) {
                    $visableReplays[] = $value;
                }
            }

            $json['replays'] = $visableReplays;

            if (!empty($json['replays'])) {
                foreach ($json['replays'] as $key => $value) {
                    $url = $this->generateUrl('live_course_lesson_replay_entry', array(
                        'courseId'             => $lesson['courseId'],
                        'lessonId'             => $lesson['id'],
                        'courseLessonReplayId' => $value['id']
                    ), true);
                    $json['replays'][$key]["url"] = $url;
                }
            }
        }

        if ($json['mediaSource'] == 'self') {
            $file = $this->getUploadFileService()->getFile($lesson['mediaId']);

            if (!empty($file)) {
                if ($file['storage'] == 'cloud') {
                    $factory = new CloudClientFactory();
                    $client  = $factory->createClient();

                    $json['mediaConvertStatus'] = $file['convertStatus'];

                    if (!empty($file['convertParams']['hasVideoWatermark'])) {
                        $json['videoWatermarkEmbedded'] = 1;
                    }

                    if (!empty($file['metas2']) && !empty($file['metas2']['sd']['key'])) {
                        if (isset($file['convertParams']['convertor']) && ($file['convertParams']['convertor'] == 'HLSEncryptedVideo')) {
                            $token = $this->getTokenService()->makeToken('hls.playlist', array(
                                'data'     => $file['id'],
                                'times'    => $this->agentInWhiteList($request->headers->get("user-agent")) ? 0 : 3,
                                'duration' => 3600,
                                'userId'   => $this->getCurrentUser()->getId()
                            ));

                            $url = array(
                                'url' => $this->generateUrl('hls_playlist', array(
                                    'id'    => $file['id'],
                                    'token' => $token['token'],
                                    'line'  => $request->query->get('line')
                                ), true)
                            );
                        } else {
                            $url = $client->generateHLSQualitiyListUrl($file['metas2'], 3600);
                        }

                        $json['mediaHLSUri'] = $url['url'];

                        if ($this->setting('magic.lesson_watch_limit') && $course['watchLimit'] > 0) {
                            $user        = $this->getCurrentUser();
                            $watchStatus = $this->getCourseService()->checkWatchNum($user['id'], $lesson['id']);

                            if ($watchStatus['status'] == 'error') {
                                $wathcLimitTime     = $this->container->get('topxia.twig.web_extension')->durationTextFilter($watchStatus['watchLimitTime']);
                                $json['mediaError'] = "您的观看时长已到 <strong>{$wathcLimitTime}</strong>，不能再观看。";
                            }
                        }
                    } elseif ($file['type'] == 'ppt') {
                        $json['mediaUri'] = $this->generateUrl('course_lesson_ppt', array('courseId' => $course['id'], 'lessonId' => $lesson['id']));
                    } else {
                        if (!empty($file['metas']) && !empty($file['metas']['hd']['key'])) {
                            $key = $file['metas']['hd']['key'];
                        } else {
                            if ($file['type'] == 'video') {
                                $key = null;
                            } else {
                                $key = $file['hashId'];
                            }
                        }

                        if ($key) {
                            $url              = $client->generateFileUrl($client->getBucket(), $key, 3600);
                            $json['mediaUri'] = $url['url'];
                        } else {
                            $json['mediaUri'] = '';
                        }
                    }
                } else {
                    $json['mediaUri'] = $this->generateUrl('course_lesson_media', array('courseId' => $course['id'], 'lessonId' => $lesson['id']));

                    if ($this->setting('magic.lesson_watch_limit') && $course['watchLimit'] > 0) {
                        $user        = $this->getCurrentUser();
                        $watchStatus = $this->getCourseService()->checkWatchNum($user['id'], $lesson['id']);

                        if ($watchStatus['status'] == 'error') {
                            $wathcLimitTime     = $this->container->get('topxia.twig.web_extension')->durationTextFilter($watchStatus['watchLimitTime']);
                            $json['mediaError'] = "您的观看时长已到 <strong>{$wathcLimitTime}</strong>，不能再观看。";
                        }
                    }
                }
            } else {
                $json['mediaUri'] = '';

                if ($lesson['type'] == 'video') {
                    $json['mediaError'] = '抱歉，视频文件不存在，暂时无法学习。';
                } elseif ($lesson['type'] == 'audio') {
                    $json['mediaError'] = '抱歉，音频文件不存在，暂时无法学习。';
                } elseif ($lesson['type'] == 'ppt') {
                    $json['mediaError'] = '抱歉，PPT文件不存在，暂时无法学习。';
                }
            }
        } elseif ($json['mediaSource'] == 'youku' && $this->isMobile()) {
            $matched = preg_match('/\/sid\/(.*?)\/v\.swf/s', $lesson['mediaUri'], $matches);

            if ($matched) {
                $json['mediaUri']    = "http://player.youku.com/embed/{$matches[1]}";
                $json['mediaSource'] = 'iframe';
            } else {
                $json['mediaUri'] = $lesson['mediaUri'];
            }
        } elseif ($json['mediaSource'] == 'tudou') {
            $matched = preg_match('/\/v\/(.*?)\/v\.swf/s', $lesson['mediaUri'], $matches);

            if ($matched) {
                $json['mediaUri']    = "http://www.tudou.com/programs/view/html5embed.action?code={$matches[1]}";
                $json['mediaSource'] = 'iframe';
            } else {
                $json['mediaUri'] = $lesson['mediaUri'];
            }
        } else {
            $json['mediaUri'] = $lesson['mediaUri'];
        }

        $json['canLearn'] = $this->getCourseService()->canLearnLesson($lesson['courseId'], $lesson['id']);

        return $this->createJsonResponse($json);
    }

    public function mediaAction(Request $request, $courseId, $lessonId)
    {
        $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        if (empty($lesson) || empty($lesson['mediaId']) || ($lesson['mediaSource'] != 'self')) {
            throw $this->createNotFoundException();
        }

        if (!$lesson['free']) {
            $this->getCourseService()->tryTakeCourse($courseId);
        }

        $file = $this->getUploadFileService()->getFile($lesson['mediaId']);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if ($file['storage'] == 'cloud') {
            throw $this->createNotFoundException();
        }

        return $this->createLocalMediaResponse($request, $file, false);
    }

    public function detailDataAction($courseId, $lessonId)
    {
        $students = array();
        $lesson   = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        $count     = $this->getCourseService()->searchLearnCount(array('courseId' => $courseId, 'lessonId' => $lessonId));
        $paginator = new Paginator($this->get('request'), $count, 20);

        $learns = $this->getCourseService()->searchLearns(array('courseId' => $courseId, 'lessonId' => $lessonId), array('startTime', 'ASC'), $paginator->getOffsetCount(), $paginator->getPerPageCount());

        foreach ($learns as $key => $learn) {
            $user                           = $this->getUserService()->getUser($learn['userId']);
            $students[$key]['nickname']     = $user['nickname'];
            $students[$key]['startTime']    = $learn['startTime'];
            $students[$key]['finishedTime'] = $learn['finishedTime'];
            $students[$key]['learnTime']    = $learn['learnTime'];
            $students[$key]['watchTime']    = $learn['watchTime'];

            if ($lesson['type'] == 'testpaper') {
                $paperId = $lesson['mediaId'];
                $score   = $this->getTestpaperService()->findTestpaperResultByTestpaperIdAndUserIdAndActive($paperId, $user['id']);

                $students[$key]['result'] = $score['score'];
            }
        }

        $lesson['length'] = intval($lesson['length'] / 60);

        return $this->render('TopxiaWebBundle:CourseLesson:lesson-data-modal.html.twig', array(
            'lesson'    => $lesson,
            'paginator' => $paginator,
            'students'  => $students
        ));
    }

    public function mediaDownloadAction(Request $request, $courseId, $lessonId)
    {
        if (!$this->setting('course.student_download_media')) {
            return $this->createMessageResponse('未开启课时音视频下载。');
        }

        $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        if (empty($lesson) || empty($lesson['mediaId']) || ($lesson['mediaSource'] != 'self')) {
            throw $this->createNotFoundException();
        }

        $this->getCourseService()->tryTakeCourse($courseId);

        return $this->fileAction($request, $lesson['mediaId'], true);
    }

    public function pptAction(Request $request, $courseId, $lessonId)
    {
        $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        if (empty($lesson)) {
            throw $this->createNotFoundException();
        }

        if (!$lesson['free']) {
            $this->getCourseService()->tryTakeCourse($courseId);
        }

        if ($lesson['type'] != 'ppt' || empty($lesson['mediaId'])) {
            throw $this->createNotFoundException();
        }

        $file = $this->getUploadFileService()->getFile($lesson['mediaId']);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if ($file['convertStatus'] != 'success') {
            if ($file['convertStatus'] == 'error') {
                $url     = $this->generateUrl('course_manage_files', array('id' => $courseId));
                $message = sprintf('PPT文档转换失败，请到课程<a href="%s" target="_blank">文件管理</a>中，重新转换。', $url);

                return $this->createJsonResponse(array(
                    'error' => array('code' => 'error', 'message' => $message)
                ));
            } else {
                return $this->createJsonResponse(array(
                    'error' => array('code' => 'processing', 'message' => 'PPT文档还在转换中，还不能查看，请稍等。')
                ));
            }
        }

        $factory = new CloudClientFactory();
        $client  = $factory->createClient();

        $result = $client->pptImages($file['metas2']['imagePrefix'], $file['metas2']['length'].'');

        return $this->createJsonResponse($result);
    }

    public function documentAction(Request $request, $courseId, $lessonId)
    {
        $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        if (empty($lesson)) {
            throw $this->createNotFoundException();
        }

        if (!$lesson['free']) {
            $this->getCourseService()->tryTakeCourse($courseId);
        }

        if ($lesson['type'] != 'document' || empty($lesson['mediaId'])) {
            throw $this->createNotFoundException();
        }

        $file = $this->getUploadFileService()->getFile($lesson['mediaId']);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if ($file['convertStatus'] != 'success') {
            if ($file['convertStatus'] == 'error') {
                $url     = $this->generateUrl('course_manage_files', array('id' => $courseId));
                $message = sprintf('文档转换失败，请到课程<a href="%s" target="_blank">文件管理</a>中，重新转换。', $url);

                return $this->createJsonResponse(array(
                    'error' => array('code' => 'error', 'message' => $message)
                ));
            } else {
                return $this->createJsonResponse(array(
                    'error' => array('code' => 'processing', 'message' => '文档还在转换中，还不能查看，请稍等。')
                ));
            }
        }

        $factory = new CloudClientFactory();
        $client  = $factory->createClient();

        $metas2           = $file['metas2'];
        $url              = $client->generateFileUrl($client->getBucket(), $metas2['pdf']['key'], 3600);
        $result['pdfUri'] = $url['url'];
        $url              = $client->generateFileUrl($client->getBucket(), $metas2['swf']['key'], 3600);
        $result['swfUri'] = $url['url'];

        return $this->createJsonResponse($result);
    }

    public function flashAction(Request $request, $courseId, $lessonId)
    {
        $lesson = $this->getCourseService()->getCourseLesson($courseId, $lessonId);

        if (empty($lesson)) {
            throw $this->createNotFoundException();
        }

        if (!$lesson['free']) {
            $this->getCourseService()->tryTakeCourse($courseId);
        }

        if ($lesson['type'] != 'flash' || empty($lesson['mediaId'])) {
            throw $this->createNotFoundException();
        }

        $file = $this->getUploadFileService()->getFile($lesson['mediaId']);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        $factory = new CloudClientFactory();
        $client  = $factory->createClient();

        if ($file["hashId"]) {
            $url                = $client->generateFileUrl($client->getBucket(), $file["hashId"], 3600);
            $result['mediaUri'] = $url['url'];
        } else {
            $result['mediaUri'] = '';
        }

        return $this->createJsonResponse($result);
    }

    public function fileAction(Request $request, $fileId, $isDownload = false)
    {
        $file = $this->getUploadFileService()->getFile($fileId);

        if (empty($file)) {
            throw $this->createNotFoundException();
        }

        if ($file['storage'] == 'cloud') {
            if ($isDownload) {
                $key = $file['hashId'];
            } else {
                if (!empty($file['metas']) && !empty($file['metas']['hd']['key'])) {
                    $key = $file['metas']['hd']['key'];
                } else {
                    $key = $file['hashId'];
                }
            }

            if (empty($key)) {
                throw $this->createNotFoundException();
            }

            $factory = new CloudClientFactory();
            $client  = $factory->createClient();

            if ($isDownload) {
                $client->download($client->getBucket(), $key, 3600, $file['filename']);
            } else {
                $client->download($client->getBucket(), $key);
            }
        }

        return $this->createLocalMediaResponse($request, $file, $isDownload);
    }

    public function learnStatusAction(Request $request, $courseId, $lessonId)
    {
        $user   = $this->getCurrentUser();
        $status = $this->getCourseService()->getUserLearnLessonStatus($user['id'], $courseId, $lessonId);

        return $this->createJsonResponse(array('status' => $status ?: 'unstart'));
    }

    public function learnStartAction(Request $request, $courseId, $lessonId)
    {
        $result = $this->getCourseService()->startLearnLesson($courseId, $lessonId);

        return $this->createJsonResponse($result);
    }

    public function learnFinishAction(Request $request, $courseId, $lessonId)
    {
        $user = $this->getCurrentUser();

        if ($this->isPluginInstalled('ClassroomPlan')) {
            return $this->forward('ClassroomPlanBundle:ClassroomPlan:lessonFinishModal', array(
                'lessonId' => $lessonId
            ));
        }

        $this->getCourseService()->finishLearnLesson($courseId, $lessonId);

        $member = $this->getCourseService()->getCourseMember($courseId, $user['id']);

        $response = array(
            'learnedNum' => empty($member['learnedNum']) ? 0 : $member['learnedNum'],
            'isLearned'  => empty($member['isLearned']) ? 0 : $member['isLearned']
        );

        return $this->createJsonResponse($response);
    }

    public function learnCancelAction(Request $request, $courseId, $lessonId)
    {
        $this->getCourseService()->cancelLearnLesson($courseId, $lessonId);

        return $this->createJsonResponse(true);
    }

    public function watchNumAction(Request $request, $courseId, $lessonId)
    {
        $user   = $this->getCurrentUser();
        $result = $this->getCourseService()->waveWatchNum($user['id'], $lessonId, 1);

        return $this->createJsonResponse($result);
    }

    public function qrcodeAction(Request $request, $courseId, $lessonId)
    {
        $user = $this->getUserService()->getCurrentUser();
        $host = $request->getSchemeAndHttpHost();

        if ($user->isLogin()) {
            $appUrl = "{$host}/mapi_v2/mobile/main#/lesson/{$courseId}/{$lessonId}";
        } else {
            $appUrl = "{$host}/mapi_v2/mobile/main#/course/{$id}";
        }

        $token = $this->getTokenService()->makeToken('qrcode', array(
            'userId'   => $user['id'],
            'data'     => array(
                'url'    => $this->generateUrl('course_learn', array('id' => $courseId), true)."#lesson/".$lessonId,
                'appUrl' => $appUrl
            ),
            'times'    => 0,
            'duration' => 3600
        ));
        $url = $this->generateUrl('common_parse_qrcode', array('token' => $token['token']), true);

        $response = array(
            'img' => $this->generateUrl('common_qrcode', array('text' => $url), true)
        );
        return $this->createJsonResponse($response);
    }

    protected function createLocalMediaResponse(Request $request, $file, $isDownload = false)
    {
        $response = BinaryFileResponse::create($file['fullpath'], 200, array(), false);
        $response->trustXSendfileTypeHeader();

        if ($isDownload) {
            $file['filename'] = urlencode($file['filename']);

            if (preg_match("/MSIE/i", $request->headers->get('User-Agent'))) {
                $response->headers->set('Content-Disposition', 'attachment; filename="'.$file['filename'].'"');
            } else {
                $response->headers->set('Content-Disposition', "attachment; filename*=UTF-8''".$file['filename']);
            }
        }

        $mimeType = FileToolkit::getMimeTypeByExtension($file['ext']);

        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }

    protected function isMobile()
    {
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备

        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }

        //如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息

        if (isset($_SERVER['HTTP_VIA'])) {
            //找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }

        //判断手机发送的客户端标志,兼容性有待提高

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $clientkeywords = array('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp',
                'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu',
                'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi',
                'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字

            if (preg_match("/(".implode('|', $clientkeywords).")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }

        //协议法，因为有可能不准确，放到最后判断

        if (isset($_SERVER['HTTP_ACCEPT'])) {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备

            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }

        return false;
    }

    public function listAction(Request $request, $courseId, $member, $previewUrl, $mode = 'full')
    {
        $user          = $this->getCurrentUser();
        $learnStatuses = $this->getCourseService()->getUserLearnLessonStatuses($user['id'], $courseId);
        $items         = $this->getCourseService()->getCourseItems($courseId);
        $course        = $this->getCourseService()->getCourse($courseId);

        $homeworkPlugin     = $this->getAppService()->findInstallApp('Homework');
        $homeworkLessonIds  = array();
        $exercisesLessonIds = array();

        if ($homeworkPlugin) {
            $lessonIds          = ArrayToolkit::column($items, 'id');
            $homeworks          = $this->getHomeworkService()->findHomeworksByCourseIdAndLessonIds($course['id'], $lessonIds);
            $exercises          = $this->getExerciseService()->findExercisesByLessonIds($lessonIds);
            $homeworkLessonIds  = ArrayToolkit::column($homeworks, 'lessonId');
            $exercisesLessonIds = ArrayToolkit::column($exercises, 'lessonId');
        }

        if ($this->setting('magic.lesson_watch_limit')) {
            $lessonLearns = $this->getCourseService()->findUserLearnedLessons($user['id'], $courseId);
        } else {
            $lessonLearns = array();
        }

        $testpaperIds = array();
        array_walk($items, function ($item, $key) use (&$testpaperIds) {
            if ($item['type'] == 'testpaper') {
                array_push($testpaperIds, $item['mediaId']);
            }
        }

        );

        $testpapers = $this->getTestpaperService()->findTestpapersByIds($testpaperIds);

        return $this->Render('TopxiaWebBundle:CourseLesson/Widget:list.html.twig', array(
            'items'              => $items,
            'course'             => $course,
            'member'             => $member,
            'previewUrl'         => $previewUrl,
            'learnStatuses'      => $learnStatuses,
            'lessonLearns'       => $lessonLearns,
            'currentTime'        => time(),
            'homeworkLessonIds'  => $homeworkLessonIds,
            'exercisesLessonIds' => $exercisesLessonIds,
            'mode'               => $mode,
            'testpapers'         => $testpapers

        ));
    }

    public function doTestpaperAction(Request $request, $lessonId, $testId)
    {
        $status  = 'do';
        $message = $this->checkTestPaper($lessonId, $testId, $status);

        if (!empty($message)) {
            return $this->createMessageResponse('info', $message);
        }

        return $this->forward('TopxiaWebBundle:Testpaper:doTestpaper', array('targetType' => 'lesson', 'targetId' => $lessonId, 'testId' => $testId));
    }

    public function reDoTestpaperAction(Request $request, $lessonId, $testId)
    {
        $status  = 'redo';
        $message = $this->checkTestPaper($lessonId, $testId, $status);

        if (!empty($message)) {
            return $this->createMessageResponse('info', $message);
        }

        return $this->forward('TopxiaWebBundle:Testpaper:reDoTestpaper', array('targetType' => 'lesson', 'targetId' => $lessonId, 'testId' => $testId));
    }

    private function checkTestPaper($lessonId, $testId, $status)
    {
        $user = $this->getCurrentUser();

        $message   = '';
        $testpaper = $this->getTestpaperService()->getTestpaper($testId);

        $targets = $this->get('topxia.target_helper')->getTargets(array($testpaper['target']));

        if ($targets[$testpaper['target']]['type'] != 'course') {
            throw $this->createAccessDeniedException('试卷只能属于课程');
        }

        $courseId = $targets[$testpaper['target']]['id'];

        $course = $this->getCourseService()->getCourse($courseId);

        if (empty($course)) {
            return $message = '试卷所属课程不存在！';
        }

        if (!$this->getCourseService()->canTakeCourse($course)) {
            return $message = '不是试卷所属课程老师或学生';
        }

        $lesson = $this->getCourseService()->getLesson($lessonId);

        if ($lesson['testMode'] == 'realTime') {
            $testpaper = $this->getTestpaperService()->getTestpaper($testId);

            $testEndTime = $lesson['testStartTime'] + $testpaper['limitedTime'] * 60;

            if ($testEndTime < time()) {
                return $message = '实时考试已经结束!';
            }

            if ($status == 'do') {
                $testpaperResult = $this->getTestpaperService()->findTestpaperResultsByTestIdAndStatusAndUserId($testpaper['id'], $user['id'], array('finished'));

                if ($testpaperResult) {
                    return $message = '您已经提交试卷，不能继续考试!';
                }
            } else {
                return $message = '实时考试，不能再考一次!';
            }
        }
    }

    protected function agentInWhiteList($userAgent)
    {
        $whiteList = array("iPhone", "iPad", "Mac", "Android");

        foreach ($whiteList as $value) {
            if (strpos(strtolower($userAgent), strtolower($value)) > -1) {
                return true;
            }
        }

        return false;
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getTokenService()
    {
        return $this->getServiceKernel()->createService('User.TokenService');
    }

    protected function getUploadFileService()
    {
        return $this->getServiceKernel()->createService('File.UploadFileService');
    }

    protected function getTestpaperService()
    {
        return $this->getServiceKernel()->createService('Testpaper.TestpaperService');
    }

    //Homework plugins(contains Exercise)
    protected function getHomeworkService()
    {
        return $this->getServiceKernel()->createService('Homework:Homework.HomeworkService');
    }

    protected function getExerciseService()
    {
        return $this->getServiceKernel()->createService('Homework:Homework.ExerciseService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getCourseMemberService()
    {
        return $this->getServiceKernel()->createService('Course.CourseMemberService');
    }

    protected function getVipService()
    {
        return $this->getServiceKernel()->createService('Vip:Vip.VipService');
    }

    protected function getLevelService()
    {
        return $this->getServiceKernel()->createService('Vip:Vip.LevelService');
    }

    protected function getClassroomService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomService');
    }
}
