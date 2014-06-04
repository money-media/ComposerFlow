<?php

namespace MoneyMedia\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use SebastianBergmann\Git;


class StatusCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('status')
            ->setDescription('Check the git-flow status of a composer application')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $repos = $this->_getRepos();
        $pkg_width = array_reduce(array_keys($repos), function($x,$y) { return max(strlen($y),$x); });

        $queries = array(
            array('HEAD','master'),
            array('master','develop'),
        );
        $results = array();

        //$repos = array_slice($repos, 0, 4);

        foreach($repos as $name => $path) {
            $git = new Git($path);
            $tag = $git->getCurrentDescription();
            if ($output->isDebug()) {
                $output->writeln("Working on package $name @ $tag");
            }
            $result = array();
            $result['tag'] = $tag;
            foreach($queries as $pair) {
                $key = implode('..', $pair);
                foreach($pair as &$branch) {
                    if($branch == 'HEAD') {
                        $branch = $tag;
                    } else {
                        $git->checkout($branch); // diffing doesn't work otherwise; no local branches!
                        $branch = "origin/$branch";
                    }
                }
                list($a, $b) = $pair;
                $ahead = $git->getAhead($a, $b);
                $is_ahead = abs($ahead['left']) + abs($ahead['right']) != 0;
                $result[$key] = $is_ahead ? "{$ahead['left']},{$ahead['right']}" : null;

            }
            $result['name'] = $name;
            $results[$name] = $result;
            $git->checkout($tag);
        }

        $widths = array();
        array_walk($queries, function(&$item) { $item = implode('..', $item); });
        array_unshift($queries, 'name', 'tag');

        array_unshift($results, array_combine($queries, $queries)); // put column heads in
        foreach($queries as $query) {
            $widths[$query] = array_reduce(array_column($results, $query), function($x,$y) { return max(strlen($y),$x); });
        }

        foreach($results as $rowIdx => $result){
            $string = '';
            foreach($queries as $colIdx => $k) {
                $v = $result[$k];
                if($rowIdx && $colIdx && $k != 'tag') {
                    $tag = $v===null ? "pass" : "fail";
                    if($tag!='fail') {
                        $v = strtoupper($tag);
                    }
                } elseif(!$rowIdx) {
                    $tag = 'head';
                } else {
                    $tag = 'plain';
                }
                $string .= sprintf("<$tag>%' {$widths[$k]}s</$tag> ", $v);
            }
            $output->writeln($string);
        }

    }
}
