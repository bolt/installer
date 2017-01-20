<?php

namespace Bolt\Installer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command provides information about the Bolt installer.
 *
 * Based on Symfony Installer (c) Fabien Potencier <fabien@symfony.com>
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class AboutCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('about')
            ->setDescription('Bolt Installer Help.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $commandHelp = <<<COMMAND_HELP

 Bolt Installer (%s)
 %s

 This is the official installer to start new projects based on the
 Bolt CMS framework.

 To create a new project called <info>blog</info> in the current directory using
 the <info>latest stable version</info> of Bolt, execute the following command:

   <comment>%s new blog</comment>

 Create a project based on a <info>specific Bolt branch</info>:

   <comment>%3\$s new blog 3.3</comment> or <comment>%3\$s new blog 3.4</comment>

 Create a project based on a <info>specific Bolt version</info>:

   <comment>%3\$s new blog 3.2.1</comment> or <comment>%3\$s new blog 3.2.4</comment>

 Create a <info>demo application</info> to learn how a Bolt application works:

   <comment>%3\$s demo</comment>

COMMAND_HELP;

        // show the self-update information only when using the PHAR file
        if ('phar://' === substr(__DIR__, 0, 7)) {
            $commandUpdateHelp = <<<COMMAND_UPDATE_HELP

 Updating the Bolt Installer
 ------------------------------

 New versions of the Bolt Installer are released regularly. To <info>update your
 installer</info> version, execute the following command:

   <comment>%3\$s self-update</comment>

COMMAND_UPDATE_HELP;

            $commandHelp .= $commandUpdateHelp;
        }

        $output->writeln(sprintf($commandHelp,
            $this->$this->getApplication()->getVersion(),
            str_repeat('=', 20 + strlen($this->getApplication()->getVersion())),
            $this->getExecutedCommand()
        ));
    }

    /**
     * Returns the executed command.
     *
     * @return string The executed command
     */
    private function getExecutedCommand()
    {
        $pathDirs = explode(PATH_SEPARATOR, $_SERVER['PATH']);
        $executedCommand = $_SERVER['PHP_SELF'];
        $executedCommandDir = dirname($executedCommand);

        if (in_array($executedCommandDir, $pathDirs)) {
            $executedCommand = basename($executedCommand);
        }

        return $executedCommand;
    }
}
