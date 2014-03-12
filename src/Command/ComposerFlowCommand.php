<?php

namespace MoneyMedia\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerFlowCommand extends Command
{
    protected function configure() {
        $this
            ->setName('status')
            ->setDescription('Check the git-flow status of a composer application')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'URL to a Composerized application'
            )
            ->addArgument(
                'refspec',
                InputArgument::OPTIONAL,
                'A tag, branch or refspec for the composer install',
                'master'
            )
            /*
            ->addOption(
               'yell',
               null,
               InputOption::VALUE_NONE,
               'If set, the task will yell in uppercase letters'
            )
            */
        ;
    }

}
