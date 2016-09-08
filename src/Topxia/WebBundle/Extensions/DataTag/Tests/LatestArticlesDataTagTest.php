<?php

namespace Topxia\WebBundle\Extensions\DataTag\Test;

use Topxia\Service\Common\BaseTestCase;
use Topxia\WebBundle\Extensions\DataTag\LatestArticlesDataTag;

class LatestArticlesDataTagTest extends BaseTestCase
{   

    public function testGetData()
    {
    	$category1 = $this->getCategoryService()->createCategory(array(
            'name' => 'category 1',
            'code' => 'c1',
            'weight' => 1,
            'parentId' => 0
        ));

        $category2 = $this->getCategoryService()->createCategory(array(
            'name' => 'category 2',
            'code' => 'c2',
            'weight' => 1,
            'parentId' => $category1['id']
        ));

        $category3 = $this->getCategoryService()->createCategory(array(
            'name' => 'category 3',
            'code' => 'c3',
            'weight' => 1,
            'parentId' => 0
        ));

    	$article1 = $this->getArtcileService()->createArticle(array(
    		'title' => 'Article1',
    		'categoryId' => $category1['id'],
    		'featured' => 1,
    		'body' => '',
    		'thumb' => '',
    		'originalThumb' => '',
    		'source' => '',
    		'sourceUrl' => '',
    		'publishedTime' => '2015-05-12 09:58:04',
    		'tags' => array()
    	));

    	$article2 = $this->getArtcileService()->createArticle(array(
    		'title' => 'Article2',
    		'categoryId' => $category2['id'],
    		'featured' => 1,
    		'body' => '',
    		'thumb' => '',
    		'originalThumb' => '',
    		'source' => '',
    		'sourceUrl' => '',
    		'publishedTime' => '2015-05-12 09:58:04',
    		'tags' => array()
    	));
    	$article3 = $this->getArtcileService()->createArticle(array(
    		'title' => 'Article2',
    		'categoryId' => $category2['id'],
    		'promoted' => 1,
    		'body' => '',
    		'thumb' => '',
    		'originalThumb' => '',
    		'source' => '',
    		'sourceUrl' => '',
    		'publishedTime' => '2015-05-12 09:58:04',
    		'tags' => array()
    	));
        $datatag = new LatestArticlesDataTag();
        $articles = $datatag->getData(array('count' => 5, 'type' => 'featured','categoryId' => $category1['id']));
        $this->assertEquals(2, count($articles));

    }

    public function getArtcileService()
    {
    	return $this->getServiceKernel()->createService('Article.ArticleService');
    }

    public function getCategoryService()
    {
    	return $this->getServiceKernel()->createService('Article.CategoryService');
    }

}