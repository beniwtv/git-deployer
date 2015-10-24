<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('remove')
            ->setDescription('Removes a Git repository from the Git-Deployer environment')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Which repository do you want to remove from Git-Deployer?'
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        $repository = $input->getArgument('repository');

        // -> Get logged-in service       
        $instance = \GitDeployer\AppInstance::getInstance();
        $appService = $instance->service();
        $appService->setInstances($input, $output, $this->getHelperSet());

        // .. and storage
        $storage = $instance->storage();       

        // -> Get the current projects, and check if
        // the project exists and has not yet been added to
        // Git-Deployer  
        $output->writeln('Removing project <info>"' . $repository . '"</info>...');

        // -> Once we have our project, add it to the deployer projects
        // This will fail if the project does not exist
        $storage->removeDeploymentStatusForProject($repository);
        $output->writeln('The project <info>"' . $repository . '"</info> was successfully removed from Git-Deployer!');

    }

}
