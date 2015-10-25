<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('init')
            ->setDescription('Initializes a skeleton .deployerfile in your Git repository');

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        if (getcwd()) {
            if (\Gitonomy\Git\Admin::isValidRepository(getcwd())) {
                // Repo is valid, drop the bare example file there
                copy(__DIR__ . '/../../../stuff/deployerfile.json.template', getcwd() . '/deployerfile');
                $output->writeln('Repository initialized!');
            } else {
                throw new \Exception('"' . getcwd() . '" does not appear to contain a Git repository!');
            }
        } else {
            throw new \Exception('Could not access "' . getcwd() . '"');
        }

    }

}
