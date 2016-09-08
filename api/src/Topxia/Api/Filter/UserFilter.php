<?php

namespace Topxia\Api\Filter;
use Topxia\Service\Common\ServiceKernel;

class UserFilter implements Filter
{
    //输出前的字段控制
    //查看权限,附带内容可以写在这里
    public function filter(array &$data)
    {
        $fileService = ServiceKernel::instance()->createService('Content.FileService');
        $userService = ServiceKernel::instance()->createService('User.UserService');
        
        unset($data['password']);
        unset($data['salt']);
        unset($data['payPassword']);
        unset($data['payPasswordSalt']);
       
        $data['promotedTime'] = date('c', $data['promotedTime']);
        $data['lastPasswordFailTime'] = date('c', $data['lastPasswordFailTime']);
        $data['loginTime'] = date('c', $data['loginTime']);
        $data['approvalTime'] = date('c', $data['approvalTime']);
        $data['createdTime'] = date('c', $data['createdTime']);
        
        $host = ServiceKernel::instance()->getParameter('host');
        $smallAvatar = empty($data['smallAvatar']) ? '' : $fileService->parseFileUri($data['smallAvatar']);
        $data['smallAvatar'] = empty($smallAvatar) ? $host.'/assets/img/default/avatar.png' : $host.'/files/'.$smallAvatar['path'];
        $mediumAvatar = empty($data['mediumAvatar']) ? '' : $fileService->parseFileUri($data['mediumAvatar']);
        $data['mediumAvatar'] = empty($mediumAvatar) ? $host.'/assets/img/default/avatar.png' : $host.'/files/'.$mediumAvatar['path'];
        $largeAvatar = empty($data['largeAvatar']) ? '' : $fileService->parseFileUri($data['largeAvatar']);
        $data['largeAvatar'] = empty($largeAvatar) ? $host.'/assets/img/default/avatar.png' : $host.'/files/'.$largeAvatar['path'];
        
        $user = getCurrentUser();
        $profile = $userService->getUserProfile($data['id']);
        $profile['about'] = $this->convertAbsoluteUrl($host, $profile['about']);
        if (!($user->isAdmin() || $user['id'] == $data['id'])) {
            unset($data['email']);
            unset($data['verifiedMobile']);
            unset($data['uri']);
            unset($data['tags']);
            unset($data['type']);
            unset($data['point']);
            unset($data['coin']);
            unset($data['emailVerified']);
            unset($data['setup']);
            unset($data['promoted']);
            unset($data['promotedTime']);
            unset($data['locked']);//TODO 是否需要处理
            unset($data['lockDeadline']);//TODO 是否需要处理
            unset($data['lastPasswordFailTime']);
            unset($data['consecutivePasswordErrorTimes']);
            unset($data['loginTime']);
            unset($data['loginIp']);
            unset($data['loginSessionId']);
            unset($data['approvalTime']);
            unset($data['approvalStatus']);
            unset($data['newMessageNum']);
            unset($data['newNotificationNum']);
            unset($data['createdIp']);
            unset($data['createdTime']);
            $data['about'] = $profile['about'];
            return $data;
        }
        $data = array_merge($data,$profile);

        unset($data['intField1']);
        unset($data['intField2']);
        unset($data['intField3']);
        unset($data['intField4']);
        unset($data['intField5']);

        unset($data['dateField1']);
        unset($data['dateField2']);
        unset($data['dateField3']);
        unset($data['dateField4']);
        unset($data['dateField5']);

        unset($data['floatField1']);
        unset($data['floatField2']);
        unset($data['floatField3']);
        unset($data['floatField4']);
        unset($data['floatField5']);

        unset($data['varcharField1']);
        unset($data['varcharField2']);
        unset($data['varcharField3']);
        unset($data['varcharField4']);
        unset($data['varcharField5']);
        unset($data['varcharField6']);
        unset($data['varcharField7']);
        unset($data['varcharField8']);
        unset($data['varcharField9']);
        unset($data['varcharField10']);

        unset($data['textField1']);
        unset($data['textField2']);
        unset($data['textField3']);
        unset($data['textField4']);
        unset($data['textField5']);
        unset($data['textField6']);
        unset($data['textField7']);
        unset($data['textField8']);
        unset($data['textField9']);
        unset($data['textField10']);
        return $data;

    }

    public function filters(array &$datas)
    {
        $num = 0;
        $results = array();
        foreach ($datas as $data) {
            $results[$num] = $this->filter($data);
            $num++;
        }
        return $results;
    }

    public function convertAbsoluteUrl($host, $html)
    {
        $html = preg_replace_callback('/src=[\'\"]\/(.*?)[\'\"]/', function($matches) use ($host) {
            return "src=\"{$host}/{$matches[1]}\"";
        }, $html);

        return $html;

    }

}

