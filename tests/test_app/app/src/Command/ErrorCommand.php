<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

/**
 * A Command for checking the behavior of the CLI when errors occurs
 */
class ErrorCommand extends Command
{
    /**
     * {@inheritDoc}
     *
     * Trigger errors
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        triggerWarning('with triggerWarning');

        $errorLevels = [
            E_USER_NOTICE => 'notice!!',
            E_USER_DEPRECATED => 'deprecated!!',
            E_USER_WARNING => 'warning!!',
            E_USER_ERROR => 'error!!',
        ];

        foreach ($errorLevels as $errorLevel => $errorMessage) {
            $this->triggerError($errorLevel, $errorMessage);
        }
    }

    /**
     * @param int $level user error level
     * @param string $message error message
     * @return void
     */
    private function triggerError(int $level, string $message)
    {
        trigger_error($message, $level);
    }
}
