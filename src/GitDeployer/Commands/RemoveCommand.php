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
        // the project exists and has been added to
        // Git-Deployer
        $hasBeenFound = false;

        // -> Check if we have a namespace declaration too, in that
        // case make sure we honor that
        if (stristr($repository, '/')) {
            $repoTmp    = explode('/', $repository);
            $namespace  = trim($repoTmp[0]);
            $repository = trim($repoTmp[1]);
        } else {
            $namespace  = null;
        }

        foreach ($appService->getProjects() as $key => $project) {
            if ($project->name() == $repository && $project->namespace() == $namespace) {
                $output->writeln('Removing project <info>"' . $repository . '"</info>...');

                // -> Once we have our project, add it to the deployer projects
                // This will fail if the project does not exist
                $storage->removeDeploymentStatusForProject($project);
                $output->writeln('The project <info>"' . $repository . '"</info> was successfully removed from Git-Deployer!');

                $hasBeenFound = true;
                break;
            } elseif( $project->name() == $repository && $namespace == null ) {
                $output->writeln('Removing project <info>"' . $repository . '"</info>...');

                // -> Once we have our project, add it to the deployer projects
                // This will fail if the project does not exist
                $storage->removeDeploymentStatusForProject($project);
                $output->writeln('The project <info>"' . $repository . '"</info> was successfully removed from Git-Deployer!');

                $hasBeenFound = true;
                break;
            }
        }

        if (!$hasBeenFound) {
            throw new \Exception('Project "' . $repository . '" could not be found! Please check your spelling!');        
        }   

    }

}
