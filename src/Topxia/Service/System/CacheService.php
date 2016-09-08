<?php

namespace Topxia\Service\System;

interface CacheService
{

	/**
	 * 获取缓存数据
     * 
     * 缓存无效(不存在或已过期)，则返回NULL
	 */
    function get($name);

    /**
     * 批量获取缓存数据
     *
     * 以数组的形式，返回有效的缓存；缓存的name作为数组的key，缓存的data作为数组的value。
     */
    function gets (array $names);

    /**
     * 添加缓存
     * expire=0, 永久
     */
    function set($name, $data, $expiredTime = 0);

    /**
     * 清除缓存
     * name为空的话，则清除所有缓存
     */
    function clear ($name = NULl);
}