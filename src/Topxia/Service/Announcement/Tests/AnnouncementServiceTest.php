<?php
namespace Topxia\Service\Announcement\Tests;

use Topxia\Service\Common\BaseTestCase;
use Topxia\Service\Announcement\AnnouncementService;
use Topxia\Common\ArrayToolkit;

class AnnouncementServiceTest extends BaseTestCase
{
	public function testCreateAnnouncement()
    {
        $announcementInfo = array(
        	'targetType' => 'course',
        	'targetId' => '1',
        	'content' => 'test_announcement',
            'startTime'=>time(),
            'endTime'=>time()+3600*1000,
            'url'=> 'http://www.baidu.com'
        );

        $createdAnnouncement = $this->getAnnouncementService()->createAnnouncement($announcementInfo);

        $this->assertNotNull($createdAnnouncement);
    }

    public function testGetAnnouncement()
    {
        $announcementInfo = array(
        	'targetType' => 'course',
        	'targetId' => '1',
        	'content' => 'test_announcement',
            'startTime'=>time(),
            'endTime'=>time()+3600*1000,
            'url'=> 'http://www.baidu.com'
        );

        $createdAnnouncement = $this->getAnnouncementService()->createAnnouncement($announcementInfo);

        $getedAnnouncement = $this->getAnnouncementService()->getAnnouncement($createdAnnouncement['id']);

        $this->assertEquals($this->getCurrentUser()->id, $getedAnnouncement['userId']);
        $this->assertEquals(1, $getedAnnouncement['targetId']);
        $this->assertEquals('test_announcement', $getedAnnouncement['content']);
    }

    public function testSearchAnnouncements()
    {
        $announcementInfo1 = array(
        	'targetType' => 'course',
        	'targetId' => '1',
        	'content' => 'test_announcement1',
            'startTime'=>time(),
            'endTime'=>time()+3600*1000,
            'url'=> 'http://www.baidu.com'
        );

        $announcementInfo2 = array(
        	'targetType' => 'course',
        	'targetId' => '1',
        	'content' => 'test_announcement2',
            'startTime'=>time(),
            'endTime'=>time()+3600*1000,
            'url'=> 'http://www.baidu.com'
        );

        $announcement1 = $this->getAnnouncementService()->createAnnouncement($announcementInfo1);
        $announcement2 = $this->getAnnouncementService()->createAnnouncement($announcementInfo2);
        $resultAnnouncements = $this->getAnnouncementService()->searchAnnouncements(array('targetType'=>'course','targetId'=>1), array('createdTime','DESC'), 0, 30);

        $this->assertContains($announcement1, $resultAnnouncements);
        $this->assertContains($announcement2, $resultAnnouncements);
    }

    public function testDeleteAnnouncement()
    {
        $announcementInfo = array(
        	'targetType' => 'course',
        	'targetId' => '1',
        	'content' => 'test_deleteAnnouncement',
            'startTime'=>time(),
            'endTime'=>time()+3600*1000,
            'url'=> 'http://www.baidu.com'
        );

        $createdAnnouncement = $this->getAnnouncementService()->createAnnouncement($announcementInfo);
        $this->getAnnouncementService()->deleteAnnouncement($createdAnnouncement['id']);
        $getAnnouncement = $this->getAnnouncementService()->getAnnouncement($createdAnnouncement['id']);
        
        $this->assertNull($getAnnouncement);
    }

    public function testUpdateAnnouncement()
    {
        $announcementInfo = array(
        	'targetType' => 'course',
        	'targetId' => '1',
        	'content' => 'test_updateAnnouncement',
            'startTime'=>time(),
            'endTime'=>time()+3600*1000,
            'url'=> 'http://www.baidu.com'
        );

        $createdAnnouncement = $this->getAnnouncementService()->createAnnouncement($announcementInfo);
        $updateInfo = array(
            'targetType' => 'course',
            'targetId' => '1',
            'content' => 'update_info',
            'startTime'=>time(),
            'endTime'=>time()+3600*1000,
            'url'=> 'http://www.baidu.com'
        );
        $this->getAnnouncementService()->updateAnnouncement($createdAnnouncement['id'], $updateInfo);
        
        $getAnnouncement = $this->getAnnouncementService()->getAnnouncement($createdAnnouncement['id']);
        
        $this->assertEquals($updateInfo['content'], $getAnnouncement['content']);
    }

    protected function getAnnouncementService()
    {
        return $this->getServiceKernel()->createService('Announcement.AnnouncementService');
    }

}