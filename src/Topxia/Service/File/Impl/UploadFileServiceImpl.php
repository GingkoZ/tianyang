<?php

namespace Topxia\Service\File\Impl;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\FileToolkit;
use Topxia\Service\Common\BaseService;
use Topxia\Service\Util\EdusohoCloudClient;
use Topxia\Service\File\UploadFileService;
    
class UploadFileServiceImpl extends BaseService implements UploadFileService
{
	static $implementor = array(
        'local'=>'File.LocalFileImplementor',
        'cloud' => 'File.CloudFileImplementor',
    );

    public function getFile($id)
    {
       $file = $this->getUploadFileDao()->getFile($id);
       if(empty($file)){
        return null;
       }
       return $this->getFileImplementorByFile($file)->getFile($file);
    }

    public function getFileByHashId($hashId)
    {
       $file = $this->getUploadFileDao()->getFileByHashId($hashId);
       if(empty($file)){
        return null;
       }
       return $this->getFileImplementorByFile($file)->getFile($file);
    }

    public function getFileByConvertHash($hash)
    {
        return $this->getUploadFileDao()->getFileByConvertHash($hash);
    }

    public function findFilesByIds(array $ids)
    {
       return  $this->getUploadFileDao()->findFilesByIds($ids);
    }

    public function searchFiles($conditions, $sort, $start, $limit)
    {
        switch ($sort) {
            case 'latestUpdated':
                $orderBy = array('updatedTime', 'DESC');
                break;
            case 'oldestUpdated':
                $orderBy =  array('updatedTime', 'ASC');
                break; 
            case 'latestCreated':
                $orderBy =  array('createdTime', 'DESC');
                break;
            case 'oldestCreated':
                $orderBy =  array('createdTime', 'ASC');
                break;            
            case 'extAsc':
                $orderBy =  array('ext', 'ASC');
                break;            
            case 'extDesc':
                $orderBy =  array('ext', 'DESC');
                break;
            case 'nameAsc':
                $orderBy =  array('filename', 'ASC');
                break;            
            case 'nameDesc':
                $orderBy =  array('filename', 'DESC');
                break;
            case 'sizeAsc':
                $orderBy =  array('size', 'ASC');
                break;
            case 'sizeDesc':
                $orderBy =  array('size',
                    'DESC' 
                );
                break;
			default :
                throw $this->createServiceException ( '参数sort不正确。' );
		}
		
		if (array_key_exists('source', $conditions) && $conditions['source'] == 'shared') {
			//Find all the users who is sharing with current user.
            $myFriends = $this->getUploadFileShareDao ()->findMySharingContacts ($conditions ['currentUserId']);
			
            if(isset($myFriends)) {
				$createdUserIds = ArrayToolkit::column ($myFriends, "sourceUserId" );
			}else{
				//Browsing shared files, but nobody is sharing with current user.
				return array();
			}
			
		} elseif (isset($conditions['currentUserId'] )) {
			$createdUserIds = array($conditions['currentUserId']);
		}
		
		if(isset($createdUserIds)){
			$conditions['createdUserIds'] = $createdUserIds;
		}

		return $this->getUploadFileDao()->searchFiles($conditions, $orderBy, $start, $limit);
    }

    public function searchFileCount($conditions)
    {
    	
    	if (array_key_exists('source', $conditions) && $conditions['source'] == 'shared') {
    		//Find all the users who is sharing with current user.
    		$myFriends = $this->getUploadFileShareDao ()->findMySharingContacts($conditions['currentUserId']);
    			
    		if (isset($myFriends)) {
                $createdUserIds = ArrayToolkit::column($myFriends, "sourceUserId");
    		}else{
    			//Browsing shared files, but nobody is sharing with current user.
                return 0;
    		}
    			
    	} elseif (isset($conditions['currentUserId'] )) {
    		$createdUserIds = array($conditions['currentUserId']);
    	}
    	
    	if(isset($createdUserIds)){
    		$conditions['createdUserIds'] = $createdUserIds;
    	}
    	
        return $this->getUploadFileDao()->searchFileCount($conditions);
    }

    public function addFile($targetType,$targetId,array $fileInfo=array(),$implemtor='local',UploadedFile $originalFile=null)    
    {
        $file = $this->getFileImplementor($implemtor)->addFile($targetType,$targetId,$fileInfo,$originalFile);

        $file = $this->getUploadFileDao()->addFile($file);
        
        return $file; 
    }

    public function renameFile($id, $newFilename)
    {
        $this->getUploadFileDao()->updateFile($id,array('filename'=>$newFilename));
        return $this->getFile($id);
    }

    public function deleteFile($id)
    {
        $file = $this->getFile($id);
        if (empty($file)) {
            throw $this->createServiceException("文件(#{$id})不存在，删除失败");
        }
        
        $deleted = $this->getFileImplementorByFile($file)->deleteFile($file);
        if ($deleted) {
            $deleted = $this->getUploadFileDao()->deleteFile($id);
        }

        return $deleted;
    }

    public function deleteFiles(array $ids)
    {
        foreach ($ids as $id) {
            $this->deleteFile($id);
        }
    }

    public function saveConvertResult($id, array $result = array())
    {
        $file = $this->getFile($id);
        if (empty($file)) {
            throw $this->createServiceException("文件(#{$id})不存在，转换失败");
        }

        $file = $this->getFileImplementorByFile($file)->saveConvertResult($file, $result);

        $this->getUploadFileDao()->updateFile($id, array(
            'convertStatus' => $file['convertStatus'],
            'metas2' => json_encode($file['metas2']),
            'updatedTime' => time(),
        ));

        return $this->getFile($id);
    }

    public function saveConvertResult3($id, array $result = array())
    {
        $file = $this->getFile($id);
        if (empty($file)) {
            throw $this->createServiceException("文件(#{$id})不存在，转换失败");
        }
        $file['convertParams']['convertor'] = 'HLSEncryptedVideo';

        $fileNeedUpdateFields = array();

        $file = $this->getFileImplementorByFile($file)->saveConvertResult($file, $result);

        if ($file['convertStatus'] == 'success') {
            $fileNeedUpdateFields['convertParams'] = json_encode($file['convertParams']);
            $fileNeedUpdateFields['metas2'] = json_encode($file['metas2']);
            $fileNeedUpdateFields['updatedTime'] = time();
            $this->getUploadFileDao()->updateFile($id, $fileNeedUpdateFields);
        }

        return $this->getFile($id);
    }

    public function convertFile($id, $status, array $result = array(), $callback = null)
    {
        $statuses = array('none', 'waiting', 'doing', 'success', 'error');
        if (!in_array($status, $statuses)) {
            throw $this->createServiceException('状态不正确，变更文件转换状态失败！');
        }

        $file = $this->getFile($id);
        if (empty($file)) {
            throw $this->createServiceException("文件(#{$id})不存在，转换失败");
        }

        $file = $this->getFileImplementorByFile($file)->convertFile($file, $status, $result, $callback);

        $this->getUploadFileDao()->updateFile($id, array(
            'convertStatus' => $file['convertStatus'],
            'metas2' => $file['metas2'],
            'updatedTime' => time(),
        ));

        return $this->getFile($id);
    }

    public function setFileConverting($id, $convertHash)
    {
        $file = $this->getFile($id);
        if (empty($file)) {
            throw $this->createServiceException('file not exist.');
        }

        // $status = $file['convertStatus'] == 'success' ? 'success' : 'waiting';

        $fields = array(
            'convertStatus' => 'waiting',
            'convertHash' => $convertHash,
            'updatedTime' => time(),
        );
        $this->getUploadFileDao()->updateFile($id, $fields);

        return $this->getFile($id);
    }

    public function makeUploadParams($params)
    {    
        return $this->getFileImplementor($params['storage'])->makeUploadParams($params);
    }

    public function reconvertFile($id, $convertCallback)
    {
        $file = $this->getFile($id);
        if (empty($file)) {
            throw $this->createServiceException('file not exist.');
        }
        $convertHash = $this->getFileImplementorByFile($file)->reconvertFile($file, $convertCallback);

        $this->setFileConverting($file['id'], $convertHash);

        return $convertHash;
    }

    public function reconvertOldFile($id, $convertCallback, $pipeline)
    {
        $result = array();

        $file = $this->getFile($id);
        if (empty($file)) {
            return array('error' => 'file_not_found', 'message' => "文件(#{$id})不存在");
        }

        if ($file['storage'] != 'cloud') {
            return array('error' => 'not_cloud_file', 'message' => "文件(#{$id})，不是云文件。");
        }

        if ($file['type'] != 'video') {
            return array('error' => 'not_video_file', 'message' => "文件(#{$id})，不是视频文件。");
        }

        if ($file['targetType'] != 'courselesson') {
            return array('error' => 'not_course_file', 'message' => "文件(#{$id})，不是课时文件。");
        }

        $target = $this->createService('Course.CourseService')->getCourse($file['targetId']);
        if (empty($target)) {
            return array('error' => 'course_not_exist', 'message' => "文件(#{$id})所属的课程已删除。");
        }

        if (!empty($file['convertParams']['convertor']) && $file['convertParams']['convertor'] == 'HLSEncryptedVideo') {
            return array('error' => 'already_converted', 'message' => "文件(#{$id})已转换");
        }

        $fileNeedUpdateFields = array();

        if (!empty($file['convertParams']['convertor']) && $file['convertParams']['convertor'] == 'HLSVideo') {
            $file['convertParams']['hlsKeyUrl'] = 'http://hlskey.edusoho.net/placeholder';
            $file['convertParams']['hlsKey'] = $this->generateKey(16);
            if ($file['convertParams']['videoQuality'] == 'low') {
                $file['convertParams']['videoQuality'] = 'normal';
                $file['convertParams']['video'] = array('440k', '640k', '1000K');
            }

            $fileNeedUpdateFields['convertParams'] = json_encode($file['convertParams']);
            $file['convertParams']['convertor'] = 'HLSEncryptedVideo';
        }

        if (empty($file['convertParams'])) {
            $convertParams = array(
                'convertor' => 'HLSEncryptedVideo',
                'segtime' => 10,
                'videoQuality' => 'normal',
                'audioQuality' => 'normal',
                'video' => array('440k', '640k', '1000K'),
                'audio' => array('48k', '64k', '96k'),
                'hlsKeyUrl' => 'http://hlskey.edusoho.net/placeholder',
                'hlsKey' => $this->generateKey(16),
            );

            $file['convertParams'] = $convertParams;

            $convertParams['convertor'] = 'HLSVideo';
            $fileNeedUpdateFields['convertParams'] = json_encode($convertParams);
        }

        $convertHash = $this->getFileImplementorByFile($file)->reconvertOldFile($file, $convertCallback, $pipeline);
        if (empty($convertHash)) {
            return array('error' => 'convert_request_failed', 'message' => "文件(#{$id})转换请求失败！");
        }

        $fileNeedUpdateFields['convertHash'] = $convertHash;
        $fileNeedUpdateFields['updatedTime'] = time();

        $this->getUploadFileDao()->updateFile($file['id'], $fileNeedUpdateFields);


        $subTarget = $this->createService('Course.CourseService')->findLessonsByTypeAndMediaId('video', $file['id']) ? : array();
        if (!empty($subTarget)) {
            $subTarget = $subTarget[0];
        }

        return array(
            'convertHash' => $convertHash,
            'courseId' => empty($subTarget['courseId']) ? $target['targetId'] : $subTarget['courseId'],
            'lessonId' => empty($subTarget['id']) ? 0 : $subTarget['id'],
        );
    }

    public function getMediaInfo($key, $type)
    {
        return $this->getFileImplementor('cloud')->getMediaInfo($key, $type);
    }

    public function getFileByTargetType($targetType)
    {
        return $this->getUploadFileDao()->getFileByTargetType($targetType);
    }
	
	public function findMySharingContacts($targetUserId){
		$userIds = $this->getUploadFileShareDao()->findMySharingContacts($targetUserId);
		 
		if(!empty($userIds)){
			return $this->getUserService()->findUsersByIds ( ArrayToolkit::column ( $userIds, 'sourceUserId' ) );
		}else{
			return null;
		}
	}
	
	public function findShareHistory($sourceUserId){
		$shareHistories = $this->getUploadFileShareDao()->findShareHistoryByUserId($sourceUserId);
		
		return $shareHistories;
	}

	public function shareFiles($sourceUserId, $targetUserIds) {
		foreach ( $targetUserIds as $targetUserId ) {
			//Ignore sharing request if the sourceUserId equasls to targetUserId
			if($targetUserId != $sourceUserId){
				$shareHistory = $this->getUploadFileShareDao()->findShareHistory($sourceUserId, $targetUserId);
				
				if(isset($shareHistory)){
					//File sharing record exists, update the existing record
					$fileShareFields = array (
							'isActive' => 1,
							'updatedTime' => time ()
					);
					
					$this->getUploadFileShareDao()->updateShare($shareHistory['id'], $fileShareFields);
				}else{
					//Add new file sharing record
					$fileShareFields = array (
							'sourceUserId' => $sourceUserId,
							'targetUserId' => $targetUserId,
							'isActive' => 1,
							'createdTime' => time (),
							'updatedTime' => time () 
					);
					
					$this->getUploadFileShareDao()->addShare($fileShareFields);

				}
                
			}
		}
	}

    public function findShareHistoryByUserId($sourceUserId, $targetUserId)
    {
        return $this->getUploadFileShareDao()->findShareHistory($sourceUserId, $targetUserId);
    }

    public function addShare($sourceUserId, $targetUserId)
    {
        $fileShareFields = array (
            'sourceUserId' => $sourceUserId,
            'targetUserId' => $targetUserId,
            'isActive' => 1,
            'createdTime' => time (),
            'updatedTime' => time () 
        );
        
        return $this->getUploadFileShareDao()->addShare($fileShareFields);

    }

    public function updateShare($shareHistoryId)
    {
        $fileShareFields = array (
                'isActive' => 1,
                'updatedTime' => time ()
        );
        
        return $this->getUploadFileShareDao()->updateShare($shareHistoryId, $fileShareFields);
    }

	public function cancelShareFile($sourceUserId, $targetUserId) {
		$shareHistory = $this->getUploadFileShareDao ()->findShareHistory ( $sourceUserId, $targetUserId );
		
		if (! empty ( $shareHistory )) {
			$fileShareFields = array (
					'isActive' => 0,
					'updatedTime' => time () 
			);
			
			$this->getUploadFileShareDao ()->updateShare ( $shareHistory ['id'], $fileShareFields );
		}
	}

    public function waveUploadFile($id, $field, $diff)
    {
      $this->getUploadFileDao()->waveUploadFile($id, $field, $diff);  
    }

    protected function generateKey ($length = 0 )
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $key = '';
        for ( $i = 0; $i < 16; $i++ ) {
            $key .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
        
        return $key;
    }

    protected function getFileImplementorByFile($file)
    {
        return $this->getFileImplementor($file['storage']);
    }

    protected function getUploadFileDao()
    {
        return $this->createDao('File.UploadFileDao');
    }
    
    protected function getUploadFileShareDao(){
    	return $this->createDao('File.UploadFileShareDao');
    }

    protected function getUserService()
    {
        return $this->createService('User.UserService');
    }

    protected function getFileImplementor($key)
    {
        if (!array_key_exists($key, self::$implementor)) {
            throw $this->createServiceException(sprintf("`%s` File Implementor is not allowed.", $key));
        }

        return $this->createService(self::$implementor[$key]);
    }

    protected function getLogService()
    {
        return $this->createService('System.LogService');
    }

    
}
