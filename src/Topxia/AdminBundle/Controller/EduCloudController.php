<?php

namespace Topxia\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\Paginator;
use Topxia\Service\CloudPlatform\CloudAPIFactory;

class EduCloudController extends BaseController
{
    public function indexAction(Request $request)
    {
        try {
            $api = CloudAPIFactory::create('root');

            $account = $api->get('/accounts');

            if(!empty($account)) {
                $money = isset($account['cash']) ? $account['cash'] : '--';

                $loginToken = $this->getAppService()->getLoginToken();

                
                $result = $api->post("/sms/{$api->getAccessKey()}/applyResult");

                if (isset($result['apply']) && isset($result['apply']['status'])) {
                    $smsStatus['status'] = $result['apply']['status'];
                    $smsStatus['message'] = $result['apply']['message'];
                } else if (isset($result['error'])) {
                    $smsStatus['status'] = 'error';
                    $smsStatus['message'] = $result['error'];
                }
            }
        } catch (\RuntimeException $e) {
            return $this->render('TopxiaAdminBundle:EduCloud:api-error.html.twig', array());
        }

        return $this->render('TopxiaAdminBundle:EduCloud:edu-cloud.html.twig', array(
            'account' => $account,
            'token' => isset($loginToken) && isset($loginToken["token"]) ? $loginToken["token"] : '',
            'smsStatus' => isset($smsStatus) ? $smsStatus : null,
        ));
    }

    public function smsAction(Request $request)
    {
        if($this->getWebExtension()->isTrial()) {
            return $this->render('TopxiaAdminBundle:EduCloud:sms.html.twig', array());  
        }

        $settings = $this->getSettingService()->get('storage', array());
        if (empty($settings['cloud_access_key']) || empty($settings['cloud_secret_key'])) {
            $this->setFlashMessage('warning', '您还没有授权码，请先绑定。');
            return $this->redirect($this->generateUrl('admin_setting_cloud_key_update'));
        }
        
        try {
            $smsStatus = $this->handleSmsSetting($request);
            return $this->render('TopxiaAdminBundle:EduCloud:sms.html.twig', array(
                'smsStatus' => $smsStatus,
            ));
        } catch (\RuntimeException $e) {
            return $this->render('TopxiaAdminBundle:EduCloud:api-error.html.twig', array());
        }

    }

    public function applyForSmsAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $result = null;
            $dataUserPosted = $request->request->all();

            if (
                isset($dataUserPosted['name'])
                && ($this->calStrlen($dataUserPosted['name']) >= 2)
                && ($this->calStrlen($dataUserPosted['name']) <= 16)
            ) {
                $api = CloudAPIFactory::create('root');
                $result = $api->post("/sms/{$api->getAccessKey()}/apply", array('name' => $dataUserPosted['name']));
                if (isset($result['status']) && ($result['status'] == 'ok')) {
                    $this->setCloudSmsKey('sms_school_candidate_name', $dataUserPosted['name']);
                    $this->setCloudSmsKey('show_message', 'on');
                    return $this->createJsonResponse(array('ACK' => 'ok'));
                }
            }

            return $this->createJsonResponse(array(
                'ACK' => 'failed',
                'message' => $result['error'] . '|' . ($this->calStrlen($dataUserPosted['name'])),
            ));
        }
        return $this->render('TopxiaAdminBundle:EduCloud:apply-sms-form.html.twig', array());
    }

    protected function handleSmsSetting(Request $request)
    {
        list($smsStatus, $schoolNames) = $this->getSchoolName();

        if ($request->getMethod() == 'POST') {
            $dataUserPosted = $request->request->all();
            $defaultSetting = array(
                'sms_enabled' => '0',
                'sms_registration' => 'off',
                'sms_forget_password' => 'off',
                'sms_user_pay' => 'off',
                'sms_forget_pay_password' => 'off',
                'sms_bind' => 'off',
                'sms_classroom_publish' => 'off',
                'sms_course_publish' => 'off',
                'sms_normal_lesson_publish' => 'off',
                'sms_live_lesson_publish' => 'off',
                'sms_live_play_one_day' => 'off',
                'sms_live_play_one_hour' => 'off',
                'sms_homework_check' => 'off',
                'sms_testpaper_check' => 'off',
                'sms_order_pay_success' => 'off',
                'sms_course_buy_notify' => 'off',
                'sms_classroom_buy_notify' => 'off',
                'sms_vip_buy_notify' => 'off',
                'sms_coin_buy_notify' => 'off',
            );
            if ($dataUserPosted['sms_order_pay_success'] == 'on') {
                $dataUserPosted['sms_course_buy_notify'] = 'on';
                $dataUserPosted['sms_classroom_buy_notify'] = 'on';
                $dataUserPosted['sms_vip_buy_notify'] = 'on';
                $dataUserPosted['sms_coin_buy_notify'] = 'on';
            } else {
                $dataUserPosted['sms_course_buy_notify'] = 'off';
                $dataUserPosted['sms_classroom_buy_notify'] = 'off';
                $dataUserPosted['sms_vip_buy_notify'] = 'off';
                $dataUserPosted['sms_coin_buy_notify'] = 'off';
            }
            $dataUserPosted = ArrayToolKit::filter($dataUserPosted, $defaultSetting);

            $dataUserPosted = array_merge($dataUserPosted, $schoolNames);

            $this->getSettingService()->set('cloud_sms', $dataUserPosted);
            
            if ('1' == $dataUserPosted['sms_enabled']) {
                $this->setFlashMessage('success', '短信功能开启成功，短信最低￥0.055/条。');
            } else {
                $this->setFlashMessage('success', '设置成功。');
            }
        } 
        return $smsStatus;
    }

    public function smsNoMessageAction(Request $request)
    {
        $this->setCloudSmsKey('show_message', 'off');
        return $this->redirect($this->generateUrl('admin_edu_cloud_sms', array()));
    }

    public function smsBillAction(Request $request)
    {
        try {

            $api = CloudAPIFactory::create('root');
            
            $loginToken = $this->getAppService()->getLoginToken();
            $account = $api->get('/accounts');

            
            $result = $api->get('/bills', array('type' => 'sms', 'page' => 1, 'limit' => 20));

            $paginator = new Paginator(
                $this->get('request'),
                $result["total"],
                20
            );

            $result = $api->get('/bills', array(
                'type' => 'sms', 
                'page' => $paginator->getCurrentPage(), 
                'limit' => $paginator->getPerPageCount()
            ));

            $bills = $result['items'];
        } catch (\RuntimeException $e) {
            return $this->render('TopxiaAdminBundle:EduCloud:api-error.html.twig', array());
        }
           
        return $this->render('TopxiaAdminBundle:EduCloud:sms-bill.html.twig', array(
            'account' => $account,
            'token' => isset($loginToken) && isset($loginToken["token"]) ? $loginToken["token"] : '',
            'bills' => $bills,
            'paginator' => $paginator,
        ));
    }

    protected function getSchoolName()
    {
        $schoolName = $this->setting('cloud_sms.sms_school_name');
        $schoolCandidateName = $this->setting('cloud_sms.sms_school_candidate_name');

        $api = CloudAPIFactory::create('root');
        $result = $api->post("/sms/{$api->getAccessKey()}/applyResult");
        $smsStatus = array();
        if (isset($result['apply']) && isset($result['apply']['status'])) {
            $smsStatus['status'] = $result['apply']['status'];
            if (($smsStatus['status'] == 'passed')&&(strlen($schoolCandidateName) > 0)) {
                $schoolName = $schoolCandidateName;
                $schoolCandidateName = '';
                $this->setCloudSmsKey('sms_school_name', $schoolName);
                $this->setCloudSmsKey('sms_school_candidate_name', '');
            }
            if (isset($result['apply']['message'])) {
                $smsStatus['message'] = $result['apply']['message'];
                if (strlen($smsStatus['message']) > 0){
                    $smsStatus['message'] = $smsStatus['message'];
                }
            }
            if ($smsStatus['status'] == 'failed') {
                $info = '您新申请的网校名称“'.$schoolCandidateName.'”未通过审核，原因是：';
                if(isset($smsStatus['message']) && $smsStatus['message']) {
                    $info .= $smsStatus['message'];
                } else {
                    $info .= '网校名称不符合规范';
                }
                $smsStatus['schoolNameError'] = $info;
                
            }
        } else if (isset($result['error'])) {
            $smsStatus['status'] = 'error';
            $smsStatus['message'] = $result['error'];
        }

        return array(
            $smsStatus, 
            array(
                'sms_school_name' => $schoolName,
                'sms_school_candidate_name' => $schoolCandidateName,
            )
        );
    }

    protected function calStrlen($str)
    {
        return (strlen($str) + mb_strlen($str, 'UTF8')) / 2;
    }

    protected function setCloudSmsKey($key, $val)
    {
        $setting = $this->getSettingService()->get('cloud_sms', array());
        $setting[$key] = $val;
        $this->getSettingService()->set('cloud_sms', $setting);
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    private function getWebExtension()
    {
        return $this->container->get('topxia.twig.web_extension');
    }
}
