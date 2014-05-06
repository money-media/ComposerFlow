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
               null,
               InputOption::VALUE_NONE,
               'If set, the task will not actually merge branches'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $src = $input->getArgument('src');
        $dst = $input->getArgument('dst');
        $repos = $this->_getRepos();
        if (OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
            $output->writeln("Filtering branches");
        }
        $srcRepos = $this->_filterRepoByBranch($repos, $src);
        $dstRepos = $this->_filterRepoByBranch($repos, $dst);
        $targetRepos = $this->_filterRepoByBranch($srcRepos, $dst);
        if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $numRepos = count($repos);
            $numSrcRepos = count($srcRepos);
            $numDstRepos = count($dstRepos);
            $numTargetRepos = count($targetRepos);
            $output->writeln("$numRepos repos in total; $numSrcRepos w/ src branch $src, $numDstRepos w/ dst branch $dst, $numTargetRepos with both.");
        }
    }
}
