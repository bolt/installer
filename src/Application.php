<?php

namespace Bolt\Installer;

use Bolt\Installer\Output\BufferedArrayOutput;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bolt Installer console application.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Application extends ConsoleApplication
{
    /** @var string */
    private $version = '0.2.0-DEV';
    /** @var bool */
    private $web;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $version
     * @param bool   $isWeb
     */
    public function __construct($name = 'UNKNOWN', $version = null, $isWeb = false)
    {
        parent::__construct($name, $version ?: $this->version);
        $this->web = $isWeb;
    }

    /**
     * @return bool
     */
    public function isWeb()
    {
        return $this->web;
    }

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $input) {
            $input = new ArgvInput();
        }

        if (null === $output) {
            $output = $this->web ? new BufferedArrayOutput() : new ConsoleOutput();
        }

        return parent::run($input, $output);
    }
}
