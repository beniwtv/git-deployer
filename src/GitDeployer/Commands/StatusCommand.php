<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('status')
            ->setDescription('Display the current deployment status of your Git repositories')
            ->setHelp(
              <<<HELP
 Hello world!
HELP
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
               
        $output->writeln($text);

    }

}