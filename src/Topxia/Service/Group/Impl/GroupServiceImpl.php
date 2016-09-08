<?php

namespace Topxia\Service\Group\Impl;

use Topxia\Service\Common\BaseService;
use Topxia\Service\Group\GroupService;
use Topxia\Common\ArrayToolkit;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;
use Symfony\Component\HttpFoundation\File\File;


class GroupServiceImpl extends BaseService implements GroupService {

    public function getGroup($id)
    {
          return $this->getGroupDao()->getGroup($id);
    }

    public function getGroupsByIds($ids)
    {
        $groups=$this->getGroupDao()->getGroupsByIds($ids);
        return ArrayToolkit::index($groups, 'id');
    }

    public function searchGroups($conditions, $orderBy, $start, $limit)
    {

        $conditions = $this->prepareGroupConditions($conditions);
        return $this->getGroupDao()->searchGroups($conditions,$orderBy,$start,$limit);
    }

    public function searchMembersCount($conditions)
    {
         $count= $this->getGroupMemberDao()->searchMembersCount($conditions);
         return $count;

    }

    public function updateGroup($id,$fields)
    {   
        if(isset($fields['about'])){
            $fields['about']=$this->purifyHtml($fields['about']);
        }
        $group=$this->getGroupDao()->updateGroup($id,$fields);
        return $group;
    }
    public function updateMember($id, $fields)
    {
        return $this->getGroupMemberDao()->updateMember($id, $fields);
    }

    public function addGroup($user,$group)
    {   
        if (!isset($group['title']) || empty($group['title'])) {
            throw $this->createServiceException("小组名称不能为空！");
        }
        $title=trim($group['title']);

        if(isset($group['about'])){
            $group['about']=$this->purifyHtml($group['about']);
        }
        $group['ownerId']=$user['id'];
        $group['memberNum']=1;
        $group['createdTime']=time();
        $group = $this->getGroupDao()->addGroup($group);
        $member = array(
            'groupId' => $group['id'],
            'userId' => $user['id'],
            'createdTime' => time(),
            'role' => 'owner',
        );
        $this->getGroupMemberDao()->addMember($member);
        return $group;
    }

    public function addOwner($groupId,$userId)
    {
        $member = array(
            'groupId' => $groupId,
            'userId' => $userId,
            'createdTime' => time(),
            'role' => 'owner',
        );

        $member=$this->getGroupMemberDao()->addMember($member);

        $this->reCountGroupMember($groupId);

        return $member;
    }
    public function openGroup($id)
    {
         return $this->updateGroup($id, array(
            'status' => 'open',
        ));
    }

    public function closeGroup($id)
    {
         return $this->updateGroup($id, array(
            'status' => 'close',
        ));
    }

    public function changeGroupImg($id, $field, $data)
    {
        if(!in_array($field, array("logo", "backgroundLogo"))) {
            throw $this->createServiceException('更新的字段错误！');
        }

        $group=$this->getGroup($id);
        if (empty($group)) {
            throw $this->createServiceException('小组不存在，更新失败！');
        }

        $fileIds = ArrayToolkit::column($data, "id");
        $files = $this->getFileService()->getFilesByIds($fileIds);

        $files = ArrayToolkit::index($files, "id");
        $fileIds = ArrayToolkit::index($data, "type");

        $fields = array(
            $field => $files[$fileIds[$field]["id"]]["uri"],
        );

        $oldAvatars = array(
            $field => $group[$field] ? $group[$field] : null,
        );

        $fileService = $this->getFileService();
        array_map(function($oldAvatar) use($fileService){
            if (!empty($oldAvatar)) {
                $fileService->deleteFileByUri($oldAvatar);
            }
        }, $oldAvatars);

        return  $this->getGroupDao()->updateGroup($id, $fields);

    }

    public function joinGroup($user,$groupId) 
    {
        $group= $this->getGroup($groupId);
        if (empty($group)) {
            throw $this->createServiceException("小组不存在, 加入小组失败！");
        }

        if($this->isMember($groupId, $user['id'])){
            throw $this->createServiceException('您已加入小组！！');
        }

        $member = array(
            'groupId' => $groupId,
            'userId' => $user['id'],
            'createdTime' => time(),
        );
        $member=$this->getGroupMemberDao()->addMember($member);

        $this->reCountGroupMember($groupId);

        return $member;
    }
        
    public function exitGroup($user,$groupId)
    {
        $group= $this->getGroup($groupId);
        if (empty($group)) {
            throw $this->createServiceException("小组不存在,退出小组失败！");
        }

        $member=$this->getGroupMemberDao()->getMemberByGroupIdAndUserId($groupId,$user['id']);

        if(empty($member)) {
            throw $this->createServiceException('退出小组失败！');
        }

        $this->getGroupMemberDao()->deleteMember($member['id']);

        $this->reCountGroupMember($groupId);

    }

    public function findGroupsByUserId($userId)
    {
        $members=$this->getGroupMemberDao()->getMembersByUserId($userId);
        if($members) {
            foreach ($members as $key ) 
            {
            $ids[]=$key['groupId'];
            }
            return $this->getGroupDao()->getGroupsByIds($ids);
        }
        return array();
    }

    public function findGroupByTitle($title)
    {
        return $this->getGroupDao()->getGroupByTitle($title);
    }

    public function searchMembers($conditions, $orderBy, $start, $limit)
    {   
        return $this->getGroupMemberDao()->searchMembers($conditions,$orderBy,$start,$limit);
    }

    public function searchGroupsCount($conditions)
    {
         $conditions = $this->prepareGroupConditions($conditions);
         $count= $this->getGroupDao()->searchGroupsCount($conditions);
         return $count;
    }

    public function isOwner($id,$userId)
    {
        $group=$this->getGroupDao()->getGroup($id);
        return $group['ownerId']==$userId ? true : false;
    }

    public function isAdmin($groupId, $userId) 
    {
        $member=$this->getGroupMemberDao()->getMemberByGroupIdAndUserId($groupId, $userId);
        return $member['role']=="admin" ? true : false;
    }

    public function isMember($groupId, $userId) 
    {
        $member=$this->getGroupMemberDao()->getMemberByGroupIdAndUserId($groupId, $userId);
        return $member ? true : false;
    }

    public function getMembersCountByGroupId($id)
    {
        return $this->getGroupMemberDao()->getMembersCountByGroupId($id);
    }

    public function getMemberByGroupIdAndUserId($groupid,$userId)
    {
        return $this->getGroupMemberDao()->getMemberByGroupIdAndUserId($groupid,$userId);
    }

    protected function reCountGroupMember($groupId)
    {
        $groupMemberNum=$this->getGroupMemberDao()->getMembersCountByGroupId($groupId);
        $this->getGroupDao()->updateGroup($groupId,array('memberNum'=>$groupMemberNum));
    }

    public function waveGroup($id, $field, $diff)
    {
        return $this->getGroupDao()->waveGroup($id,$field, $diff);
    }

    public function waveMember($groupId,$userId,$field,$diff)
    {
        $member=$this->getGroupMemberDao()->getMemberByGroupIdAndUserId($groupId,$userId);

        if($member){
          return $this->getGroupMemberDao()->waveMember($member['id'], $field, $diff); 
        }
        
    }

    public function deleteMemberByGroupIdAndUserId($groupId,$userId)
    {
        $member=$this->getGroupMemberDao()->getMemberByGroupIdAndUserId($groupId,$userId);

        $this->getGroupMemberDao()->deleteMember($member['id']); 

        $this->reCountGroupMember($groupId);
    }

    protected function prepareGroupConditions($conditions)
    {

        if(isset($conditions['ownerName'])&&$conditions['ownerName']!==""){

            $owner=$this->getUserService()->getUserByNickname($conditions['ownerName']);

            if(!empty($owner)){
                  $conditions['ownerId']=$owner['id'];
            }else{
                  $conditions['ownerId']=0;
            }
   
        }
        if(isset($conditions['status']))
        {
            if($conditions['status']==""){
               unset( $conditions['status']);
            }
        }
        
        return $conditions;
    }

    protected function getLogService() 
    {
        return $this->createService('System.LogService');
    }

    protected function getGroupDao() 
    {
        return $this->createDao('Group.GroupDao');
    }

    protected function getGroupMemberDao() 
    {
        return $this->createDao('Group.GroupMemberDao');
    }

    protected function getFileService()
    {
        return $this->createService('Content.FileService');
    }

    protected function getUserService()
    {
        return $this->createService('User.UserService');
    }

    protected function getMessageService() 
    {
        return $this->createService('User.MessageService');
    }

    

}
