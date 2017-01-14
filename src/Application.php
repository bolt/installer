<?php

namespace Bolt\Installer;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bolt Installer console application.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Application extends ConsoleApplication
{
    const VERSIONS_URL = 'https://get.bolt.cm/installer.version';

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        return parent::doRun($input, $output);
    }
}
