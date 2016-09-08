<?php
namespace Topxia\Service\Taxonomy\Tests;

use Topxia\Service\Common\BaseTestCase;
use Topxia\Service\Common\ServiceException;

class TagServiceTest extends BaseTestCase
{
    
    /**
     * @group add
     */
    public function testAddTag()
    {
        $tag = array();
        $tag['name'] = '测试标签';
        $tag = $this->getTagService()->addTag($tag);
        $this->assertNotEmpty($tag);
        $this->assertEquals('测试标签', $tag['name']);
        $this->assertEquals('1', $tag['id']);
        $this->assertGreaterThan(0, $tag['createdTime']);        
    }

    /**
     * @group add
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testAddTagWithEmptyTagName()
    {
        $tag = array();
		$tag['name'] = null;
        $this->getTagService()->addTag($tag);
        $tag['name'] = '';
        $this->getTagService()->addTag($tag);
        $tag['name'] = 0;
        $this->getTagService()->addTag($tag);
    }

    /**
     * @group add
     */
    public function testAddTagWithTooLongTagName()
    {
        $tag = array();
        $tag['name'] = '过长的标签名称过长的标签名称过长的标签名称过长的标签名称';
        $this->getTagService()->addTag($tag);
    }

    /**
     * @group add
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testAddMultiTagNameTag()
    {
        $this->getTagService()->addTag(array('name' => '测试标签'));
        $this->getTagService()->addTag(array('name' => '测试标签'));
    }

    /**
     * @group get
    */
    public function testGetTag()
    {
        $tag = $this->getTagService()->addTag(array('name' => '测试标签'));
        $foundTag = $this->getTagService()->getTag($tag['id']);

        $this->assertEquals('测试标签', $foundTag['name']);
        $this->assertGreaterThan(0, $foundTag['createdTime']);       
    }

    public function testGetTagWithNotExistTagId()
    {
        $tag = $this->getTagService()->addTag(array('name' => '测试标签'));
        $foundTag = $this->getTagService()->getTag(999);
        $this->assertFalse($foundTag);
    }

    public function testGetTagByName()
    {
        $tag = $this->getTagService()->addTag(array('name' => '测试标签'));
        $tagByName = $this->getTagService()->getTagByName('测试标签');

        $this->assertNotEmpty($tagByName);
        $this->assertEquals('测试标签', $tagByName['name']);
        $this->assertGreaterThan(0, $tagByName['createdTime']);   
    }

    public function testGetTagByNameWithNotExistTagName()
    {
        $tag = $this->getTagService()->addTag(array('name' => '测试标签'));
        $foundTag = $this->getTagService()->getTagByName('xxx');
        $this->assertFalse($foundTag);
    }

    public function testfindAllTagsAndGetTagsCount()
    {
        $tagA = array('name' => '测试标签1');
        $tagB = array('name' => '测试标签2');
        $this->getTagService()->addTag($tagA);
        $this->assertEquals(1, $this->getTagService()->getAllTagCount());
        $this->getTagService()->addTag($tagB);
        $this->assertEquals(2, $this->getTagService()->getAllTagCount());
        $tags = $this->getTagService()->findAllTags(0, 1);
        $this->assertEquals(1, count($tags));
        $tags = $this->getTagService()->findAllTags(0, 2);
        $this->assertEquals(2, count($tags));
    }

    /**
    * @group get
    */
    public function testfindAllTagsAndGetTagsCountWithEmptyTags()
    {
        $this->assertEquals(0, $this->getTagService()->getAllTagCount());
        $tags = $this->getTagService()->findAllTags(0, 1);
        $this->assertEquals(0, count($tags));
        $tags = $this->getTagService()->findAllTags(0, 2);
        $this->assertEquals(0, count($tags));
    }

    /**
    * @group get
    */
    public function testfindTagsByIds()
    {
        $tagA = array('name' => '测试标签1');
        $tagB = array('name' => '测试标签2');
        $tagA = $this->getTagService()->addTag($tagA);
        $tagB = $this->getTagService()->addTag($tagB);
        $ids = array($tagA['id'], $tagB['id']);
        $tags = $this->getTagService()->findTagsByIds($ids);
        $this->assertEquals(2, count($tags));
    }

    /**
    * @group get
    */
    public function testfindTagsByIdsWithNotExistId()
    {
        $tagA = array('name' => '测试标签1');
        $tagB = array('name' => '测试标签2');
        $tagA = $this->getTagService()->addTag($tagA);
        $tagB = $this->getTagService()->addTag($tagB);
        $tags = $this->getTagService()->findTagsByIds(array($tagA['id'], $tagB['id'], 99, 12));
        $this->assertEquals(2, count($tags));

        $tags = $this->getTagService()->findTagsByIds(array(99, 12));
        $this->assertEquals(0, count($tags));
    }

    /**
    * @group get
    */
    public function testfindTagsByNames()
    {
    	$tagA = array('name' => '测试标签1');
        $tagB = array('name' => '测试标签2');
        $tagA = $this->getTagService()->addTag($tagA);
        $tagB = $this->getTagService()->addTag($tagB);
        $tags = $this->getTagService()->findTagsByNames(array('测试标签1', '测试标签2'));
        $this->assertEquals(2, count($tags));
    }

    /**
    * @group get
    */
    public function testfindTagsByNamesWithNotExistId()
    {
        $tagA = array('name' => '测试标签1');
        $tagB = array('name' => '测试标签2');
        $tagA = $this->getTagService()->addTag($tagA);
        $tagB = $this->getTagService()->addTag($tagB);
        $tags = $this->getTagService()->findTagsByNames(array('xxx'));
        $this->assertEquals(0, count($tags));

        $tags = $this->getTagService()->findTagsByNames(array('xxx', '测试标签1', '测试标签2'));
        $this->assertEquals(2, count($tags));
    }

    /**
     * @group current
     */
    public function testUpdateTag()
    {
        $tag = array();
        $tag['name'] = '修改前的分类名称';
        $tag = $this->getTagService()->addTag($tag);
        $updateTag = array('name' => '修改后的分类名称');
        $updatedTag = $this->getTagService()->updateTag($tag['id'], $updateTag);
        $this->assertEquals('修改后的分类名称', $updatedTag['name']);
    }

    /**
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testUpdateTagWithNotExistId()
    {
        $tag = array();
        $tag['name'] = '修改前的分类名称';
        $tag = $this->getTagService()->addTag($tag);
        $updateTag = array('name' => '修改后的分类名称');
        $updatedTag = $this->getTagService()->updateTag(999, $updateTag);
        $this->assertFalse($updatedTag);
    }

    /**
     * @group current
     */
    public function testUpdateTagWithTooLongName()
    {
        $tag = array();
        $tag['name'] = '修改前的分类名称';
        $tag = $this->getTagService()->addTag($tag);
        $updateTag = array('name' => '修改后的分类名称修改后的分类名称修改后的分类名称修改后的分类名称修改后的分类名称修改后的分类名称修改后的分类名称');
        $this->getTagService()->updateTag($tag['id'], $updateTag);
    }

    /**
     * @group update
     * @expectedException Topxia\Service\Common\ServiceException
     */
    public function testUpdateTagWithEmptyName()
    {
        $tag = array();
        $tag['name'] = '修改前的分类名称';
        $tag = $this->getTagService()->addTag($tag);
        $updateTag = array('name' => '');
        $this->getTagService()->updateTag($tag['id'], $updateTag);
    }

    /**
     * @group current
     */
    public function testDeleteTag()
    {
    	$tag = array('name' => '测试标签');
    	$tag = $this->getTagService()->addTag($tag);
        $this->assertNull($this->getTagService()->deleteTag($tag['id']));
        $this->assertNull($this->getTagService()->deleteTag($tag['id']));
    }

    /**
     * @group delete
     */
    public function testDeleteTagWithNotExistId()
    {
        $this->assertEquals(0, $this->getTagService()->deleteTag(999));
    }

	protected function getTagService()
	{
		return $this->getServiceKernel()->createService('Taxonomy.TagService');
	}

}
