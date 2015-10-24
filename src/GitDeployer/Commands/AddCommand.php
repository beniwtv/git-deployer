<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('add')
            ->setDescription('Adds a Git repository to the Git-Deployer environment')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Which repository do you want to add to Git-Deployer?'
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
        $output->writeln('Adding new project <info>"' . $repository . '"</info>...');

        $hasBeenFound = false;

        foreach ($appService->getProjects() as $key => $project) {
            if ($project->name() == $repository) {
                // -> Once we have our project, add it to the deployer projects
                // This will fail if the project was already added
                $storage->addNewDeploymentStatus($project);
                $output->writeln('The project <info>"' . $repository . '"</info> was successfully added to Git-Deployer!');

                $hasBeenFound = true;
                break;
            }
        }

        if (!$hasBeenFound) {
            throw new \Exception('Project "' . $repository . '" could not be found! Please check your spelling!');        
        }
        
    }

}
