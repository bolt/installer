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
            $body = '';

            $template = $this->getHtml('index.html');
            $page = str_replace('%TITLE%', 'Bolt Set-up', $template);
            $page = str_replace('%CSS%', file_get_contents('phar://bolt/web/installer.css'), $page);
            $page = str_replace('%BODY%', $body, $page);

            $response = new Response($page);
            $response->send();
        }
    }

    private function check()
    {
        $template = $this->getHtml('check.html');

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

        $page = str_replace('%TITLE%', 'Bolt System Checks', $template);
        $page = str_replace('%CSS%', file_get_contents('phar://bolt/web/installer.css'), $page);
        $page = str_replace('%BODY%', $body, $page);

        $response = new Response();
        $response->setContent($page);

        return $response->send();
    }

    /**
     * @return Response
     */
    private function install()
    {
        $app = $this->getConsole();
        $phar = $this->getPharFile();
        $target = getcwd();

        $body = '<h1>Install Bolt</h1>';

        $output = new BufferedArrayOutput();
        $result = (int) $app->run(new StringInput('new ' . $target), $output);

        foreach ($output->fetch() as $message) {
            if (strpos($message, 'new') === 0 || trim($message) === '') {
                continue;
            }
            if (strpos($message, 'p>') === false && strpos($message, 'ul>') === false && strpos($message, 'li>') === false) {
                $$message = nl2br($message);
            }
            $body .= sprintf('<p>%s</p>', $message);
        }

        if ($result > 0) {
            try {
                $phar->stopBuffering();
            } catch (\UnexpectedValueException $e) {
            }
        }

        $template = $this->getHtml('install.html');
        $page = str_replace('%TITLE%', 'Bolt Installation', $template);
        $page = str_replace('%CSS%', file_get_contents('phar://bolt/web/installer.css'), $page);
        $page = str_replace('%BODY%', $body, $page);

        $response = new Response();
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

    /**
     * @param string $template
     *
     * @return string
     */
    private function getHtml($template)
    {
        return file_get_contents(sprintf('phar://bolt/web/%s', $template));
    }

    /**
     * @return Application
     */
    private function getConsole()
    {
        $app = new Application('Bolt Web Installer', null, true);
        $app->add(new Command\CheckCommand());
        $app->add(new Command\NewCommand());
        $app->setAutoExit(false);

        return $app;
    }
}
