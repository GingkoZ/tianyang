<?php

namespace Topxia\Common;

use Symfony\Component\Finder\Finder;
use Topxia\Common\ExtensionalBundle;
use Symfony\Component\Yaml\Yaml;

class ExtensionManager
{
    protected $kernel;

    protected $bundles;

    protected $booted;

    protected $statusTemplates;

    protected $dataDict;

    protected $dataTagClassmap;

    protected $dataTags;

    private static $_instance;

    private function __construct($kernel)
    {
        $this->kernel = $kernel;
        $this->bundles = array(
            'DataTag' => array(),
            'StatusTemplate' => array(),
            'DataDict' => array(),
        );
        $this->booted = false;
        $this->statusTemplates = array();
        $this->dataDict = array();
        $this->dataTagClassmap = array();
        $this->dataTags = array();
    }

    public static function init($kernel)
    {
        if (self::$_instance) {
            return self::$_instance;
        }

        self::$_instance = new self($kernel);

        return self::$_instance;
    }

    public static function instance()
    {
        if (empty(self::$_instance)) {
            throw new \RuntimeException('ExtensionManager尚未实例化。');
        }
        return self::$_instance;
    }

    public function renderStatus($status, $mode)
    {
        $this->loadStatusTemplates();

        if (!isset($this->statusTemplates[$status['type']])) {
            return '无法显示该动态。';
        }

        return $this->kernel->getContainer()->get('templating')->render(
            $this->statusTemplates[$status['type']],
            array('status' => $status, 'mode' => $mode)
        );
    }

    public function getDataDict($type)
    {
        $this->loadDataDict();

        if (empty($this->dataDict[$type])) {
            return array();
        }

        return $this->dataDict[$type];
    }

    public function getDataTag($name)
    {
        if (isset($this->dataTags[$name])) {
            return $this->dataTags[$name];
        }

        $this->loadDataTagClassmap();



        if (!isset($this->dataTagClassmap[$name])) {
            throw new \RuntimeException("数据标签`{$name}`尚未定义。");
        }

        $class = $this->dataTagClassmap[$name];

        $this->dataTags[$name] = new $class();

        return $this->dataTags[$name];
    }

    private function boot()
    {
        if ($this->booted) {
            return ;
        }

        $this->getExtensionalBundles();
    }

    private function loadDataTagClassmap()
    {
        $this->boot();

        if (!empty($this->dataTagClassmap)) {
            return $this->dataTagClassmap;
        }

        $finder = new Finder();
        $finder->files()->name('*DataTag.php')->depth('== 0');

        $root = realpath($this->kernel->getContainer()->getParameter('kernel.root_dir') . '/../');

        $dirNamespaces = array();
        foreach($this->bundles['DataTag'] as $bundle) {
            $directory = $bundle->getPath() . '/Extensions/DataTag';
            if (!is_dir($directory)) {
                continue;
            }
            $dirNamespaces[$directory] = $bundle->getNamespace() . "\\Extensions\\DataTag";

            $finder->in($directory);
        }

        foreach ($finder as $file) {
            $name = $file->getBasename('DataTag.php');
            $this->dataTagClassmap[$name] = $dirNamespaces[$file->getPath()] . "\\{$name}DataTag";
        }

        return $this->dataTagClassmap;
    }

    private function loadDataDict()
    {
        $this->boot();

        if (!empty($this->dataDict)) {
            return $this->dataDict;
        }

        $files = array();
        foreach($this->bundles['DataDict'] as $bundle) {
            $file = $bundle->getPath() . '/Extensions/data_dict.yml';
            if (!file_exists($file)) {
                continue;
            }
            $this->dataDict = array_merge($this->dataDict, Yaml::parse(file_get_contents($file)));
        }

        return $this->dataDict;
    }

    private function loadStatusTemplates()
    {
        $this->boot();

        if (!empty($this->statusTemplates)) {
            return $this->statusTemplates;
        }

        $finder = new Finder();
        $finder->files()->name('*.tpl.html.twig')->depth('== 0');

        $root = realpath($this->kernel->getContainer()->getParameter('kernel.root_dir') . '/../');

        foreach($this->bundles['StatusTemplate'] as $bundle) {
            $directory = $bundle->getPath() . '/Extensions/StatusTemplate';
            if (!is_dir($directory)) {
                continue;
            }

            $finder->in($directory);
        }

        foreach ($finder as $file) {
            $type = $file->getBasename('.tpl.html.twig');
            $path = str_replace($root, '@root', $file->getRealPath());
            $this->statusTemplates[$type] = $path;
        }

        return $this->statusTemplates;
    }

    private function getExtensionalBundles()
    {
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!($bundle instanceof ExtensionalBundle)) {
                continue;
            }

            $enableds = $bundle->getEnabledExtensions();
            foreach (array_keys($this->bundles) as $enabled) {
                if (!in_array($enabled, $enableds)) {
                    continue;
                }
                $this->bundles[$enabled][] = $bundle;
            }
        }

        return $this->bundles;
    }

}