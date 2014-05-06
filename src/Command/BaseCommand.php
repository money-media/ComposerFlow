<?php

namespace MoneyMedia\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Finder\Finder;

use SebastianBergmann\Git;


abstract class BaseCommand extends Command
{
    protected function configure()
    {
        $this
            ->addArgument(
                'repo',
                InputArgument::REQUIRED,
                'path to a Composerized application'
            )
            ->addOption(
                'refspec',
                null,
               InputOption::VALUE_REQUIRED,
                'A tag, branch or refspec for the composer install; implies a fresh checkout'
            )
            ->addOption(
               'no-install',
               null,
               InputOption::VALUE_NONE,
               'Do not switch branches or run composer install. Not compatible with --refspec'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repo = $input->getArgument('repo');
        $refspec = $input->getOption('refspec');
        $doInstall = $input->getOption('no-install')==0;

        $composer = `which composer`;
        if(!$composer) {
            $this->_raise($output, "Composer not found!");
        }
        $cwd_orig = getcwd();

        $vendor_path = "$repo/vendor";

        // composer install
        if(!chdir($repo)) {
            $this->_raise($output, "Couldn't chdir to $repo!");
        }
        $repo = getcwd(); // real path to repo


        if(!$doInstall && $refspec) {
            $this->_raise($output, "--refspec incompatible with --no-install");
        }

        if($doInstall) {
            $this->_composerInstall($repo, $refspec, $output);
        }

        if(!chdir('vendor')) {
            $this->_raise($output, "Couldn't chdir to $vendor_path!");
        }
    }

    protected function _composerInstall($repo, $refspec, OutputInterface $output)
    {
        $composerGit = new Git($repo); // checkout tag
        if($refspec) {
            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $output->write("Checking out \"$refspec\"");
            }
            $composerGit->checkout($refspec);
        } else {
            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->write("Skipping checkout");
            }
        }

        if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
            $output->write("Running Composer install");
        }
        $process = new Process('composer install -n');
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use($output) {
            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->write($buffer);
            }
        });
    }

    protected function _raise(OutputInterface $output, $message)
    {
        if (!$output->isQuiet()) {
            $output->writeln("<error>$message</error>");
        }
        throw new \Exception($message);
    }

    protected function _getRepos()
    {
        $finder = new Finder();
        $finder->in('.')->directories()->depth('== 1');

        $repos = array();
        foreach ($finder as $file) {
            if(is_dir($file->getRealpath().'/.git')) {
                $repos[$file->getRelativePathname()] = $file->getRealpath();
            }
        }
        return $repos;
    }

    protected function _filterRepoByBranch($repos, $branch) {
        return array_filter($repos, function($path) use ($branch) {
            $git = new Git($path);
            $output =  `cd $path && git checkout --track -b $branch origin/$branch 2>&1`; // hack for setting up tracking branches fixme
            $git->checkout($branch);
            return $git->getCurrentBranch() == $branch;
        });
    }

}
