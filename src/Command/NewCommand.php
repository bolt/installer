<?php

namespace Bolt\Installer\Command;

use Bolt\Installer\Exception\AbortException;
use Bolt\Installer\Manager\ComposerManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command creates new Bolt projects for the given Bolt version.
 *
 * Based on Symfony Installer (c) Fabien Potencier <fabien@symfony.com>
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class NewCommand extends DownloadCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new Bolt project.')
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory where the new project will be created.')
            ->addArgument('version', InputArgument::OPTIONAL, 'The Bolt version to be installed (defaults to the latest stable version).', 'latest')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $directory = rtrim(trim($input->getArgument('directory')), DIRECTORY_SEPARATOR);
        $this->version = trim($input->getArgument('version'));
        $this->projectDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd() . DIRECTORY_SEPARATOR . $directory;
        $this->projectName = basename($directory);

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
                ->dumpReadmeFile()
                ->updateParameters()
                ->updateComposerConfig()
                ->createGitIgnore()
                ->checkBoltRequirements()
                ->displayInstallationResult()
            ;
        } catch (AbortException $e) {
            aborted:

            $output->writeln('');
            $output->writeln('<error>Aborting download and cleaning up temporary directories.</error>');

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

        return null;
    }

    /**
     * Removes all the temporary files and directories created to
     * download the project and removes Bolt-related files that don't make
     * sense in a proprietary project.
     *
     * @return $this
     */
    protected function cleanUp()
    {
        $this->fs->remove(dirname($this->downloadedFilePath));

        try {
            $licenseFile = [$this->projectDir . '/LICENSE'];
            $upgradeFiles = glob($this->projectDir . '/UPGRADE*.md');
            $changelogFiles = glob($this->projectDir . '/CHANGELOG*.md');

            $filesToRemove = array_merge($licenseFile, $upgradeFiles, $changelogFiles);
            $this->fs->remove($filesToRemove);
        } catch (\Exception $e) {
            // don't throw an exception in case any of the Bolt-related files cannot
            // be removed, because this is just an enhancement, not something mandatory
            // for the project
        }

        return $this;
    }

    /**
     * It displays the message with the result of installing Bolt
     * and provides some pointers to the user.
     *
     * @return $this
     */
    protected function displayInstallationResult()
    {
        if (empty($this->requirementsErrors)) {
            $this->output->writeln(sprintf(
                " <info>%s</info>  Bolt %s was <info>successfully installed</info>. Now you can:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔',
                $this->getInstalledBoltVersion()
            ));
        } else {
            $this->output->writeln(sprintf(
                " <comment>%s</comment>  Bolt %s was <info>successfully installed</info> but your system doesn't meet its\n" .
                "     technical requirements! Fix the following issues before executing\n" .
                "     your Bolt application:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'FAILED' : '✕',
                $this->getInstalledBoltVersion()
            ));

            foreach ($this->requirementsErrors as $helpText) {
                $this->output->writeln(' * ' . $helpText);
            }

            $checkFile = $this->isBolt4() ? 'bin/symfony_requirements' : 'app/check.php';

            $this->output->writeln(sprintf(
                " After fixing these issues, re-check Bolt requirements executing this command:\n\n" .
                "   <comment>php %s/%s</comment>\n\n" .
                " Then, you can:\n",
                $this->projectName, $checkFile
            ));
        }

        if ('.' !== $this->projectDir) {
            $this->output->writeln(sprintf(
                "    * Change your current directory to <comment>%s</comment>\n", $this->projectDir
            ));
        }

        $consoleDir = ($this->isBolt4() ? 'bin' : 'app');
        $serverRunCommand = version_compare($this->version, '2.6.0', '>=') && extension_loaded('pcntl') ? 'server:start' : 'server:run';

        $this->output->writeln(sprintf(
            "    * Configure your application in <comment>app/config/config.yml</comment> file.\n\n" .
            "    * Run your application:\n" .
            "        1. Execute the <comment>php %s/nut %s</comment> command.\n" .
            "        2. Browse to the <comment>http://0.0.0.0:8000</comment> URL.\n\n" .
            "    * Read the documentation at <comment>https://docs.bolt.cm</comment>\n",
            $consoleDir, $serverRunCommand
        ));

        return $this;
    }

    /**
     * Dump a basic README.md file.
     *
     * @return $this
     */
    protected function dumpReadmeFile()
    {
        $readmeContents = sprintf("%s\n%s\n\nA Bolt project created on %s.\n", $this->projectName, str_repeat('=', strlen($this->projectName)), date('F j, Y, g:i a'));
        try {
            $this->fs->dumpFile($this->projectDir . '/README.md', $readmeContents);
        } catch (\Exception $e) {
            // don't throw an exception in case the file could not be created,
            // because this is just an enhancement, not something mandatory
            // for the project
        }

        return $this;
    }

    /**
     * Updates the Bolt config.yml file to replace default configuration
     * values with better generated values.
     *
     * @return $this
     */
    protected function updateParameters()
    {
        $filename = $this->projectDir . '/app/config/config.yml';

        if (!is_writable($filename)) {
            if ($this->output->isVerbose()) {
                $this->output->writeln(sprintf(
                    " <comment>[WARNING]</comment> The value of the <info>secret</info> configuration option cannot be updated because\n" .
                    " the <comment>%s</comment> file is not writable.\n",
                    $filename
                ));
            }

            return $this;
        }

        $ret = str_replace('ThisTokenIsNotSoSecretChangeIt', $this->generateRandomSecret(), file_get_contents($filename));
        file_put_contents($filename, $ret);

        return $this;
    }

    /**
     * Updates the composer.json file to provide better values for some of the
     * default configuration values.
     *
     * @return $this
     */
    protected function updateComposerConfig()
    {
        parent::updateComposerConfig();
        $this->composerManager->updateProjectConfig([
            'name'        => $this->composerManager->createPackageName($this->projectName),
            'license'     => 'proprietary',
            'description' => null,
            'extra'       => ['branch-alias' => null],
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDownloadedApplicationType()
    {
        return 'Bolt';
    }

    /**
     * {@inheritdoc}
     */
    protected function getRemoteFileUrl()
    {
        if ($this->version === 'latest') {
            return 'https://bolt.cm/distribution/bolt-' . $this->version;
        }

        return sprintf('https://bolt.cm/distribution/archive/%s/bolt-%s', $this->getMinorVersion(), $this->version);
    }

    private function getMinorVersion()
    {
        $ver = explode('.', $this->version);

        return $ver[0] . '.' . isset($ver[1]) ? $ver[1] : 0;
    }
}
