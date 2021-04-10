<?php
declare(strict_types=1);

namespace TestApp\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

/**
 * A Command for checking the behavior of the CLI when an exception occurs
 */
class ExceptionCommand extends Command
{
    /**
     * {@inheritDoc}
     *
     * Raising an exception
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->throwBadMethodCallException('some exception');
    }

    /**
     * @param string $message exception message
     * @return never-return
     */
    private function throwBadMethodCallException(string $message)
    {
        throw new \BadMethodCallException($message);
    }
}
