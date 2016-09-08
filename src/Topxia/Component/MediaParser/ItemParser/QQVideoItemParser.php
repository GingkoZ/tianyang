<?php

namespace Topxia\Component\MediaParser\ItemParser;

class QQVideoItemParser extends AbstractItemParser
{

    private $patterns = array(
        'p1' => '/^http\:\/\/v\.qq\.com\/cover\//s',
        'p2' => '/^http\:\/\/v\.qq\.com\/boke\/page\//s',
        'p3' => '/^http\:\/\/v\.qq\.com\/page\//s',
    );

	public function parse($url)
	{
        $matched = preg_match('/vid=(\w+)/s', $url, $matches);
        if (!empty($matched)) {
            $vid = $matches[1];
        } else {
            $response = $this->fetchUrl($url);
            if ($response['code'] != 200) {
                throw $this->createParseException('获取QQ视频页面信息失败！');
            }

            $matched = preg_match('/VIDEO_INFO.*?vid\s*:\s*"(\w+?)"/s', $response['content'], $matches);
            if (empty($matched)) {
                throw $this->createParseException("解析QQ视频ID失败！");
            }

            $vid = $matches[1];
        }

        $matched = preg_match($this->patterns['p1'], $url);
        if ($matched) {
            $url = 'http://sns.video.qq.com/tvideo/fcgi-bin/video?otype=json&vid=' . $vid;

            $response = $this->fetchUrl($url);
            if ($response['code'] != 200) {
                throw $this->createParseException('获取QQ视频信息失败！.');
            }

            $matched = preg_match('/{.*}/s', $response['content'], $matches);
            if (empty($matched)) {
                throw $this->createParseException('解析QQ视频信息失败！..');
            }

            $video = json_decode($matches[0], true) ? : array();
            if (empty($video) || empty($video['video'])) {
                throw $this->createParseException('解析QQ视频信息失败！...');
            }
            $video = $video['video'];

            $item = array(
                'type' => 'video',
                'source' => 'qqvideo',
                'uuid' => 'qqvideo:' . $vid,
                'name' => $video['title'],
                'summary' => $video['desc'],
                'duration' => $video['duration'],
                'page' => 'http://v.qq.com/cover/' . substr($video['cover'], 0, 1) . "/{$video['cover']}.html?vid={$vid}",
                'pictures' => array(
                    array('url' => "http://shp.qpic.cn/qqvideo/0/{$vid}/400")
                ),
                'files' => array(
                    array('type' => 'swf', 'url' => "http://static.video.qq.com/TPout.swf?vid={$vid}&auto=1"),
                    array('type' => 'mp4', 'url' => "http://video.store.qq.com/{$vid}.mp4"),
                ),
            );

        } else {

            $matched = preg_match('/VIDEO_INFO.*?title\s*:\s*"(.*?)"/s', $response['content'], $matches);

            if (empty($matched)) {
                throw $this->createParseException("解析QQ视频ID失败！....");
            }

            $title = $matches[1];

            $item = array(
                'type' => 'video',
                'source' => 'qqvideo',
                'uuid' => 'qqvideo:' . $vid,
                'name' => $title,
                'summary' => '',
                'duration' => '',
                'page' => $url,
                'pictures' => array(
                    array('url' => "http://shp.qpic.cn/qqvideo/0/{$vid}/400")
                ),
                'files' => array(
                    array('type' => 'swf', 'url' => "http://static.video.qq.com/TPout.swf?vid={$vid}&auto=1"),
                    array('type' => 'mp4', 'url' => "http://video.store.qq.com/{$vid}.mp4"),
                ),
            );
        }

        return $item;
	}

    public function detect($url)
    {
        $matched = preg_match($this->patterns['p1'], $url);
        if ($matched) {
            return true;
        }

        $matched = preg_match($this->patterns['p2'], $url);
        if ($matched) {
            return true;
        }

        $matched = preg_match($this->patterns['p3'], $url);
        if ($matched) {
            return true;
        }

        return false;
    }
}