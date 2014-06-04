<?php

namespace MoneyMedia\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;


use SebastianBergmann\Git;


abstract class BaseCommand extends Command
{

    protected $_isScratchable = false;
    protected $_output = null;

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
            ->addOption(
               'scratch-copy',
               null,
               InputOption::VALUE_NONE,
               'If set, the task will first copy the repo to a temporary location'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_output = $output;
        $output->getFormatter()->setStyle('head', new OutputFormatterStyle('white', 'black', array('bold')));
        $output->getFormatter()->setStyle('plain', new OutputFormatterStyle('white'));
        $output->getFormatter()->setStyle('pass', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('fail', new OutputFormatterStyle('red', 'yellow', array('bold', 'blink')));
        $output->getFormatter()->setStyle('warn', new OutputFormatterStyle('yellow', 'black', array('bold', 'blink')));

        $repo = $input->getArgument('repo');
        $refspec = $input->getOption('refspec');
        $doInstall = $input->getOption('no-install')==0;
        $scratch = $input->getOption('scratch-copy');

        $composer = `which composer`;
        if(!$composer) {
            $this->_raise("Composer not found!");
        }
        $cwd_orig = getcwd();

        $vendor_path = "$repo/vendor";

        // composer install
        if(!chdir($repo)) {
            $this->_raise("Couldn't chdir to $repo!");
        }
        $repo = getcwd(); // real path to repo

        if($this->_isScratchable && $scratch) {
            $scratch = $this->_getScratchDirectory();

            $that = $this; // http://stackoverflow.com/questions/19431440/why-can-i-not-use-this-as-a-lexical-variable-in-php-5-5-4
            $f =  function() use ($that, $scratch, $output) {
                $that->shutdownHandler($scratch, $output);
            };

            declare(ticks = 1);
            register_shutdown_function($f);
            pcntl_signal(SIGINT, $f);
            pcntl_signal(SIGTERM, $f);

            if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
                $output->write("<info>Copying repo into scratch directory $scratch...</info>");
            }

            chdir($scratch);
            $fs = new Filesystem();
            $fs->mirror($repo, $scratch);

            if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
                $output->writeln("<info> done!</info>");
            }
            $repo = $scratch;
        }

        if(!$doInstall && $refspec) {
            $this->_raise("--refspec incompatible with --no-install");
        }

        if($doInstall) {
            $this->_composerInstall($repo, $refspec, $output);
        }

        if(!chdir('vendor')) {
            $this->_raise("Couldn't chdir to $vendor_path!");
        }
    }

    protected function _composerInstall($repo, $refspec, OutputInterface $output)
    {
        $composerGit = new Git($repo); // checkout tag
        if($refspec) {
            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln("<info>Checking out \"$refspec\"</info>");
            }
            $composerGit->checkout($refspec);
        } else {
            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->writeln("<comment>Skipping checkout of root package since refspec not specified</comment>");
            }
        }

        if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
            $output->writeln("<info>Running Composer install</info>");
        }
        $process = new Process('composer install -n');
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use($output) {
            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $output->write($buffer);
            }
        });

        $code = $process->getExitCode();
        if($code != 0) {
            $this->_raise("composer install returned $code");
        }
    }

    protected function _raise($message)
    {
        $output = $this->_output;
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

    protected function _getScratchDirectory()
    {
        $tempfile=tempnam(sys_get_temp_dir(), 'cflo');
        if (file_exists($tempfile)){
            unlink($tempfile);
        }
        mkdir($tempfile);
        if (is_dir($tempfile))
        {
            return $tempfile;
        }
        $this->_raise("Problem creating scratch directory in $tempfile");
    }

    public function shutdownHandler($scratchDirectory, $output)
    {
        //register_shutdown_function($f);
        pcntl_signal(SIGINT, SIG_IGN);
        pcntl_signal(SIGTERM, SIG_DFL);
        if(is_dir($scratchDirectory)) {
            $fs = new Filesystem();
            if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
                $output->writeln("<info>Removing scratch directory $scratchDirectory</info>");
            }
            $fs->remove($scratchDirectory);
            if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
                $output->writeln("<info> done!</info>");
            }
        }
        exit();
    }
}
