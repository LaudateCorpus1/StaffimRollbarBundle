<?php

namespace Staffim\DTOBundle\Tests;

use Rollbar\RollbarLogger;
use Staffim\RollbarBundle\EventListener\RollbarListener;
use Staffim\RollbarBundle\ReportDecisionManager;
use Staffim\RollbarBundle\RollbarReporter;
use Staffim\RollbarBundle\Voter\HttpExceptionVoter;
use Staffim\RollbarBundle\Voter\SameRefererVoter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContainerTest extends KernelTestCase
{
    /**
     * @dataProvider serviceDataProvider
     */
    public function testContainerHasService($serviceId, $class)
    {
        $this->assertTrue($this->getContainer()->has($serviceId));
        $this->assertInstanceOf($class, $this->getContainer()->get($serviceId));
    }

    private function getContainer()
    {
        return static::$kernel->getContainer();
    }

    protected function setUp()
    {
        $this->bootKernel();
    }

    public static function serviceDataProvider()
    {
        return [
            ['staffim_rollbar.rollbar_listener', RollbarListener::class],
            ['staffim_rollbar.rollbar_reporter', RollbarReporter::class],
            ['staffim_rollbar.rollbar', RollbarLogger::class],
            ['staffim_rollbar.report_decision_manager', ReportDecisionManager::class],
            ['staffim_rollbar.http_exception_voter', HttpExceptionVoter::class],
            ['staffim_rollbar.same_referer_voter', SameRefererVoter::class],
        ];
    }
}
