<?php
namespace Topxia\WebBundle\Command;

use Topxia\System;
use Topxia\Common\BlockToolkit;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('topxia:build');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Start build.</info>');
        $this->initBuild($input, $output);
        $this->buildRootDirectory();
        $this->buildApiDirectory();
        $this->buildAppDirectory();
        $this->buildDocDirectory();
        $this->buildSrcDirectory();
        $this->buildVendorDirectory();
        $this->buildVendorUserDirectory();
        $this->buildWebDirectory();
        $this->buildPluginsDirectory();
        $this->buildFixPdoSession();
        $this->buildDefaultBlocks();
        $this->cleanMacosDirectory();

        $this->package();

        $this->clean();

        $output->writeln('<info>End build.</info>');

        // $filesystem->mirror("{$rootDirectory}/{$directory}", "{$targetDirectory}/{$directory}");
    }

    private function initBuild(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        $this->rootDirectory  = realpath($this->getContainer()->getParameter('kernel.root_dir').'/../');
        $this->buildDirectory = $this->rootDirectory.'/build';

        $this->filesystem = new Filesystem();

        if ($this->filesystem->exists($this->buildDirectory)) {
            $this->filesystem->remove($this->buildDirectory);
        }

        $this->distDirectory = $this->buildDirectory.'/edusoho';
        $this->filesystem->mkdir($this->distDirectory);
    }

    private function package()
    {
        $this->output->writeln('packaging...');

        chdir($this->buildDirectory);

        $command = "tar czvf edusoho-".System::VERSION.".tar.gz edusoho/";
        exec($command);
    }

    private function clean()
    {
        $this->output->writeln('cleaning...');
    }

    private function buildRootDirectory()
    {
        $this->output->writeln('build / .');
        $this->filesystem->copy("{$this->rootDirectory}/README.html", "{$this->distDirectory}/README.html");
    }

    private function buildApiDirectory()
    {
        $this->output->writeln('build api/ .');
        $this->filesystem->mkdir("{$this->distDirectory}/api");
        $this->filesystem->mirror("{$this->rootDirectory}/api", "{$this->distDirectory}/api");
    }

    private function buildAppDirectory()
    {
        $this->output->writeln('build app/ .');

        $this->filesystem->mkdir("{$this->distDirectory}/app");
        $this->filesystem->mkdir("{$this->distDirectory}/app/cache");
        $this->filesystem->mkdir("{$this->distDirectory}/app/data");
        $this->filesystem->mkdir("{$this->distDirectory}/app/data/udisk");
        $this->filesystem->mkdir("{$this->distDirectory}/app/data/private_files");
        $this->filesystem->mkdir("{$this->distDirectory}/app/data/upgrade");
        $this->filesystem->mkdir("{$this->distDirectory}/app/data/backup");
        $this->filesystem->mkdir("{$this->distDirectory}/app/logs");
        $this->filesystem->mirror("{$this->rootDirectory}/app/Resources", "{$this->distDirectory}/app/Resources");
        $this->filesystem->mirror("{$this->rootDirectory}/app/config", "{$this->distDirectory}/app/config");

        $this->filesystem->chmod("{$this->distDirectory}/app/cache", 0777);
        $this->filesystem->chmod("{$this->distDirectory}/app/data", 0777);
        $this->filesystem->chmod("{$this->distDirectory}/app/data/udisk", 0777);
        $this->filesystem->chmod("{$this->distDirectory}/app/data/private_files", 0777);
        $this->filesystem->chmod("{$this->distDirectory}/app/data/upgrade", 0777);
        $this->filesystem->chmod("{$this->distDirectory}/app/data/backup", 0777);
        $this->filesystem->chmod("{$this->distDirectory}/app/logs", 0777);

        // $this->filesystem->remove("{$this->distDirectory}/app/config/config_dev.yml");
        // $this->filesystem->remove("{$this->distDirectory}/app/config/config_test.yml");
        // $this->filesystem->remove("{$this->distDirectory}/app/config/routing_dev.yml");
        $this->filesystem->remove("{$this->distDirectory}/app/config/routing_plugins.yml");
        $this->filesystem->touch("{$this->distDirectory}/app/config/routing_plugins.yml");
        $this->filesystem->remove("{$this->distDirectory}/app/config/parameters.yml");
        $this->filesystem->remove("{$this->distDirectory}/app/config/uc_client_config.php");
        $this->filesystem->remove("{$this->distDirectory}/app/config/windid_client_config.php");

        $this->filesystem->copy("{$this->distDirectory}/app/config/parameters.yml.dist", "{$this->distDirectory}/app/config/parameters.yml");
        $this->filesystem->chmod("{$this->distDirectory}/app/config/parameters.yml", 0777);

        $this->filesystem->copy("{$this->distDirectory}/app/config/uc_client_config.php.dist", "{$this->distDirectory}/app/config/uc_client_config.php");
        $this->filesystem->chmod("{$this->distDirectory}/app/config/uc_client_config.php", 0777);

        $this->filesystem->copy("{$this->distDirectory}/app/config/windid_client_config.php.dist", "{$this->distDirectory}/app/config/windid_client_config.php");
        $this->filesystem->chmod("{$this->distDirectory}/app/config/windid_client_config.php", 0777);

        $this->filesystem->remove("{$this->distDirectory}/app/config/parameters.yml.dist");
        $this->filesystem->remove("{$this->distDirectory}/app/config/uc_client_config.php.dist");
        $this->filesystem->remove("{$this->distDirectory}/app/config/windid_client_config.php.dist");

        $this->filesystem->copy("{$this->rootDirectory}/app/console", "{$this->distDirectory}/app/console");
        $this->filesystem->copy("{$this->rootDirectory}/app/AppCache.php", "{$this->distDirectory}/app/AppCache.php");
        $this->filesystem->copy("{$this->rootDirectory}/app/AppKernel.php", "{$this->distDirectory}/app/AppKernel.php");
        $this->filesystem->copy("{$this->rootDirectory}/app/autoload.php", "{$this->distDirectory}/app/autoload.php");
        $this->filesystem->copy("{$this->rootDirectory}/app/bootstrap.php.cache", "{$this->distDirectory}/app/bootstrap.php.cache");
    }

    public function buildDocDirectory()
    {
        $this->output->writeln('build doc/ .');

        $this->filesystem->mkdir("{$this->distDirectory}/doc");
        // $this->filesystem->copy("{$this->rootDirectory}/doc/development/INSTALL.md", "{$this->distDirectory}/doc/INSTALL.md", true);
        // $this->filesystem->copy("{$this->rootDirectory}/doc/apache_server_config.txt", "{$this->distDirectory}/doc/apache_server_config.txt", true);
        // $this->filesystem->copy("{$this->rootDirectory}/doc/nginx_server_config.txt", "{$this->distDirectory}/doc/nginx_server_config.txt", true);
    }

    public function buildPluginsDirectory()
    {
        $this->output->writeln('build plugins/ .');
        $this->filesystem->mkdir("{$this->distDirectory}/plugins");
    }

    public function buildSrcDirectory()
    {
        $this->output->writeln('build src/ .');
        $this->filesystem->mirror("{$this->rootDirectory}/src", "{$this->distDirectory}/src");

        $this->filesystem->remove("{$this->distDirectory}/src/Topxia/AdminBundle/Resources/public");
        $this->filesystem->remove("{$this->distDirectory}/src/Topxia/WebBundle/Resources/public");
        $this->filesystem->remove("{$this->distDirectory}/src/Topxia/MobileBundle/Resources/public");
        $this->filesystem->remove("{$this->distDirectory}/src/Custom/AdminBundle/Resources/public");
        $this->filesystem->remove("{$this->distDirectory}/src/Custom/WebBundle/Resources/public");

        $this->filesystem->remove("{$this->distDirectory}/src/Topxia/WebBundle/Command");
        $this->filesystem->mkdir("{$this->distDirectory}/src/Topxia/WebBundle/Command");

        $this->filesystem->mirror("{$this->rootDirectory}/src/Topxia/WebBundle/Command/plugins-tpl", "{$this->distDirectory}/src/Topxia/WebBundle/Command/plugins-tpl");
        $this->filesystem->mirror("{$this->rootDirectory}/src/Topxia/WebBundle/Command/Templates", "{$this->distDirectory}/src/Topxia/WebBundle/Command/Templates");

        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/BaseCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/BaseCommand.php");
        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/BuildPluginAppCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/BuildPluginAppCommand.php");
        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/BuildThemeAppCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/BuildThemeAppCommand.php");
        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/PluginRegisterCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/PluginRegisterCommand.php");
        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/PluginCreateCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/PluginCreateCommand.php");
        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/PluginRefreshCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/PluginRefreshCommand.php");
        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/ThemeRegisterCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/ThemeRegisterCommand.php");
        $this->filesystem->copy("{$this->rootDirectory}/src/Topxia/WebBundle/Command/ResetPasswordCommand.php", "{$this->distDirectory}/src/Topxia/WebBundle/Command/ResetPasswordCommand.php");

        $finder = new Finder();
        $finder->directories()->in("{$this->distDirectory}/src/");

        $toDeletes = array();

        foreach ($finder as $dir) {
            if ($dir->getFilename() == 'Tests') {
                $toDeletes[] = $dir->getRealpath();
            }
        }

        foreach ($toDeletes as $file) {
            $this->filesystem->remove($file);
        }
    }

    public function buildVendorDirectory()
    {
        $this->output->writeln('build vendor2/ .');
        $this->filesystem->mkdir("{$this->distDirectory}/vendor2");
        $this->filesystem->copy("{$this->rootDirectory}/vendor2/autoload.php", "{$this->distDirectory}/vendor2/autoload.php");

        $directories = array(
            'composer',
            'silex',
            'doctrine/annotations/lib',
            'doctrine/cache/lib',
            'doctrine/collections/lib',
            'doctrine/common/lib/Doctrine',
            'doctrine/dbal/lib/Doctrine',
            'doctrine/doctrine-bundle',
            'doctrine/doctrine-cache-bundle',
            'doctrine/doctrine-migrations-bundle',
            'doctrine/doctrine-cache-bundle',
            'doctrine/inflector/lib',
            'doctrine/lexer/lib',
            'doctrine/migrations/lib',
            'doctrine/orm/lib',
            'ezyang/htmlpurifier/library',
            'gregwar/captcha',
            'imagine/imagine/lib',
            'jdorn/sql-formatter/lib',
            'kriswallsmith/assetic/src',
            'monolog/monolog/src',
            'phpoffice/phpexcel/Classes',
            'psr/log/Psr',
            'sensio/distribution-bundle',
            'sensio/framework-extra-bundle',
            'sensio/generator-bundle',
            'swiftmailer/swiftmailer/lib',
            'symfony/assetic-bundle',
            //'symfony/icu',
            'symfony/monolog-bundle',
            'symfony/swiftmailer-bundle',
            'symfony/symfony/src',
            'twig/twig/lib',
            'twig/extensions/lib',
            'endroid/qrcode/src',
            'endroid/qrcode/assets'
        );

        foreach ($directories as $dir) {
            $this->filesystem->mirror("{$this->rootDirectory}/vendor2/{$dir}", "{$this->distDirectory}/vendor2/{$dir}");
        }

        $this->filesystem->remove("{$this->distDirectory}/vendor2/composer/installed.json");

        $finder = new Finder();
        $finder->directories()->in("{$this->distDirectory}/vendor2");

        $toDeletes = array();

        foreach ($finder as $dir) {
            if ($dir->getFilename() == 'Tests') {
                $toDeletes[] = $dir->getRealpath();
            }
        }

        $this->filesystem->remove($toDeletes);

        //$this->cleanIcuVendor();
    }

    private function cleanIcuVendor()
    {
        $icuBase    = "{$this->distDirectory}/vendor2/symfony/icu/Symfony/Component/Icu/Resources/data";
        $whileFiles = array(
            'svn-info.txt',
            'version.txt',
            'curr/en.res',
            'curr/zh.res',
            'curr/zh_CN.res',
            'lang/en.res',
            'lang/zh.res',
            'lang/zh_CN.res',
            'locales/en.res',
            'locales/zh.res',
            'locales/zh_CN.res',
            'region/en.res',
            'region/zh.res',
            'region/zh_CN.res'
        );

        $finder = new Finder();
        $finder->files()->in($icuBase);

        foreach ($finder as $file) {
            if (!in_array($file->getRelativePathname(), $whileFiles)) {
                $this->filesystem->remove($file->getRealpath());
            }
        }
    }

    public function buildVendorUserDirectory()
    {
        $this->output->writeln('build vendor_user/ .');
        $this->filesystem->mirror("{$this->rootDirectory}/vendor_user", "{$this->distDirectory}/vendor_user");
    }

    public function buildWebDirectory()
    {
        $this->output->writeln('build web/ .');

        $this->filesystem->mkdir("{$this->distDirectory}/web");
        $this->filesystem->mkdir("{$this->distDirectory}/web/files");
        $this->filesystem->mkdir("{$this->distDirectory}/web/bundles");
        $this->filesystem->mkdir("{$this->distDirectory}/web/themes");
        $this->filesystem->mirror("{$this->rootDirectory}/web/assets", "{$this->distDirectory}/web/assets");
        $this->filesystem->mirror("{$this->rootDirectory}/web/customize", "{$this->distDirectory}/web/customize");
        $this->filesystem->mirror("{$this->rootDirectory}/web/install", "{$this->distDirectory}/web/install");
        $this->filesystem->mirror("{$this->rootDirectory}/web/themes/autumn", "{$this->distDirectory}/web/themes/autumn");
        $this->filesystem->mirror("{$this->rootDirectory}/web/themes/default", "{$this->distDirectory}/web/themes/default");
        $this->filesystem->mirror("{$this->rootDirectory}/web/themes/jianmo", "{$this->distDirectory}/web/themes/jianmo");
        $this->filesystem->mirror("{$this->rootDirectory}/web/themes/default-b", "{$this->distDirectory}/web/themes/default-b");
        $this->filesystem->copy("{$this->rootDirectory}/web/themes/block.json", "{$this->distDirectory}/web/themes/block.json");

        $this->filesystem->copy("{$this->rootDirectory}/web/.htaccess", "{$this->distDirectory}/web/.htaccess");
        $this->filesystem->copy("{$this->rootDirectory}/web/app.php", "{$this->distDirectory}/web/app.php");
        $this->filesystem->copy("{$this->rootDirectory}/web/app_dev.php", "{$this->distDirectory}/web/app_dev.php");
        $this->filesystem->copy("{$this->rootDirectory}/web/favicon.ico", "{$this->distDirectory}/web/favicon.ico");
        $this->filesystem->copy("{$this->rootDirectory}/web/robots.txt", "{$this->distDirectory}/web/robots.txt");
        $this->filesystem->copy("{$this->rootDirectory}/web/crossdomain.xml", "{$this->distDirectory}/web/crossdomain.xml");

        $this->filesystem->chmod("{$this->distDirectory}/web/files", 0777);

        $finder = new Finder();
        $finder->files()->in("{$this->distDirectory}/web/assets/libs");

        foreach ($finder as $file) {
            $filename = $file->getFilename();

            if ($filename == 'package.json' || preg_match('/-debug.js$/', $filename) || preg_match('/-debug.css$/', $filename)) {
                $this->filesystem->remove($file->getRealpath());
            }
        }

        $finder = new Finder();
        $finder->directories()->in("{$this->rootDirectory}/web/bundles")->depth('== 0');
        $needs = array('sensiodistribution', 'topxiaadmin', 'framework', 'topxiaweb', 'customweb', 'customadmin', 'topxiamobilebundlev2', 'classroom');

        foreach ($finder as $dir) {
            if (!in_array($dir->getFilename(), $needs)) {
                continue;
            }

            $this->filesystem->mirror($dir->getRealpath(), "{$this->distDirectory}/web/bundles/{$dir->getFilename()}");
        }
    }

    public function buildFixPdoSession()
    {
        $this->output->writeln('build fix PdoSessionHandler .');

        $targetPath = "{$this->distDirectory}/vendor2/symfony/symfony/src/Symfony/Component/HttpFoundation/Session/Storage/Handler/PdoSessionHandler.php";
        $sourcePath = __DIR__."/Fixtures/PdoSessionHandler.php";
        $this->filesystem->copy($sourcePath, $targetPath, true);
    }

    public function buildDefaultBlocks()
    {
        $this->output->writeln('build default blocks .');

        $themeDir = realpath(__DIR__.'/../../../../web/themes/');

        $html = $this->generateBlcokContent("{$themeDir}/block.json");
        $this->generateBlcokContent("{$themeDir}/default/block.json");
        $this->generateBlcokContent("{$themeDir}/autumn/block.json");
        $this->generateBlcokContent("{$themeDir}/jianmo/block.json");
    }

    private function generateBlcokContent($metaFilePath)
    {
        $metas = file_get_contents($metaFilePath);
        $metas = json_decode($metas, true);

        if (empty($metas)) {
            throw new \RuntimeException("插件元信息文件{$metaFilePath}格式不符合JSON规范，解析失败，请检查元信息文件格式");
        }

        foreach ($metas as $code => $meta) {
            $data = array();

            foreach ($meta['items'] as $key => $item) {
                $data[$key] = $item['default'];
            }

            $block = array('templateName' => $meta['templateName'], 'data' => $data);
            $html  = BlockToolkit::render($block, $this->getContainer());

            $filename = "block-".md5($code).'.html';
            $folder   = "{$this->distDirectory}/web/install/blocks/";

            if (!file_exists($folder)) {
                mkdir($folder);
            }

            $filename = $folder.$filename;

            file_put_contents($filename, $html);
        }
    }

    public function cleanMacosDirectory()
    {
        $finder = new Finder();
        $finder->files()->in($this->distDirectory)->ignoreDotFiles(false);

        foreach ($finder as $dir) {
            if ($dir->getBasename() == '.DS_Store') {
                $this->filesystem->remove($dir->getRealpath());
            }
        }
    }
}
