<?php

namespace Topxia\Service\Article\Dao;

interface ArticleDao
{
	public function getArticle($id);

	public function getArticlePrevious($categoryId,$createdTime);
	
	public function getArticleNext($categoryId,$createdTime);

	public function getArticleByAlias($alias);

	public function findAllArticles();

	public function findArticlesByCategoryIds(array $categoryIds, $start, $limit);

	public function findArticlesCount(array $categoryIds);

	public function searchArticles($conditions, $orderBys, $start, $limit);

	public function searchArticlesCount($conditions);

	public function addArticle($article);

	public function waveArticle($id,$field,$diff);

	public function updateArticle($id, $article);

	public function deleteArticle($id);

	public function findPublishedArticlesByTagIdsAndCount($tagIds,$count);
}