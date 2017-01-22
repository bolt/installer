<?php

namespace Bolt\Installer\Command;

use Bolt\Installer\Application;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;

/**
 * Base command.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class BaseCommand extends Command
{
    /** @var Application */
    private $application;

    /**
     * @return Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @param ConsoleApplication|null $application
     */
    public function setApplication(ConsoleApplication $application = null)
    {
        $this->application = $application;
        parent::setApplication($application);
    }
}
