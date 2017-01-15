<?php

namespace Bolt\Installer\Command;

use Bolt\Installer\Requirement\BoltRequirements;
use Bolt\Installer\Requirement\PhpIniRequirement;
use Bolt\Installer\Requirement\Requirement;
use Bolt\Installer\Requirement\RequirementCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Check installation command.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class CheckCommand extends Command
{
    /** @var Filesystem */
    protected $fs;
    /** @var string The project name */
    protected $projectName;
    /** @var string The project dir */
    protected $projectDir;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('check')
            ->setDescription('Bolt Installer environment checks.')
            ->addArgument('directory', InputArgument::OPTIONAL, 'Directory (optional) where the project is located.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();

        $style = new OutputFormatterStyle('black', 'yellow', []);
        $output->getFormatter()->setStyle('koala', $style);
        $directory = rtrim(trim($input->getArgument('directory')), DIRECTORY_SEPARATOR);
        $this->projectDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd() . DIRECTORY_SEPARATOR . $directory;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $boltRequirements = new BoltRequirements($this->projectDir);
        $requirementsErrors = [];
        $recommendationsErrors = [];
        $problemFree = true;

        if ($boltRequirements->hasPhpIniConfigIssue()) {
            $this->notifyPhpIniConfigIssue($boltRequirements, $output);
        }

        /** @var Requirement $requirement */
        foreach ($boltRequirements->getRequirements() as $requirement) {
            if ($requirement->isFulfilled() || $requirement instanceof PhpIniRequirement) {
                continue;
            }
            $requirementsErrors[] = $requirement;
        }
        foreach ($boltRequirements->getRecommendations() as $requirement) {
            if ($requirement->isFulfilled() || $requirement instanceof PhpIniRequirement) {
                continue;
            }
            $recommendationsErrors[] = $requirement;
        }

        if (!empty($requirementsErrors)) {
            $problemFree = false;
            $this->notifyRequirements($requirementsErrors, $output);
        }

        if (!empty($recommendationsErrors)) {
            $problemFree = false;
            $this->notifyRecommendations($recommendationsErrors, $output);
        }

        if ($problemFree) {
            $this->notifyProblemFree($output);
        }
    }

    private function notifyProblemFree(OutputInterface $output)
    {
        $banner = <<<BANNER
 <bg=green;options=bold>

 Congratulations!
 
 Everything looks OK, you should be able to run Bolt on this host.
 </>

BANNER;
        $output->writeln(sprintf($banner));
    }

    /**
     * @param RequirementCollection $requirements
     * @param OutputInterface       $output
     */
    private function notifyPhpIniConfigIssue(RequirementCollection $requirements, OutputInterface $output)
    {
        $style = new OutputFormatterStyle('black', 'red', ['bold']);
        $output->getFormatter()->setStyle('banner_php', $style);
        $banner = <<<BANNER
 <banner_php>

 PHP Configuration problems found:
 </>
BANNER;
        $output->write(sprintf($banner));

        $message = '';
        foreach ($requirements as $requirement) {
            if ($requirement instanceof PhpIniRequirement) {
                $message .= $this->getErrorMessage($requirement);
            }
        }
        $output->writeln(sprintf('<koala>%s%s</koala>', PHP_EOL, $message));
    }

    /**
     * @param Requirement[]   $requirements
     * @param OutputInterface $output
     */
    private function notifyRequirements($requirements, OutputInterface $output)
    {
        $style = new OutputFormatterStyle('black', 'red', ['bold']);
        $output->getFormatter()->setStyle('banner_require', $style);
        $banner = <<<BANNER
 <banner_require>

 Requirement problems found:
 </>
BANNER;
        $output->write(sprintf($banner));

        $message = '';
        foreach ($requirements as $requirement) {
            $message .= $this->getErrorMessage($requirement);
        }
        $output->writeln(sprintf('<koala>%s%s</koala>', PHP_EOL, $message));
    }

    /**
     * @param Requirement[]   $requirements
     * @param OutputInterface $output
     */
    private function notifyRecommendations($requirements, OutputInterface $output)
    {
        $style = new OutputFormatterStyle('black', 'cyan', ['bold']);
        $output->getFormatter()->setStyle('banner_recommend', $style);
        $banner = <<<BANNER
 <banner_recommend>

 Recommended settings are not correct:
 </>
BANNER;
        $output->write(sprintf($banner));

        $message = '';
        foreach ($requirements as $requirement) {
            $message .= $this->getErrorMessage($requirement);
        }
        $output->writeln(sprintf('<koala>%s%s</koala>', PHP_EOL, $message));
    }

    /**
     * @param Requirement $requirement
     * @param int         $lineSize
     *
     * @return null|string
     */
    private function getErrorMessage(Requirement $requirement, $lineSize = 70)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL . '   ') . PHP_EOL;
        $errorMessage .= '   > ' . wordwrap($requirement->getHelpText(), $lineSize - 5, PHP_EOL . '   > ') . PHP_EOL;

        return $errorMessage;
    }
}
