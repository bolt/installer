<?php

namespace Bolt\Installer;

use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * Bolt Installer console application.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Application extends ConsoleApplication
{
    private $version = '0.2.0-DEV';

    public function __construct($name = 'UNKNOWN', $version = null)
    {
        parent::__construct($name, $version ?: $this->version);
    }
}
