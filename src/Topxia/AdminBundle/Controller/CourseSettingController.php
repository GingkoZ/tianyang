<?php

namespace Topxia\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Service\Util\EdusohoLiveClient;

class CourseSettingController extends BaseController
{
    public function courseSettingAction(Request $request)
    {
        $courseSetting = $this->getSettingService()->get('course', array());
        $liveCourseSetting = $this->getSettingService()->get('live-course', array());
        $defaultSettings = $this->getSettingService()->get('default', array());
        $userDefaultSetting = $this->getSettingService()->get('user_default', array());
        $courseDefaultSetting = $this->getSettingService()->get('course_default', array());
        $path = $this->container->getParameter('kernel.root_dir') . '/../web/assets/img/default/';
        $courseDefaultSet = $this->getCourseDefaultSet();
        $defaultSetting = array_merge($courseDefaultSet, $courseDefaultSetting);

        $default = array(
            'welcome_message_enabled' => '0',
            'welcome_message_body' => '{{nickname}},欢迎加入课程{{course}}',
            'buy_fill_userinfo' => '0',
            'teacher_modify_price' => '1',
            'teacher_search_order' => '0',
            'teacher_manage_student' => '0',
            'teacher_export_student' => '0',
            'student_download_media' => '0',
            'explore_default_orderBy' => 'latest',
            'free_course_nologin_view' => '1',
            'relatedCourses' => '0',
            'coursesPrice' => '0',
            'allowAnonymousPreview' => '1',
            'userinfoFields' => array(),
            "userinfoFieldNameArray" => array(),
            "copy_enabled" => '0',
            "testpaperCopy_enabled" => '0',
            "custom_chapter_enabled" => '1',
        );

        $this->getSettingService()->set('course', $courseSetting);
        $this->getSettingService()->set('live-course', $liveCourseSetting);
        $courseSetting = array_merge($default, $courseSetting);

        if ($request->getMethod() == 'POST') {

            $defaultSetting = $request->request->all();

            if (isset($defaultSetting['chapter_name'])) {
                $defaultSetting['chapter_name'] = $defaultSetting['chapter_name'];
            } else {
                $defaultSetting['chapter_name'] = '章';
            }

            if (isset($defaultSetting['part_name'])) {
                $defaultSetting['part_name'] = $defaultSetting['part_name'];
            } else {
                $defaultSetting['part_name'] = '节';
            }

            $courseDefaultSetting = ArrayToolkit::parts($defaultSetting, array(
                'chapter_name',
                'part_name',
            ));
            $this->getSettingService()->set('course_default', $courseDefaultSetting);

            $default = $this->getSettingService()->get('default', array());
            $defaultSetting = array_merge($default, $userDefaultSetting, $courseDefaultSetting);
            $this->getSettingService()->set('default', $defaultSetting);

            $courseSetting = $request->request->all();

            if (!isset($courseSetting['userinfoFields'])) {
                $courseSetting['userinfoFields'] = array();
            }

            if (!isset($courseSetting['userinfoFieldNameArray'])) {
                $courseSetting['userinfoFieldNameArray'] = array();
            }

            $courseSetting = array_merge($courseSetting, $liveCourseSetting);

            $this->getSettingService()->set('live-course', $liveCourseSetting);
            $this->getSettingService()->set('course', $courseSetting);
            $this->getLogService()->info('system', 'update_settings', "更新课程设置", $courseSetting);
            $this->setFlashMessage('success', '课程设置已保存！');
        }

        $userFields = $this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();

        if ($courseSetting['userinfoFieldNameArray']) {
            foreach ($userFields as $key => $fieldValue) {
                if (!in_array($fieldValue['fieldName'], $courseSetting['userinfoFieldNameArray'])) {
                    $courseSetting['userinfoFieldNameArray'][] = $fieldValue['fieldName'];
                }
            }

        }
        return $this->render('TopxiaAdminBundle:System:course-setting.html.twig', array(
            'courseSetting' => $courseSetting,
            'userFields' => $userFields,
            'defaultSetting' => $defaultSetting,
            'hasOwnCopyright' => false,
        ));
    }

    public function courseAvatarAction(Request $request)
    {
        $defaultSetting = $this->getSettingService()->get('default', array());

        if ($request->getMethod() == 'POST') {

            $defaultSetting = $request->request->all();

            $courseDefaultSetting = ArrayToolkit::parts($defaultSetting, array(
                'defaultCoursePicture',
            ));

            $default = $this->getSettingService()->get('default', array());
            $defaultSetting = array_merge($default, $courseDefaultSetting);

            $this->getSettingService()->set('default', $defaultSetting);

            $this->getLogService()->info('system', 'update_settings', "更新课程默认图片设置", $defaultSetting);
            $this->setFlashMessage('success', '课程默认图片设置已保存！');
        }

        return $this->render('TopxiaAdminBundle:System:course-avatar.html.twig', array(
            'defaultSetting' => $defaultSetting,
            'hasOwnCopyright' => false,
        ));
    }

    public function liveCourseSettingAction(Request $request)
    {
        $courseSetting = $this->getSettingService()->get('course', array());
        $liveCourseSetting = $this->getSettingService()->get('live-course', array());
        $client = new EdusohoLiveClient();
        $capacity = $client->getCapacity();

        $default = array(
            'live_course_enabled' => '0',
        );

        $this->getSettingService()->set('course', $courseSetting);
        $this->getSettingService()->set('live-course', $liveCourseSetting);
        $setting = array_merge($default, $liveCourseSetting);

        if ($request->getMethod() == 'POST') {
            $liveCourseSetting = $request->request->all();
            $liveCourseSetting['live_student_capacity'] = empty($capacity['capacity']) ? 0 : $capacity['capacity'];
            $setting = array_merge($courseSetting, $liveCourseSetting);
            $this->getSettingService()->set('live-course', $liveCourseSetting);
            $this->getSettingService()->set('course', $setting);

            $hiddenMenus = $this->getSettingService()->get('menu_hiddens', array());
            if ($liveCourseSetting['live_course_enabled']) {
                unset($hiddenMenus['admin_live_course_add']);
                unset($hiddenMenus['admin_live_course']);
            } else {
                $hiddenMenus['admin_live_course_add'] = true;
                $hiddenMenus['admin_live_course'] = true;
            }
            $this->getSettingService()->set('menu_hiddens', $hiddenMenus);

            $this->getLogService()->info('system', 'update_settings', "更新课程设置", $setting);
            $this->setFlashMessage('success', '课程设置已保存！');
        }

        $setting['live_student_capacity'] = empty($capacity['capacity']) ? 0 : $capacity['capacity'];
        return $this->render('TopxiaAdminBundle:System:live-course-setting.html.twig', array(
            'courseSetting' => $setting,
            'capacity' => $capacity,
        ));
    }

    public function questionsSettingAction(Request $request)
    {
        $questionsSetting = $this->getSettingService()->get('questions', array());
        if (empty($questionsSetting)) {
            $default = array(
                'testpaper_answers_show_mode' => 'submitted',
            );
            $questionsSetting = $default;
        }

        if ($request->getMethod() == 'POST') {
            $questionsSetting = $request->request->all();
            $this->getSettingService()->set('questions', $questionsSetting);
            $this->getLogService()->info('system', 'questions_settings', "更新题库设置", $questionsSetting);
            $this->setFlashMessage('success', '题库设置已保存！');
        }

        return $this->render('TopxiaAdminBundle:System:questions-setting.html.twig');
    }

    protected function getCourseDefaultSet()
    {
        $default = array(
            'defaultCoursePicture' => 0,
            'defaultCoursePictureFileName' => 'coursePicture',
            'articleShareContent' => '我正在看{{articletitle}}，关注{{sitename}}，分享知识，成就未来。',
            'courseShareContent' => '我正在学习{{course}}，收获巨大哦，一起来学习吧！',
            'groupShareContent' => '我在{{groupname}}小组,发表了{{threadname}},很不错哦,一起来看看吧!',
            'classroomShareContent' => '我正在学习{{classroom}}，收获巨大哦，一起来学习吧！',
            'chapter_name' => '章',
            'part_name' => '节',
        );

        return $default;
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getUploadFileService()
    {
        return $this->getServiceKernel()->createService('File.UploadFileService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    protected function getAuthService()
    {
        return $this->getServiceKernel()->createService('User.AuthService');
    }

}
