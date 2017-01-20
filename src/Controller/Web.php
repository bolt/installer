<?php

namespace Bolt\Installer\Controller;

use Bolt\Installer\Application;
use Bolt\Installer\Command;
use Bolt\Installer\Output\BufferedArrayOutput;
use Bolt\Requirement\BoltRequirements;
use Bolt\Requirement\PhpIniRequirement;
use Bolt\Requirement\Requirement;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web server controller.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Web
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        ob_clean();
    }

    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        if ($request->query->has('check')) {
            $this->check();
        } elseif ($request->query->has('install')) {
            $this->install();
        } else {
            $response = new Response();
            $body = '';
            $page = $this->getHtml('index.html', 'Bolt Set-up', $body);
            $response->setContent($page);
            $response->send();
        }
    }

    private function check()
    {
        $response = new Response();
        $body = '<h1>Checking</h1>';

        $boltRequirements = new BoltRequirements(__DIR__);
        if ($boltRequirements->hasPhpIniConfigIssue()) {
            $body .= '<h2>PHP Configuration problems found</h2>';
            $body .= '<ul>';
            foreach ($boltRequirements as $requirement) {
                if ($requirement instanceof PhpIniRequirement) {
                    $body .= '<li>' . $requirement->getHelpHtml() . '</li>';
                }
            }
            $body .= '</ul>';
        }

        /** @var Requirement $requirement */
        $body .= '<h2>Requirement problems found</h2>';
        $body .= '<ul>';
        foreach ($boltRequirements->getRequirements() as $requirement) {
            if ($requirement->isFulfilled() || $requirement instanceof PhpIniRequirement) {
                continue;
            }
            $body .= '<li>' . $requirement->getHelpHtml() . '</li>';
        }
        $body .= '</ul>';

        $body .= '<h2>Recommended settings are not correct</h2>';
        $body .= '<ul>';
        foreach ($boltRequirements->getRecommendations() as $requirement) {
            if ($requirement->isFulfilled() || $requirement instanceof PhpIniRequirement) {
                continue;
            }
            $body .= '<li>' . $requirement->getHelpHtml() . '</li>';
        }
        $body .= '</ul>';

        $page = $this->getHtml('check.html', 'Bolt System Checks', $body);
        $response->setContent($page);

        return $response->send();
    }

    /**
     * @return Response
     */
    private function install()
    {
        $phar = $this->getPharFile();
        $target = getcwd();
        $response = new Response();
        $body = '<h1>Installing</h1>';

        $app = new Application('Bolt Installer', '');
        $app->add(new Command\CheckCommand());
        $app->add((new Command\NewCommand())->setWebInstall(true));
        $app->add(new Command\SelfUpdateCommand());
        $app->setDefaultCommand('about');
        $app->setAutoExit(false);
        $output = new BufferedArrayOutput();

        $template = $this->getHtml('install.html');

        $result = $app->run(new StringInput('new ' . $target), $output);
        //if ((int) $result > 0) {
        //    $this->setPharFile($phar);
        //}

        $body .= "<p>Installed Bolt in $target</p>";

        foreach ($output->fetch() as $message) {
            $body .= sprintf('<p>%s</p>', nl2br($message));
        }

        $page = sprintf($template, 'Bolt Installation', $body);
        $response->setContent($page);

        return $response->send();
    }

    /**
     * @return \Phar
     */
    private function getPharFile()
    {
        $target = getcwd();
        $pharFile = sprintf('%s/%s', $target, basename($_SERVER['PHP_SELF']));

        return new \Phar($pharFile, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS, 'bolt');
    }

    private function setPharFile(\Phar $phar)
    {
        $target = getcwd();
        $pharFile = sprintf('%s/%s', $target, basename($_SERVER['PHP_SELF']));

        file_put_contents($pharFile, $phar);
    }

    /**
     * @param string $template
     *
     * @return string
     */
    private function getHtml($template)
    {
        return file_get_contents(sprintf('phar://bolt/web/%s', $template));
    }
}
