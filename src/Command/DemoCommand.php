<?php

namespace Bolt\Installer\Command;

use Bolt\Installer\Exception\AbortException;
use Bolt\Installer\Manager\ComposerManager;
use Bolt\Installer\Urls;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command creates a full-featured Bolt demo application.
 *
 * Based on Symfony Installer (c) Fabien Potencier <fabien@symfony.com>
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class DemoCommand extends DownloadCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('demo')
            ->addArgument('directory', InputArgument::OPTIONAL, 'Directory where the new project will be created.')
            ->setDescription('Creates a demo Bolt project.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->version = 'lts';

        if (!$input->getArgument('directory')) {
            $this->projectDir = getcwd();

            $i = 1;
            $projectName = 'bolt_demo';
            while (file_exists($this->projectDir . DIRECTORY_SEPARATOR . $projectName)) {
                $projectName = 'bolt_demo_' . (++$i);
            }

            $this->projectName = $projectName;
            $this->projectDir = $this->projectDir . DIRECTORY_SEPARATOR . $projectName;
        } else {
            $directory = rtrim(trim($input->getArgument('directory')), DIRECTORY_SEPARATOR);
            $this->projectDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd() . DIRECTORY_SEPARATOR . $directory;
            $this->projectName = basename($directory);
        }

        $this->composerManager = new ComposerManager($this->projectDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this
                ->checkInstallerVersion()
                ->checkProjectName()
                ->checkPermissions()
                ->download()
                ->extract()
                ->cleanUp()
                ->updateComposerConfig()
                ->createGitIgnore()
                ->checkBoltRequirements()
                ->displayInstallationResult()
            ;
        } catch (AbortException $e) {
            aborted:

            $output->writeln('');
            $output->writeln('<error>Aborting download and cleaning up temporary directories.</>');

            $this->cleanUp();

            return 1;
        } catch (\Exception $e) {
            // Guzzle can wrap the AbortException in a GuzzleException
            if ($e->getPrevious() instanceof AbortException) {
                goto aborted;
            }

            $this->cleanUp();
            throw $e;
        }
    }

    /**
     * Removes all the temporary files and directories created to
     * download the demo application.
     *
     * @return $this
     */
    private function cleanUp()
    {
        $this->fs->remove(dirname($this->downloadedFilePath));

        return $this;
    }

    /**
     * It displays the message with the result of installing the Bolt Demo
     * application and provides some pointers to the user.
     *
     * @return $this
     */
    private function displayInstallationResult()
    {
        if (empty($this->requirementsErrors)) {
            $this->output->writeln(sprintf(
                " <info>%s</info>  Bolt Demo Application was <info>successfully installed</info>. Now you can:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔'
            ));
        } else {
            $this->output->writeln(sprintf(
                " <comment>%s</comment>  Bolt Demo Application was <info>successfully installed</info> but your system doesn't meet the\n" .
                "     technical requirements to run Bolt applications! Fix the following issues before executing it:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'FAILED' : '✕'
            ));

            foreach ($this->requirementsErrors as $helpText) {
                $this->output->writeln(' * ' . $helpText);
            }

            $this->output->writeln(sprintf(
                " After fixing these issues, re-check Bolt requirements executing this command:\n\n" .
                "   <comment>php %s/bin/bolt_requirements</comment>\n\n" .
                " Then, you can:\n",
                $this->projectName
            ));
        }

        $serverRunCommand = extension_loaded('pcntl') ? 'server:start' : 'server:run';

        $this->output->writeln(sprintf(
            "    1. Change your current directory to <comment>%s</comment>\n\n" .
            "    2. Execute the <comment>php app/nut %s</comment> command to run the demo application.\n\n" .
            "    3. Browse to the <comment>http://0.0.0.0:8000</comment> URL to see the demo application in action.\n\n",
            $this->projectDir, $serverRunCommand
        ));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDownloadedApplicationType()
    {
        return 'the Bolt Demo Application';
    }

    /**
     * {@inheritdoc}
     */
    protected function getRemoteFileUrl()
    {
        return Urls::REMOTE_DEMO_FILE;
    }
}
