<?php

namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;

/**
 * TriggerError command.
 */
class TriggerErrorCommand extends Command
{
    /**
     * Trigger error/exception
     *
     * {@inheritDoc}
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        trigger_error('Weak error in cli', E_USER_WARNING);
        throw new \RuntimeException('Exception in cli');
    }
}
