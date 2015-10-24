<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class InitCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('init')
            ->setDescription('Initializes a skeleton .git-deployer file in your Git repository');

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        // -> Get logged-in service       
        $instance = \GitDeployer\AppInstance::getInstance();
        $appService = $instance->service();
        $appService->setInstances($input, $output, $this->getHelperSet());

        

    }

}