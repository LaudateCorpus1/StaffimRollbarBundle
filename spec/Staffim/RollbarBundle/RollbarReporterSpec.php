<?php

namespace spec\Staffim\RollbarBundle;

use Exception;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Rollbar\DataBuilder;
use Rollbar\ErrorWrapper;
use Rollbar\Payload\Level;
use Rollbar\RollbarLogger;
use Rollbar\Utilities;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Staffim\RollbarBundle\ReportDecisionManager;

class RollbarReporterSpec extends ObjectBehavior
{
    function let(
        RollbarLogger $rollbar,
        TokenStorageInterface $tokenStorage,
        ReportDecisionManager $reportDecisionManager
    ) {
        $this->beConstructedWith($rollbar, $tokenStorage, $reportDecisionManager);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Staffim\RollbarBundle\RollbarReporter');
    }

    function it_should_report_exception_when_decision_true(Exception $e, ReportDecisionManager $reportDecisionManager, RollbarLogger $rollbar)
    {
        $reportDecisionManager->decide($e)->willReturn(true);
        $rollbar->log(Level::ERROR, $e, [])->shouldBeCalled();
        $this->report($e);
    }

    function it_should_not_report_exception_when_decision_false(Exception $e, ReportDecisionManager $reportDecisionManager, RollbarLogger $rollbar)
    {
        $reportDecisionManager->decide($e)->willReturn(false);
        $rollbar->log(Level::ERROR, $e)->shouldNotBeCalled();
        $this->report($e);
    }

    function it_should_report_exception_with_extra_data(Exception $e, ReportDecisionManager $reportDecisionManager, RollbarLogger $rollbar)
    {
        $reportDecisionManager->decide($e)->willReturn(true);
        $extra = array('foo' => 'bar');
        $rollbar->log(Level::ERROR, $e, $extra)->shouldBeCalled();
        $this->report($e, null, $extra);
    }

    function it_should_report_error(ReportDecisionManager $reportDecisionManager, RollbarLogger $rollbar, DataBuilder $dataBuilder)
    {
        $reportDecisionManager->decide(Argument::type('ErrorException'))->willReturn(true);

        $message = 'Error';
        $file = __FILE__;
        $line = __LINE__;
        $wrapper = new ErrorWrapper(E_USER_NOTICE, $message, $file, $line, [], new Utilities());
        $dataBuilder->generateErrorWrapper(E_USER_NOTICE, $message, $file, $line)->willReturn($wrapper);

        $rollbar->getDataBuilder()->willReturn($dataBuilder);

        $rollbar->log(Level::ERROR, $wrapper)->shouldBeCalled();
        $this->reportError(E_USER_NOTICE, $message, $file, $line);
    }
}
