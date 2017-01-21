<?php

namespace Bolt\Installer\Command;
use Bolt\Installer\Application;
use Bolt\Installer\Exception\AbortException;
use Bolt\Installer\Manager\ComposerManager;
use Bolt\Installer\Urls;
use Bolt\Requirement\BoltRequirements;
use Bolt\Requirement\Requirement;
use Distill\Distill;
use Distill\Exception\IO\Input\FileCorruptedException;
use Distill\Exception\IO\Input\FileEmptyException;
use Distill\Exception\IO\Output\TargetDirectoryNotWritableException;
use Distill\Format\Composed\TarGz;
use Distill\Strategy\MinimumSize;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Cache;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;

/**
 * Abstract command used by commands which download and extract compressed Symfony files.
 *
 * Based on Symfony Installer (c) Fabien Potencier <fabien@symfony.com>
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
abstract class DownloadCommand extends Command
{
    /** @var Filesystem To dump content to a file */
    protected $fs;
    /** @var OutputInterface|Output To output content */
    protected $output;
    /** @var string The project name */
    protected $projectName;
    /** @var string The project dir */
    protected $projectDir;
    /** @var string The version to install */
    protected $version = 'latest';
    /** @var string The latest installer version */
    protected $latestInstallerVersion;
    /** @var string The version of the local installer being executed */
    protected $localInstallerVersion;
    /** @var string The path to the downloaded file */
    protected $downloadedFilePath;
    /** @var array The requirement errors */
    protected $requirementsErrors = [];
    /** @var ComposerManager */
    protected $composerManager;
    /** @var bool */
    protected $useFlat;

    /** @var Cache\Adapter\AbstractAdapter */
    private $cache;
    /** @var Application */
    private $application;

    /**
     * Returns the type of the downloaded application in a human readable format.
     * It's mainly used to display readable error messages.
     *
     * @return string The type of the downloaded application in a human readable format
     */
    abstract protected function getDownloadedApplicationType();

    /**
     * Returns the absolute URL of the remote file downloaded by the command.
     *
     * @return string The absolute URL of the remote file downloaded by the command
     */
    abstract protected function getRemoteFileUrl();

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

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->fs = new Filesystem();

        $latestInstallerVersion = $this->getUrlContents(Urls::INSTALLER_LATEST_VER)->getContents();
        $this->latestInstallerVersion = trim($latestInstallerVersion);
        $this->localInstallerVersion = $this->getApplication()->getVersion();

        $this->enableSignalHandler();
    }

    /**
     * Chooses the best compressed file format to download (ZIP or TGZ) depending upon the
     * available operating system uncompressing commands and the enabled PHP extensions
     * and it downloads the file.
     *
     * @throws \RuntimeException If the Bolt archive could not be downloaded
     *
     * @return $this
     */
    protected function download()
    {
        $remoteUrl = $this->getRemoteFileUrl();
        $this->output->writeln(sprintf("\n Downloading %s...\n", $this->getDownloadedApplicationType()));

        // decide which is the best compressed version to download
        $distill = new Distill();
        $boltArchiveFile = $distill
            ->getChooser()
            ->setStrategy(new MinimumSize())
            ->addFilesWithDifferentExtensions($remoteUrl, ['tar.gz', 'zip'])
            ->getPreferredFile()
        ;
        $this->writeDebug(sprintf("<info> — Fetching %s</info>\n", $remoteUrl));

        // store the file in a temporary hidden directory with a random name
        $this->downloadedFilePath = rtrim(getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.' . uniqid(time()) . DIRECTORY_SEPARATOR . 'bolt.' . pathinfo($boltArchiveFile, PATHINFO_EXTENSION);
        /** @var ProgressBar|null $progressBar */
        $progressBar = null;
        $downloadCallback = $this->getDownloadCallback($progressBar);
        $options = [
            RequestOptions::ALLOW_REDIRECTS => true,
            RequestOptions::PROGRESS        => $downloadCallback,
            RequestOptions::SYNCHRONOUS     => true,
        ];

        try {
            $fileUrl = $remoteUrl;
            $response = $this->getUrlContents($fileUrl, $options);
        } catch (ClientException $e) {
            if ('new' === $this->getName() && ($e->getCode() === 403 || $e->getCode() === 404)) {
                throw new \RuntimeException(sprintf(
                    "The selected version (%s) cannot be installed because it does not exist.\n" .
                    "Execute the following command to install the latest stable Bolt release:\n" .
                    '%s new %s',
                    $this->version,
                    $_SERVER['PHP_SELF'],
                    str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $this->projectDir)
                ));
            } else {
                throw new \RuntimeException(sprintf(
                    "There was an error downloading %s from bolt.cm server:\n%s",
                    $this->getDownloadedApplicationType(),
                    $e->getMessage()
                ), null, $e);
            }
        }

        $this->fs->dumpFile($this->downloadedFilePath, $response->getContents());

        if (null !== $progressBar) {
            $progressBar->finish();
            $this->output->writeln("\n");
        }

        return $this;
    }

    /**
     * Return a lazy ProgressBar object to pass to Guzzle.
     *
     * NOTE: Putting this in a Container presently causes flashing of the
     * progress bar on some terminals.
     *
     * @param ProgressBar|null $progressBar
     *
     * @return callable
     */
    protected function getDownloadCallback(&$progressBar)
    {
        return function ($downloadSize, $downloaded, $uploadSize, $uploaded) use (&$progressBar) {
            $progressTotal = $downloadSize ?: $uploadSize;
            $progressCurrent = $downloaded ?: $uploaded;

            // progress bar is only displayed for files larger than 1MB
            if ($progressTotal < 1 * 1024 * 1024) {
                return;
            }

            if (null === $progressBar) {
                ProgressBar::setPlaceholderFormatterDefinition('max', function (ProgressBar $bar) {
                    return Helper::formatMemory($bar->getMaxSteps());
                });
                ProgressBar::setPlaceholderFormatterDefinition('current', function (ProgressBar $bar) {
                    return str_pad(Helper::formatMemory($bar->getProgress()), 11, ' ', STR_PAD_LEFT);
                });

                $progressBar = new ProgressBar($this->output, $progressTotal);
                $progressBar->setFormat('%current%/%max% %bar%  %percent:3s%%');
                $progressBar->setBarWidth(60);

                if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
                    $progressBar->setEmptyBarCharacter('░'); // light shade character \u2591
                    $progressBar->setProgressCharacter('');
                    $progressBar->setBarCharacter('▓'); // dark shade character \u2593
                }

                if (!$this->getApplication()->isWeb()) {
                    $progressBar->start();
                }
            }
            if (!$this->getApplication()->isWeb()) {
                $progressBar->setProgress($progressCurrent);
            }
        };
    }

    /**
     * Checks the project name.
     *
     * @throws \RuntimeException If there is already a projet in the specified directory
     *
     * @return $this
     */
    protected function checkProjectName()
    {
        if ($this->getApplication()->isWeb() && is_dir($this->projectDir) && !is_file($this->projectDir . '/index.php')) {
            return $this;
        }
        if (is_dir($this->projectDir) && !$this->isEmptyDirectory($this->projectDir)) {
            throw new \RuntimeException(sprintf(
                "There is already a '%s' project in this directory (%s).\n" .
                'Change your project name or create it in another directory.',
                $this->projectName, $this->projectDir
            ));
        }

        return $this;
    }

    /**
     * Returns the Guzzle client configured according to the system environment
     * (e.g. it takes into account whether it should use a proxy server or not).
     *
     * @throws \RuntimeException If the php-curl is not installed or the allow_url_fopen ini setting is not set
     *
     * @return Client The configured Guzzle client
     */
    protected function getGuzzleClient()
    {
        $defaults = [];

        // check if the client must use a proxy server
        if (!empty($_SERVER['HTTP_PROXY']) || !empty($_SERVER['http_proxy'])) {
            $defaults['proxy'] = !empty($_SERVER['http_proxy']) ? $_SERVER['http_proxy'] : $_SERVER['HTTP_PROXY'];
        }

        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $defaults['debug'] = true;
        }

        try {
            $handler = \GuzzleHttp\choose_handler();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('The Bolt installer requires the php-curl extension or the allow_url_fopen ini setting.');
        }

        return new Client(['defaults' => $defaults, 'handler' => $handler]);
    }

    /**
     * Extracts the compressed Bolt file (ZIP or TGZ) using the
     * native operating system commands if available or PHP code otherwise.
     *
     * @throws \RuntimeException If the downloaded archive could not be extracted
     *
     * @return $this
     */
    protected function extract()
    {
        $this->output->writeln(" Preparing project...\n");

        $distill = new Distill();
        $isTarGz = preg_match('/\.gz$/', $this->downloadedFilePath);
        $format = $isTarGz ? new TarGz() : null;

        try {
            $extractionSucceeded = $distill->extractWithoutRootDirectory($this->downloadedFilePath, $this->projectDir, $format);
        } catch (FileCorruptedException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted.\n" .
                "To solve this issue, try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), $this->getExecutedCommand()
            ));
        } catch (FileEmptyException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is empty.\n" .
                "To solve this issue, try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), $this->getExecutedCommand()
            ));
        } catch (TargetDirectoryNotWritableException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the installer doesn't have enough\n" .
                "permissions to uncompress and rename the package contents.\n" .
                "To solve this issue, check the permissions of the %s directory and\n" .
                "try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), getcwd(), $this->getExecutedCommand()
            ));
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted\n" .
                "or because the installer doesn't have enough permissions to uncompress and\n" .
                "rename the package contents.\n" .
                "To solve this issue, check the permissions of the %s directory and\n" .
                "try executing this command again:\n%s",
                ucfirst($this->getDownloadedApplicationType()), getcwd(), $this->getExecutedCommand()
            ), null, $e);
        }

        if (!$extractionSucceeded) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted\n" .
                "or because the uncompress commands of your operating system didn't work.",
                ucfirst($this->getDownloadedApplicationType())
            ));
        }

        return $this;
    }

    /**
     * Checks if environment meets Bolt requirements.
     *
     * @return $this
     */
    protected function checkBoltRequirements()
    {
        if (null === $requirementsFile = $this->getBoltRequirementsFilePath()) {
            return $this;
        }

        try {
            require $requirementsFile;
            $boltRequirements = new BoltRequirements($this->projectDir);
            $this->requirementsErrors = [];
            /** @var Requirement $req */
            foreach ($boltRequirements->getRequirements() as $req) {
                if ($helpText = $this->getErrorMessage($req)) {
                    $this->requirementsErrors[] = $helpText;
                }
            }
        } catch (MethodArgumentValueNotImplementedException $e) {
            // workaround https://github.com/symfony/symfony-installer/issues/163
        }

        return $this;
    }

    private function getBoltRequirementsFilePath()
    {
        $paths = [
            $this->projectDir . '/src/Requirement/BoltRequirements.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Updates the composer.json file to provide better values for some of the
     * default configuration values.
     *
     * @return $this
     */
    protected function updateComposerConfig()
    {
        $this->composerManager->initializeProjectConfig();

        return $this;
    }

    /**
     * Creates the appropriate .gitignore file for a Bolt project if it doesn't exist.
     *
     * @return $this
     */
    protected function createGitIgnore()
    {
        if (!is_file($path = $this->projectDir . '/.gitignore')) {
            try {
                $client = $this->getGuzzleClient();

                $response = $client->get(sprintf(
                    Urls::GIT_IGNORE,
                    $this->getInstalledBoltVersion()
                ));

                $this->fs->dumpFile($path, $response->getBody()->getContents());
            } catch (\Exception $e) {
                // don't throw an exception in case the .gitignore file cannot be created,
                // because this is just an enhancement, not something mandatory for the project
            }
        }

        return $this;
    }

    /**
     * Returns the full Bolt version number of the project by getting
     * it from the composer.lock file.
     *
     * @return string The installed Bolt version
     */
    protected function getInstalledBoltVersion()
    {
        $boltVersion = $this->composerManager->getPackageVersion('bolt/bolt');

        if (!empty($boltVersion) && 'v' === substr($boltVersion, 0, 1)) {
            return substr($boltVersion, 1);
        };

        return $boltVersion;
    }

    /**
     * Checks if the installer has enough permissions to create the project.
     *
     * @throws IOException If the installer does not have enough permissions to write to the project parent directory
     *
     * @return $this
     */
    protected function checkPermissions()
    {
        $projectParentDirectory = dirname($this->projectDir);

        if (!is_writable($projectParentDirectory)) {
            throw new IOException(sprintf('Installer does not have enough permissions to write to the "%s" directory.', $projectParentDirectory));
        }

        return $this;
    }

    /**
     * Formats the error message contained in the given Requirement item
     * using the optional line length provided.
     *
     * @param Requirement $requirement The Bolt requirements
     * @param int         $lineSize    The maximum line length
     *
     * @return string The formatted error message
     */
    protected function getErrorMessage(Requirement $requirement, $lineSize = 70)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL . '   ') . PHP_EOL;
        $errorMessage .= '   > ' . wordwrap($requirement->getHelpText(), $lineSize - 5, PHP_EOL . '   > ') . PHP_EOL;

        return $errorMessage;
    }

    /**
     * Generates a good random value for Bolt's 'secret' option.
     *
     * @return string The randomly generated secret
     */
    protected function generateRandomSecret()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return hash('sha1', openssl_random_pseudo_bytes(23));
        }

        return hash('sha1', uniqid(mt_rand(), true));
    }

    /**
     * Returns the executed command with all its arguments
     * (e.g. "bolt new blog 3.4.1").
     *
     * @return string The executed command with all its arguments
     */
    protected function getExecutedCommand()
    {
        $commandBinary = $_SERVER['PHP_SELF'];
        $commandBinaryDir = dirname($commandBinary);
        $pathDirs = explode(PATH_SEPARATOR, $_SERVER['PATH']);
        if (in_array($commandBinaryDir, $pathDirs)) {
            $commandBinary = basename($commandBinary);
        }

        $commandName = $this->getName();

        if ('new' === $commandName) {
            $commandArguments = sprintf('%s %s', $this->projectName, ('latest' !== $this->version) ? $this->version : '');
        } elseif ('demo' === $commandName) {
            $commandArguments = '';
        }

        return sprintf('%s %s %s', $commandBinary, $commandName, $commandArguments);
    }

    /**
     * Checks whether the given directory is empty or not.
     *
     * @param string $dir the path of the directory to check
     *
     * @return bool Whether the given directory is empty
     */
    protected function isEmptyDirectory($dir)
    {
        // glob() cannot be used because it doesn't take into account hidden files
        // scandir() returns '.'  and '..'  for an empty dir
        return 2 === count(scandir($dir . '/'));
    }

    /**
     * Checks that the asked version is in the 3.x branch.
     *
     * @return bool Whether is Bolt3
     */
    protected function isBolt3()
    {
        return '3' === $this->version[0] || 'latest' === $this->version;
    }

    /**
     * Checks if the installed version is the latest one and displays some
     * warning messages if not.
     *
     * @return $this
     */
    protected function checkInstallerVersion()
    {
        // check update only if installer is running via a PHAR file
        if ('phar://' !== substr(__DIR__, 0, 7)) {
            return $this;
        }

        if (!$this->isInstallerUpdated()) {
            if ($this->getApplication()->isWeb()) {
                $this->output->writeln(
                    sprintf(
                        '<div class="alert callout large"><strong>WARNING </strong> Your Bolt Installer version (%s) is outdated.' .
                        ' <a href="%s">Download the latest version (%s)</a> and overwrite %s</div>',
                        $this->localInstallerVersion,
                        Urls::INSTALLER_FILE,
                        $this->latestInstallerVersion,
                        getcwd() . dirname($_SERVER['PHP_SELF'])
                    )
                );

                return $this;
            }

            $this->output->writeln(sprintf(
                "\n <bg=red> WARNING </> Your Bolt Installer version (%s) is outdated.\n" .
                ' Execute the command "%s selfupdate" to get the latest version (%s).',
                $this->localInstallerVersion, $_SERVER['PHP_SELF'], $this->latestInstallerVersion
            ));
        }

        return $this;
    }

    /**
     * @return bool Whether the installed version is the latest one
     */
    protected function isInstallerUpdated()
    {
        return version_compare($this->localInstallerVersion, $this->latestInstallerVersion, '>=');
    }

    /**
     * Returns the contents obtained by making a GET request to the given URL.
     *
     * @param string $url     The URL to get the contents from
     * @param array  $options Guzzle options
     *
     * @return \Psr\Http\Message\StreamInterface The obtained contents of $url
     */
    protected function getUrlContents($url, array $options = [])
    {
        $client = $this->getGuzzleClient();
        $response = $client->get($url, $options);
        $responseCode = $response->getStatusCode();

        if ($responseCode === Response::HTTP_MOVED_PERMANENTLY || $responseCode === Response::HTTP_FOUND) {
            $location = $response->getHeader('Location');
            $location = reset($location);
            $this->writeDebug(sprintf("<info> — Redirected to %s</info>\n", $location));
            $response = $client->get($location, $options);
        }

        return $response->getBody();
    }

    /**
     * @param string $message
     */
    protected function writeDebug($message)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($message);
        }
    }

    /**
     * @return Cache\Adapter\AbstractAdapter
     */
    protected function getCache()
    {
        if ($this->cache !== null) {
            return $this->cache;
        } elseif (is_writable('/tmp/.bolt-installer')) {
            $this->cache = new Cache\Adapter\FilesystemAdapter('guzzle', 60, '/tmp/.bolt-installer');
        } else {
            $this->cache = new Cache\Adapter\NullAdapter();
        }

        return $this->cache;
    }

    /**
     * @param string $template
     *
     * @return string
     */
    protected function getHtml($template)
    {
        return file_get_contents(sprintf('phar://bolt/web/%s', $template));
    }

    /**
     * Enables the signal handler.
     *
     * @throws AbortException If the execution has been aborted with SIGINT signal.
     */
    private function enableSignalHandler()
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        declare(ticks=1);

        pcntl_signal(SIGINT, function () {
            error_reporting(0);

            throw new AbortException();
        });
    }
}
