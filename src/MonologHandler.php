<?php

namespace Airbrake;

use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Monolog handler that sends logs to Airbrake.
 */
class MonologHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    /**
     * @var Notifier
     */
    private $notifier;

    /**
     * @param Notifier $notifier Notifier instance
     * @param int  $level    Level above which entries should be logged
     * @param bool  $bubble   Whether to bubble to the next handler or not
     */
    public function __construct(\Airbrake\Notifier $notifier, $level = Logger::ERROR, $bubble = true)
    {
        $this->notifier = $notifier;
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(LogRecord $record): void
    {
        $recordArray = $record->toArray();
        if (isset($recordArray['context']['exception'])) {
            $exc = $recordArray['context']['exception'];
            $trace = $exc->getTrace();
        } else {
            $trace = array_slice(debug_backtrace(), 3);
            $exc = new Errors\Base($recordArray['message'], $trace);
        }

        $notice = $this->notifier->buildNotice($exc);
        $notice['errors'][0]['type'] = $recordArray['channel'].'.'.$recordArray['level_name'];
        $notice['context']['severity'] = $recordArray['level_name'];
        if (!empty($recordArray['context'])) {
            $notice['params']['monolog_context'] = $recordArray['context'];
        }
        if (!empty($recordArray['extra'])) {
            $notice['params']['monolog_extra'] = $recordArray['extra'];
        }

        $this->notifier->sendNotice($notice);
    }
}
