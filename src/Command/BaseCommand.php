<?php

namespace MoneyMedia\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Process\Process;

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
            ->addArgument(
                'refspec',
                InputArgument::OPTIONAL,
                'A tag, branch or refspec for the composer install',
                'master'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repo = $input->getArgument('repo');
        $refspec = $input->getArgument('refspec');

        $composer = `which composer`;
        if(!$composer) {
            $this->_raise($output, "Composer not found!");
        }
        $cwd_orig = getcwd();

        $vendor_path = "$repo/vendor";

        if(!is_dir($vendor_path)) {
            $this->_raise($output, "Couldn't chdir to $vendor_path!");
        }

        // composer install
        if(!chdir($repo)) {
            $this->_raise($output, "Couldn't chdir to $repo!");
        }
        $repo = getcwd(); // real path to repo

        $composerGit = new Git($repo); // checkout tag
        $composerGit->checkout($refspec);
        $process = new Process('composer install -n');
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use($output) {
            $output->write($buffer);
        });

        if(!chdir('vendor')) {
            $this->_raise($output, "Couldn't chdir to $vendor_path!");
        }
    }

    protected function _raise(OutputInterface $output, $message)
    {
        $output->writeln("<error>$message</error>");
        throw new Exception($message);
    }
}
