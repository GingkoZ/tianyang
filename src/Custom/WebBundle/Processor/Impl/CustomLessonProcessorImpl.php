<?php
namespace Custom\WebBundle\Processor\Impl;

use Topxia\MobileBundleV2\Processor\BaseProcessor;
use Topxia\MobileBundleV2\Processor\LessonProcessor;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Service\Util\CloudClientFactory;
use Topxia\Service\Common\ServiceKernel;

class CustomLessonProcessorImpl extends BaseProcessor implements LessonProcessor
{
    public function getVideoMediaUrl()
    {
        $courseId = $this->request->get("courseId");
        $lessonId = $this->request->get("lessonId");
        if (empty($courseId)) {
            return $this->createErrorResponse('not_courseId', '课程信息不存在！');
        }
        $user   = $this->controller->getUserByToken($this->request);
        $lesson = $this->controller->getCourseService()->getCourseLesson($courseId, $lessonId);
        if (empty($lesson)) {
            return $this->createErrorResponse('not_courseId', '课时信息不存在！');
        }
        if ($lesson['free'] == 1) {
            if ($user->isLogin()) {
                if ($this->controller->getCourseService()->isCourseStudent($courseId, $user['id'])) {
                    $this->controller->getCourseService()->startLearnLesson($courseId, $lessonId);
                }
            }
            $lesson = $this->coverLesson($lesson);
            if ($lesson['mediaSource'] == 'self') {
                $response = $this->curlRequest("GET", $lesson['mediaUri'], null);
                return new Response($response);
            }
            return $lesson['mediaUri'];
        }
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        $this->controller->getCourseService()->startLearnLesson($courseId, $lessonId);
        $member = $this->controller->getCourseService()->getCourseMember($courseId, $user['id']);
        $member = $this->previewAsMember($member, $courseId, $user);
        if ($member && in_array($member['role'], array(
            "teacher",
            "student"
        ))) {
            $lesson = $this->coverLesson($lesson);
            if ($lesson['mediaSource'] == 'self') {
                $response = $this->curlRequest("GET", $lesson['mediaUri'], null);
                return new Response($response);
            }
            return $lesson['mediaUri'];
        }
        if ($lesson['mediaSource'] == 'self') {
            $response = $this->curlRequest("GET", $lesson['mediaUri'], null);
            return new Response($response);
        }
        return $lesson['mediaUri'];
    }
    public function getLessonMaterial()
    {
        $lessonId        = $this->getParam("lessonId");
        $start           = (int) $this->getParam("start", 0);
        $limit           = (int) $this->getParam("limit", 10);
        $lessonMaterials = $this->controller->getMaterialService()->findLessonMaterials($lessonId, $start, 1000);
        $files           = $this->controller->getUploadFileService()->findFilesByIds(ArrayToolkit::column($lessonMaterials, 'fileId'));
        return array(
            "start" => $start,
            "limit" => $limit,
            "total" => 1000,
            "data" => $this->filterMaterial($lessonMaterials, $files)
        );
    }
    private function filterMaterial($lessonMaterials, $files)
    {
        $newFiles = array();
        foreach ($files as $key => $file) {
            $newFiles[$file['id']] = $file;
        }
        return array_map(function($lessonMaterial) use ($newFiles)
        {
            $lessonMaterial['createdTime'] = date('c', $lessonMaterial['createdTime']);
            $field                         = $lessonMaterial['fileId'];
            $lessonMaterial['fileMime']    = $newFiles[$field]['type'];
            return $lessonMaterial;
        }, $lessonMaterials);
    }
    public function downMaterial()
    {
        $courseId   = $this->request->get("courseId");
        $materialId = $this->request->get("materialId");
        $user       = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }
        list($course, $member) = $this->controller->getCourseService()->tryTakeCourse($courseId);
        if ($member && !$this->controller->getCourseService()->isMemberNonExpired($course, $member)) {
            return "course_materials";
        }
        if ($member && $member['levelId'] > 0) {
            if ($this->controller->getVipService()->checkUserInMemberLevel($member['userId'], $course['vipLevelId']) != 'ok') {
                return "course_show";
            }
        }
        $material = $this->controller->getMaterialService()->getMaterial($courseId, $materialId);
        if (empty($material)) {
            throw "createNotFoundException";
        }
        $file = $this->controller->getUploadFileService()->getFile($material['fileId']);
        if (empty($file)) {
            throw "createNotFoundException";
        }
        if ($file['storage'] == 'cloud') {
            $factory = new CloudClientFactory();
            $client  = $factory->createClient();
            $client->download($client->getBucket(), $file['hashId'], 3600, $file['filename']);
        } else {
            return $this->createPrivateFileDownloadResponse($request, $file);
        }
    }
    private function createPrivateFileDownloadResponse(Request $request, $file)
    {
        $response = BinaryFileResponse::create($file['fullpath'], 200, array(), false);
        $response->trustXSendfileTypeHeader();
        $file['filename'] = urlencode($file['filename']);
        if (preg_match("/MSIE/i", $request->headers->get('User-Agent'))) {
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $file['filename'] . '"');
        } else {
            $response->headers->set('Content-Disposition', "attachment; filename*=UTF-8''" . $file['filename']);
        }
        $mimeType = FileToolkit::getMimeTypeByExtension($file['ext']);
        if ($mimeType) {
            $response->headers->set('Content-Type', $mimeType);
        }
        return $response;
    }
    public function learnLesson()
    {
        $courseId = $this->getParam("courseId");
        $lessonId = $this->getParam("lessonId");
        $user     = $this->controller->getuserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }
        $this->controller->getCourseService()->finishLearnLesson($courseId, $lessonId);
        return "finished";
    }
    public function getLessonStatus()
    {
        $user     = $this->controller->getuserByToken($this->request);
        $courseId = $this->getParam("courseId");
        $lessonId = $this->getParam("lessonId");
        if ($user->isLogin()) {
            $learnStatus     = $this->controller->getCourseService()->getUserLearnLessonStatus($user['id'], $courseId, $lessonId);
            $lessonMaterials = $this->controller->getMaterialService()->findLessonMaterials($lessonId, 0, 1);
            return array(
                "learnStatus" => $learnStatus,
                "hasMaterial" => empty($lessonMaterials) ? false : true
            );
        }
        return array();
    }
    public function getLearnStatus()
    {
        $user     = $this->controller->getuserByToken($this->request);
        $courseId = $this->getParam("courseId");
        if ($user->isLogin()) {
            $learnStatuses = $this->controller->getCourseService()->getUserLearnLessonStatuses($user['id'], $courseId);
        } else {
            $learnStatuses = array();
        }
        return $learnStatuses;
    }
    public function unLearnLesson()
    {
        $courseId = $this->getParam("courseId");
        $lessonId = $this->getParam("lessonId");
        $user     = $this->controller->getuserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', "您尚未登录！");
        }
        $this->controller->getCourseService()->cancelLearnLesson($courseId, $lessonId);
        return "learning";
    }
    public function getCourseLessons()
    {
        $token    = $this->controller->getUserToken($this->request);
        $user     = $this->controller->getUser();
        $courseId = $this->getParam("courseId");
        $lessons  = $this->controller->getCourseService()->getCourseItems($courseId);
        $lessons  = $this->controller->filterItems($lessons);
        if ($user->isLogin()) {
            $learnStatuses = $this->controller->getCourseService()->getUserLearnLessonStatuses($user['id'], $courseId);
        } else {
            $learnStatuses = array();
        }
        $files   = $this->getUploadFiles($courseId);
        $lessons = $this->filterLessons($lessons, $files);
        return array(
            "lessons" => array_values($lessons),
            "learnStatuses" => $learnStatuses
        );
    }
    private function getUploadFiles($courseId)
    {
        $conditions  = array(
            'targetType' => "courselesson",
            'targetId' => $courseId
        );
        $files       = $this->getUploadFileService()->searchFiles($conditions, 'latestCreated', 0, 100);
        $uploadFiles = array();
        foreach ($files as $key => $file) {
            $files[$key]['metas2']        = json_decode($file['metas2'], true) ?: array();
            $files[$key]['convertParams'] = json_decode($file['convertParams']) ?: array();
            unset($file["metas"]);
            unset($file["metas2"]);
            unset($file["hashId"]);
            unset($file["etag"]);
            $uploadFiles[$file["id"]] = $file;
        }
        return $uploadFiles;
    }
    public function getLesson()
    {
        $courseId = $this->getParam("courseId");
        $lessonId = $this->getParam("lessonId");
        if (empty($courseId)) {
            return $this->createErrorResponse('not_courseId', '课程信息不存在！');
        }
        $user   = $this->controller->getUserByToken($this->request);
        $lesson = $this->controller->getCourseService()->getCourseLesson($courseId, $lessonId);
        if (empty($lesson)) {
            return $this->createErrorResponse('not_courseId', '课时信息不存在！');
        }
        if ($lesson['free'] == 1) {
            if ($user->isLogin()) {
                if ($this->controller->getCourseService()->isCourseStudent($courseId, $user['id'])) {
                    $this->controller->getCourseService()->startLearnLesson($courseId, $lessonId);
                }
            }
            return $this->coverLesson($lesson);
        }
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        if ($this->controller->getCourseService()->isCourseStudent($courseId, $user['id'])) {
            $this->controller->getCourseService()->startLearnLesson($courseId, $lessonId);
        }
        $member = $this->controller->getCourseService()->getCourseMember($courseId, $user['id']);
        $member = $this->previewAsMember($member, $courseId, $user);
        if ($member && in_array($member['role'], array(
            "teacher",
            "student"
        ))) {
            return $this->coverLesson($lesson);
        }
        return $this->createErrorResponse('not_student', '你不是该课程学员，请加入学习!');
    }
    private function getTestpaperLesson($lesson)
    {
        $user      = $this->controller->getUser();
        $id        = $lesson['mediaId'];
        $testpaper = $this->getTestpaperService()->getTestpaper($id);
        if (empty($testpaper)) {
            return $this->createErrorResponse('error', '试卷不存在!');
        }
        $testResult        = $this->getTestpaperService()->findTestpaperResultByTestpaperIdAndUserIdAndActive($id, $user['id']);
        $lesson['content'] = array(
            'status' => empty($testResult) ? 'nodo' : $testResult['status'],
            'resultId' => empty($testResult) ? 0 : $testResult['id']
        );
        return $lesson;
    }
    public function getTestpaperInfo()
    {
        $id   = $this->getParam("testId");
        $user = $this->controller->getUserByToken($this->request);
        if (!$user->isLogin()) {
            return $this->createErrorResponse('not_login', '您尚未登录，不能查看该课时');
        }
        $testpaper = $this->getTestpaperService()->getTestpaper($id);
        if (empty($testpaper)) {
            return $this->createErrorResponse('error', '试卷已删除，请联系管理员。!');
        }
        $items = $this->getTestpaperService()->getTestpaperItems($id);
        return array(
            'testpaper' => $testpaper,
            'items' => $this->filterTestpaperItems($items)
        );
    }
    private function filterTestpaperItems($items)
    {
        $itemArray = array();
        foreach ($items as $key => $item) {
            $type = $item['questionType'];
            if (isset($itemArray[$type])) {
                $count            = $itemArray[$type];
                $itemArray[$type] = $count + 1;
            } else {
                $itemArray[$type] = 1;
            }
        }
        return $itemArray;
    }
    private function coverLesson($lesson)
    {
        $lesson['createdTime'] = date('c', $lesson['createdTime']);
        switch ($lesson['type']) {
            case 'ppt':
                return $this->getPPTLesson($lesson);
            case 'audio':
                return $this->getVideoLesson($lesson);
            case 'video':
                $lesson = $this->getVideoLesson($lesson);
                if (!empty($lesson['mediaUri'])) {
                    $lesson['mediaUri'] = $this->filterSvmpUri($lesson['mediaUri']);
                }
                if (isset($lesson["mediaSource"]) && $lesson["mediaSource"] == "smvp") {
                    $lesson["mediaSource"] = "self";
                }
                return $lesson;
            case 'testpaper':
                return $this->getTestpaperLesson($lesson);
            default:
                $lesson['content'] = $this->wrapContent($lesson['content']);
        }
        return $lesson;
    }
    private function filterSvmpUri($uri)
    {
        parse_str($uri);
        $smvpToken  = $this->getKernel()->getParameter('smvpToken');
        $client     = new SMVP($smvpToken);
        $resultStr  = $client->entries_json(array(
            "entry_id" => $videoId,
            "types" => array(
                "mp4"
            )
        ));
        $manage = (array) json_decode($resultStr);
        $entries = (array)$manage["entries"];
        $entry = (array)$entries[0];
        $renditions = $entry['renditions'];
        if(empty($renditions)){
            return "";
        }

        $autoVideo = array();
        foreach ($renditions as $key => $s) {
          $sArray = (array)$s;
          if($sArray['name'] == 'AUTO'){
            $autoVideo = $sArray ;
            $urls = (array)$autoVideo["urls"];
            $m3u8 = (array)$urls["m3u8"];
            return $m3u8["url"];
          }
        }

        $renditions = $entry['renditions'];
        $autoVideo = (array)$renditions[count($renditions) - 1];
        $urls = (array)$autoVideo["urls"];
        $m3u8 = (array)$urls["m3u8"];
        return $m3u8["url"];
    }
    protected function getKernel()
    {
        return ServiceKernel::instance();
    }
    private function getVideoLesson($lesson)
    {
        $token       = $this->controller->getUserToken($this->request);
        $mediaId     = $lesson['mediaId'];
        $mediaSource = $lesson['mediaSource'];
        $mediaUri    = $lesson['mediaUri'];
        if ($lesson['length'] > 0) {
            $lesson['length'] = $this->getContainer()->get('topxia.twig.web_extension')->durationFilter($lesson['length']);
        } else {
            $lesson['length'] = '';
        }
        if ($mediaSource == 'self') {
            $file = $this->controller->getUploadFileService()->getFile($lesson['mediaId']);
            if (!empty($file)) {
                if ($file['storage'] == 'cloud') {
                    $factory                      = new CloudClientFactory();
                    $client                       = $factory->createClient();
                    $lesson['mediaConvertStatus'] = $file['convertStatus'];
                    if (!empty($file['metas2']) && !empty($file['metas2']['sd']['key'])) {
                        if (isset($file['convertParams']['convertor']) && ($file['convertParams']['convertor'] == 'HLSEncryptedVideo')) {
                            $headLeaderInfo = $this->getHeadLeaderInfo();
                            if ($headLeaderInfo) {
                                $token             = $this->getTokenService()->makeToken('hls.playlist', array(
                                    'data' => $headLeaderInfo['id'],
                                    'times' => 2,
                                    'duration' => 3600
                                ));
                                $headUrl           = array(
                                    'url' => $this->controller->generateUrl('hls_playlist', array(
                                        'id' => $headLeaderInfo['id'],
                                        'token' => $token['token'],
                                        'line' => $this->request->get('line'),
                                        'hideBeginning' => 1
                                    ), true)
                                );
                                $lesson['headUrl'] = $headUrl['url'];
                            }
                            $token = $this->getTokenService()->makeToken('hls.playlist', array(
                                'data' => $file['id'],
                                'times' => 2,
                                'duration' => 3600
                            ));
                            $url   = array(
                                'url' => $this->controller->generateUrl('hls_playlist', array(
                                    'id' => $file['id'],
                                    'token' => $token['token'],
                                    'line' => $this->request->get('line'),
                                    'hideBeginning' => 1
                                ), true)
                            );
                        } else {
                            $url = $client->generateHLSQualitiyListUrl($file['metas2'], 3600);
                        }
                        $lesson['mediaUri'] = (isset($url) and is_array($url) and !empty($url['url'])) ? $url['url'] : '';
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
                            $url                = $client->generateFileUrl($client->getBucket(), $key, 3600);
                            $lesson['mediaUri'] = $url['url'];
                        } else {
                            $lesson['mediaUri'] = '';
                        }
                    }
                } else {
                    $lesson['mediaUri'] = $this->controller->generateUrl('mapi_course_lesson_media', array(
                        'courseId' => $lesson['courseId'],
                        'lessonId' => $lesson['id'],
                        'token' => empty($token) ? '' : $token['token']
                    ), true);
                }
            } else {
                $lesson['mediaUri'] = '';
            }
        } else if ($mediaSource == 'youku') {
            $matched = preg_match('/\/sid\/(.*?)\/v\.swf/s', $lesson['mediaUri'], $matches);
            if ($matched) {
                $lesson['mediaUri'] = "http://player.youku.com/embed/{$matches[1]}";
            } else {
                $lesson['mediaUri'] = '';
            }
        } else if ($mediaSource == 'tudou') {
            $matched = preg_match('/\/v\/(.*?)\/v\.swf/s', $lesson['mediaUri'], $matches);
            if ($matched) {
                $lesson['mediaUri'] = "http://www.tudou.com/programs/view/html5embed.action?code={$matches[1]}";
            } else {
                $lesson['mediaUri'] = '';
            }
        } else {
            $lesson['mediaUri'] = $mediaUri;
        }
        return $lesson;
    }
    private function getPPTLesson($lesson)
    {
        $file = $this->controller->getUploadFileService()->getFile($lesson['mediaId']);
        if (empty($file)) {
            return $this->createErrorResponse('not_ppt', '获取ppt课时失败!');
        }
        if ($file['convertStatus'] != 'success') {
            if ($file['convertStatus'] == 'error') {
                $url = $this->controller->generateUrl('course_manage_files', array(
                    'id' => $courseId
                ));
                return $this->createErrorResponse('not_ppt', 'PPT文档转换失败，请到课程文件管理中，重新转换!');
            } else {
                return $this->createErrorResponse('not_ppt', 'PPT文档还在转换中，还不能查看，请稍等。!');
            }
        }
        $factory           = new CloudClientFactory();
        $client            = $factory->createClient();
        $ppt               = $client->pptImages($file['metas2']['imagePrefix'], $file['metas2']['length'] . '');
        $lesson['content'] = $ppt;
        return $lesson;
    }
    public function test()
    {
        $user   = $this->controller->getUserByToken($this->request);
        $lesson = $this->controller->getCourseService()->getCourseLesson(11, 185);
        $file   = $this->getUploadFileService()->getFile($lesson['mediaId']);
        if (empty($file)) {
            return $this->createErrorResponse('not_document', '文档还在转换中，还不能查看，请稍等。!');
        }
        if ($file['convertStatus'] != 'success') {
            if ($file['convertStatus'] == 'error') {
                return $this->createErrorResponse('not_document', '文档转换失败，请联系管理员!');
            } else {
                return $this->createErrorResponse('not_document', '文档还在转换中，还不能查看，请稍等!');
            }
        }
        $factory           = new CloudClientFactory();
        $client            = $factory->createClient();
        $metas2            = $file['metas2'];
        $url               = $client->generateFileUrl($client->getBucket(), $metas2['pdf']['key'], 3600);
        $pdfUri            = $url['url'];
        $url               = $client->generateFileUrl($client->getBucket(), $metas2['swf']['key'], 3600);
        $swfUri            = $url['url'];
        $content           = $lesson['content'];
        $content           = $this->controller->convertAbsoluteUrl($this->request, $content);
        $render            = $this->controller->render('TopxiaMobileBundleV2:Course:document.html.twig', array(
            'pdfUri' => $pdfUri,
            'swfUri' => $swfUri,
            'title' => $lesson['title']
        ));
        $lesson['content'] = $render->getContent();
        return $render;
    }
    private function getDocumentLesson($lesson)
    {
        $file = $this->getUploadFileService()->getFile($lesson['mediaId']);
        if (empty($file)) {
            return $this->createErrorResponse('not_document', '文档还在转换中，还不能查看，请稍等。!');
        }
        if ($file['convertStatus'] != 'success') {
            if ($file['convertStatus'] == 'error') {
                return $this->createErrorResponse('not_document', '文档转换失败，请联系管理员!');
            } else {
                return $this->createErrorResponse('not_document', '文档还在转换中，还不能查看，请稍等!');
            }
        }
        $factory           = new CloudClientFactory();
        $client            = $factory->createClient();
        $metas2            = $file['metas2'];
        $url               = $client->generateFileUrl($client->getBucket(), $metas2['pdf']['key'], 3600);
        $pdfUri            = $url['url'];
        $url               = $client->generateFileUrl($client->getBucket(), $metas2['swf']['key'], 3600);
        $swfUri            = $url['url'];
        $content           = $lesson['content'];
        $content           = $this->controller->convertAbsoluteUrl($this->request, $content);
        $render            = $this->controller->render('TopxiaMobileBundleV2:Course:document.html.twig', array(
            'pdfUri' => $pdfUri,
            'swfUri' => $swfUri,
            'title' => $lesson['title']
        ));
        $lesson['content'] = $render->getContent();
        return $lesson;
    }
    private function getHeadLeaderInfo()
    {
        $storage = $this->controller->getSettingService()->get("storage");
        if (!empty($storage) && array_key_exists("video_header", $storage) && $storage["video_header"]) {
            $file                  = $this->controller->getUploadFileService()->getFileByTargetType('headLeader');
            $file["convertParams"] = json_decode($file["convertParams"], true);
            $file["metas2"]        = json_decode($file["metas2"], true);
            return $file;
        }
        return false;
    }
    private function wrapContent($content)
    {
        $content = $this->controller->convertAbsoluteUrl($this->request, $content);
        $render  = $this->controller->render('TopxiaMobileBundleV2:Content:index.html.twig', array(
            'content' => $content
        ));
        return $render->getContent();
    }
    private function filterLessons($lessons, $files)
    {
        return array_map(function($lesson) use ($files)
        {
            $lesson['content'] = "";
            if (isset($lesson["mediaId"])) {
                $lesson["uploadFile"] = isset($files[$lesson["mediaId"]]) ? $files[$lesson["mediaId"]] : null;
            }
            return $lesson;
        }, $lessons);
    }

}


class SMVP {

    private $api_url = 'http://api.alpha.smvp.cn/';
    private $token = NULL;

    function __construct($access_token, $url=NULL) {
        if ( $url ) $this->api_url = $url;
            $this->token = $access_token;
    }
    
    function __destruct() {}

    function post($url, $params, $headers=array()) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            curl_close($ch);
            return $result;
        } else {
            echo 'curl should be installed.';
        }
    }
   
    public function setToken($access_token) {
        $this->token = $access_token;
    }

    public function getToken() {
        return $this->token;
    }
    
    public function setURL($url) {
        $this->api_url = $url;
    }

    public function getURL() {
        return $this->api_url;
    }

    public function __call($method, $args) {
        $post_data = array();
        if ( count($args) > 0 ) {
            $arguments = $args[0];
            foreach($arguments as $arg=>$argvalue) {
                $v = $argvalue;
                if ($arg != 'file') $v = json_encode($argvalue);
                $post_data[$arg] = $v;
            }
        }
        $headers = array('Authorization:SMVP_TOKEN|'.$this->token);
        $request_url = $this->api_url;
        if (substr($request_url, -1) != '/') {
            $request_url = $request_url.'/';
        }
        $request_url = $request_url.(str_replace('_', '/', $method));
        return $this->post($request_url, $post_data, $headers);
    }
}
