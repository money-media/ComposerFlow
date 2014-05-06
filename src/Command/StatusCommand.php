<?php

namespace MoneyMedia\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

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

        $output->getFormatter()->setStyle('head', new OutputFormatterStyle('white', 'black', array('bold')));
        $output->getFormatter()->setStyle('plain', new OutputFormatterStyle('white'));
        $output->getFormatter()->setStyle('pass', new OutputFormatterStyle('green'));
        $output->getFormatter()->setStyle('fail', new OutputFormatterStyle('red', 'yellow', array('bold', 'blink')));
        $output->getFormatter()->setStyle('warn', new OutputFormatterStyle('yellow'));

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
            $tag = trim(`git --work-tree="$path" --git-dir="$path/.git" describe`);
            if ($output->isDebug()) {
                $output->writeln("Working on package $name");
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
                $result[$key] = strlen($git->getDiff("$a", "$b"));
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
                    $tag = $v===0 ? "pass" : "fail";
                    $v = strtoupper($tag);
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
