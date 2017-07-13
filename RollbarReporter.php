<?php

namespace Staffim\RollbarBundle;

use Exception;
use ErrorException;
use Rollbar\Payload\Level;
use Rollbar\RollbarLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RollbarReporter
{
    /**
     * @var RollbarLogger
     */
    private $rollbar;

    /**
     * @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var \Staffim\RollbarBundle\ReportDecisionManager
     */
    private $reportDecisionManager;

    /**
     * @var int
     */
    private $errorLevel;

    /**
     * @var array
     */
    private $scrubExceptions;

    /**
     * @var array
     */
    private $scrubParameters;

    /**
     * Constructor.
     *
     * @param RollbarLogger $rollbar
     * @param \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage
     * @param \Staffim\RollbarBundle\ReportDecisionManager $reportDecisionManager
     * @param type $errorLevel
     * @param array $scrubExceptions
     * @param array $scrubParameters
     */
    public function __construct(
        RollbarLogger $rollbar,
        TokenStorageInterface $tokenStorage,
        ReportDecisionManager $reportDecisionManager,
        $errorLevel = -1,
        array $scrubExceptions = array(),
        array $scrubParameters = array()
    ) {
        $this->rollbar = $rollbar;
        $this->tokenStorage = $tokenStorage;
        $this->reportDecisionManager = $reportDecisionManager;
        $this->scrubExceptions = $scrubExceptions;
        $this->scrubParameters = $scrubParameters;
        $this->errorLevel = $errorLevel;

        $this->rollbar->person_fn = array($this, 'getUserData');
    }

    /**
     * Report an exception to the rollbar.
     *
     * @param \Exception $exception
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param array $extraData
     * @return string UUID of rollbar item
     */
    public function report(Exception $exception, Request $request = null, $extraData = [])
    {
        if (false === $this->reportDecisionManager->decide($exception)) {
            return;
        }

        if (in_array(get_class($exception), $this->scrubExceptions)) {
            $exception = $this->scrubException($exception);
        }

        $this->prepareGlobalServer($request);
        $result = $this->rollbar->log(Level::ERROR, $exception, $extraData);
        $this->cleanGlobalServer();

        return $result;
    }

    /**
     * Report an error to the rollbar.
     *
     * @param int $level
     * @param string $message
     * @param string $file
     * @param int $line
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return bool
     */
    public function reportError($level, $message, $file, $line, Request $request = null)
    {
        if (error_reporting() & $level &&
            $this->errorLevel & $level &&
            true === $this->reportDecisionManager->decide(new ErrorException($message, 0, $level, $file, $line))
        ) {
            $this->prepareGlobalServer($request);
            $this->rollbar->log(Level::ERROR, $this->rollbar->getDataBuilder()->generateErrorWrapper($level, $message, $file, $line));

            $this->cleanGlobalServer();
        }
    }

    /**
     * Flush the rollbar Notifier.
     *
     * Do nothing silently to not cause backwards compatibility issues.
     * Deprecated in rollbar 1.0.0.  See Rollbar::flush
     */
    public function flush()
    {

    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request|null $request
     */
    private function prepareGlobalServer(Request $request = null)
    {
        if ($request) {
            $_SERVER['HTTP_REQUEST_CONTENT'] = $request->getContent();
        }
    }

    private function cleanGlobalServer()
    {
        unset($_SERVER['HTTP_REQUEST_CONTENT']);
    }

    /**
     * Scrub Exception.
     *
     * @return Exception
     */
    private function scrubException($originalException)
    {
        $exception = FlattenException::create($originalException);

        $trace = $exception->getTrace();
        foreach ($trace as $key => $item) {
            array_walk_recursive($item['args'], function (&$value, $key, $params) {
                if (is_string($value) && $key = array_search($value, $params)) {
                    $value = '%' . $key . '%';
                }
            }, $this->scrubParameters);

            $trace[$key] = $item;
        }

        $exception->setTrace($trace, $exception->getFile(), $exception->getLine());

        return $exception;
    }

    /**
     * Get current user info.
     *
     * @return null|array
     */
    public function getUserData()
    {
        if ($token = $this->tokenStorage->getToken()) {
            $userData = array();
            $user = $token->getUser();
            if (!$user || !is_object($user)) {
                return null;
            }
            if (method_exists($user, 'getId')) {
                $userData['id'] = $user->getId();
            } else {
                // id is required
                $userData['id'] = $user->getUsername();
            }
            $userData['username'] = $user->getUsername();
            if (method_exists($user, 'getEmail')) {
                $userData['email'] = $user->getEmail();
            }

            return $userData;
        }

        return null;
    }
}
