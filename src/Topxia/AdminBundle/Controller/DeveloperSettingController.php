<?php

namespace Topxia\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\FileToolkit;
use Topxia\Component\OAuthClient\OAuthClientFactory;
use Topxia\Service\Util\CloudClientFactory;
use Symfony\Component\Filesystem\Filesystem;
use Topxia\Common\JsonToolkit;

class DeveloperSettingController extends BaseController
{
    public function indexAction(Request $request)
    {
        $developerSetting = $this->getSettingService()->get('developer', array());
        $storageSetting = $this->getSettingService()->get('storage', array());

        $default = array(
            'debug' => '0',
            'app_api_url' => '',
            'cloud_api_server' => empty($storageSetting['cloud_api_server']) ? '' : $storageSetting['cloud_api_server'],
            'cloud_api_tui_server' => empty($storageSetting['cloud_api_tui_server']) ? '' : $storageSetting['cloud_api_tui_server'],
            'hls_encrypted' => '1',
        );

        $developerSetting = array_merge($default, $developerSetting);

        if ($request->getMethod() == 'POST') {
            $developerSetting = $request->request->all();

            $storageSetting['cloud_api_server'] = $developerSetting['cloud_api_server'];
            $storageSetting['cloud_api_tui_server'] = $developerSetting['cloud_api_tui_server'];
            $this->getSettingService()->set('storage', $storageSetting);
            $this->getSettingService()->set('developer', $developerSetting);

            $this->getLogService()->info('system', 'update_settings', "更新开发者设置", $developerSetting);

            $this->dealServerConfigFile();
            $this->dealNetworkLockFile($developerSetting);

            $this->setFlashMessage('success', '开发者已保存！');
        }

        return $this->render('TopxiaAdminBundle:DeveloperSetting:index.html.twig', array(
            'developerSetting' => $developerSetting,
        ));

    }

    protected function dealServerConfigFile()
    {
        $serverConfigFile = $this->getServiceKernel()->getParameter('kernel.root_dir') . '/data/api_server.json';
        $fileSystem = new Filesystem();
        $fileSystem->remove($serverConfigFile);
    }

    protected function dealNetworkLockFile($developerSetting)
    {
        $networkLock = $this->getServiceKernel()->getParameter('kernel.root_dir') . '/data/network.lock';
        $fileSystem = new Filesystem();

        if(isset($developerSetting['without_network']) && $developerSetting['without_network'] == 1 && !$fileSystem->exists($networkLock)) {
            $fileSystem->touch($networkLock);
        } else if(!isset($developerSetting['without_network']) || $developerSetting['without_network'] == 0){
            $fileSystem->remove($networkLock);
        }
    }

    public function versionAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            $app = $this->getAppservice()->getAppByCode($data['code']);
            if (empty($app)) {
                throw $this->createNotFoundException();
            }
            $this->getAppservice()->updateAppVersion($app['id'], $data['version']);
            return $this->redirect($this->generateUrl('admin_app_upgrades'));
        }

        $appCount = $this->getAppservice()->findAppCount();
        $apps = $this->getAppservice()->findApps(0, $appCount);

        return $this->render('TopxiaAdminBundle:DeveloperSetting:version.html.twig', array(
            'apps' => $apps,
        ));
    }

    public function magicAction(Request $request)
    {

        if ($request->getMethod() == 'POST') {
            $setting = $request->request->get('setting', '{}');
            $setting = json_decode($setting, true);
            $this->getSettingService()->set('magic', $setting);
            $this->getLogService()->info('system', 'update_settings', "更新Magic设置", $setting);
            $this->setFlashMessage('success', '设置已保存！');
        }

        $setting = $this->getSettingService()->get('magic', array());
        $setting = JsonToolkit::prettyPrint(json_encode($setting));

        return $this->render('TopxiaAdminBundle:DeveloperSetting:magic.html.twig', array(
            'setting' => $setting,
        ));
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }
}