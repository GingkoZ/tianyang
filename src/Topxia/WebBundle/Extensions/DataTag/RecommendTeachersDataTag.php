<?php

namespace Topxia\WebBundle\Extensions\DataTag;

use Topxia\WebBundle\Extensions\DataTag\DataTag;
use Topxia\Common\ArrayToolkit;

class RecommendTeachersDataTag extends CourseBaseDataTag implements DataTag  
{

    /**
     * 获取推荐老师列表
     *
     * 可传入的参数：
     *   count    必需 老师数量，取值不能超过100
     * 
     * @param  array $arguments 参数
     * @return array 推荐老师列表
     */
    public function getData(array $arguments)
    {	

        $this->checkCount($arguments);
        $conditions = array(
            'roles'=>'ROLE_TEACHER',
            'promoted'=>'1',
            'locked'=>0
        );
        
    	$users = $this->getUserService()->searchUsers($conditions, array('promotedTime', 'DESC'), 0, $arguments['count']);

        
        $profiles = $this->getUserService()->findUserProfilesByIds(ArrayToolkit::column($users,'id'));
        $user = $this->getUserService()->getCurrentUser();
        if($user->isLogin()){
            $myfollowings = $this->getUserService()->filterFollowingIds($user['id'], ArrayToolkit::column($users, 'id'));
        }else{
            $myfollowings = array();
        }

        foreach ($users as $key => $user) {
            if ($user['id'] == $profiles[$user['id']]['id']) {
                $users[$key]['about'] = $profiles[$user['id']]['about'];
                $users[$key]['profile'] = $profiles[$user['id']];
            }
            $users[$key]['isFollowed'] = in_array($user['id'],$myfollowings) ? 1 : 0;
        }

        return $this->unsetUserPasswords($users);
    }

}
