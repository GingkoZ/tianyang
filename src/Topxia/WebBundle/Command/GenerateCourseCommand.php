<?php
namespace Topxia\WebBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Topxia\Service\User\CurrentUser;
use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Input\InputArgument;
use Topxia\Service\Common\ServiceKernel;

class GenerateCourseCommand extends BaseCommand
{

    protected function configure()
    {
        $this->setName ( 'generate:course' )
        ->addArgument('count',InputArgument::OPTIONAL)
        ->addArgument('price', InputArgument::OPTIONAL)
        ->setDescription('第一个参数为创建课程数量(默认为50),第二个参数为价格(默认为随即)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>开始初始化课程数据</info>');
        $this->initServiceKernel();
        $count = $input->getArgument('count', 50);
        $this->getCourseDao()->getConnection()->beginTransaction();
        try {
            for ($i=0; $i < $count; $i++) {
                $price = $input->getArgument('price', raund(0, 100));
                $course['title'] = '课程-'.$price.'元-'.time().'-'.$i;
                $course['status'] = 'published';
                $course['about'] = '';
                $course['userId'] = 1;
                $course['createdTime'] = time();
                $course['teacherIds'] = '|1|';
                $course['price'] = $price;
                $course['originPrice'] = $price;
                $course = $this->getCourseDao()->addCourse($course);

                $member = array(
                    'courseId' => $course['id'],
                    'userId' => $course['userId'],
                    'role' => 'teacher',
                    'createdTime' => time(),
                );

                $this->getMemberDao()->addMember($member);
                unset($course);
                $output->writeln('<info>第'.($i+1).'个课程添加</info>');
            }
            $this->getCourseDao()->getConnection()->commit();
        } catch (\Exception $e){
            $this->getCourseDao()->getConnection()->rollback();
            throw $e;
        }
        $output->writeln('<info>初始化课程数据完毕</info>');
    }

    private function initServiceKernel()
    {
        $serviceKernel = ServiceKernel::create('dev', false);
        $serviceKernel->setParameterBag($this->getContainer()->getParameterBag());

        $serviceKernel->setConnection($this->getContainer()->get('database_connection'));
        $user = $this->getUserService()->getUser(1);
        $serviceKernel->setCurrentUser($user);
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }

    private function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    private function getCourseDao ()
    {
        return $this->getServiceKernel()->createDao('Course.CourseDao');
    }

    private function getMemberDao ()
    {
        return $this->getServiceKernel()->createDao('Course.CourseMemberDao');
    }
}