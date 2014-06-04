<?php

namespace MoneyMedia\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use SebastianBergmann\Git;


class MergeCommand extends BaseCommand
{
    protected $_isScratchable = true;

    protected function configure() {
        parent::configure();
        $this
            ->setName('merge')
            ->setDescription('Initiate a merge from one branch to another (iff they both exist)')
            ->addArgument(
                'src',
                InputArgument::REQUIRED,
                'Merge from this branch'
            )
            ->addArgument(
                'dst',
                InputArgument::REQUIRED,
                'Merge into this branch'
            )
            ->addOption(
               'dry-run',
               'null',
               InputOption::VALUE_NONE,
               'If set, the task will not actually merge branches, etc. Might do some checkouts FIXME'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $src = $input->getArgument('src');
        $dst = $input->getArgument('dst');
        $dry = $input->getOption('dry-run');

        if (!$dry && OutputInterface::VERBOSITY_QUIET < $output->getVerbosity()) {
            $output->writeln("<warn>Warning: About to perform a merge! Hit control-C to stop.</warn>");
            usleep(1000);
        }


        $repos = $this->_getRepos();
        if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
            $output->write("<info>Filtering branches...</info>");
        }

        $srcRepos = $this->_filterRepoByBranch($repos, $src);
        $dstRepos = $this->_filterRepoByBranch($repos, $dst);
        $targetRepos = $this->_filterRepoByBranch($srcRepos, $dst);

        if (OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
            $output->writeln("<info> done!</info>");
        }

        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $numRepos = count($repos);
            $numSrcRepos = count($srcRepos);
            $numDstRepos = count($dstRepos);
            $numTargetRepos = count($targetRepos);
            $output->writeln("<comment>$numRepos repos in total; $numSrcRepos w/ src branch $src, $numDstRepos w/ dst branch $dst, $numTargetRepos with both.</comment>");
            if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
                $width = array_reduce(array_keys($targetRepos), function($x,$y) { return max(strlen($y),$x); });
                foreach($targetRepos as $name => $repo) {
                    $git = new Git($repo);
                    $ahead = $git->getAhead($src,$dst);

                    $output->writeln(
                        sprintf("%{$width}s",$name) . "   {$ahead['right']} commits"
                    );
                }
                $output->writeln('');
            }
        }
        if($dry) {
            if (OutputInterface::VERBOSITY_QUIET < $output->getVerbosity()) {
                $output->writeln("<info>Dry-run complete; exiting.</info>");
                return;
            }
        }

    }
}
