<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('deploy')
            ->setDescription('Deploys a Git repository to a remote server')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Which repository do you want to deploy?'
            )->addArgument(
                'revision',
                InputArgument::REQUIRED,
                'Which revision/tag do you want to deploy?'
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

        $hasBeenFound = false;

        foreach ($appService->getProjects() as $key => $project) {
            if ($project->name() == $repository) {
                // -> Once we have our project, check we are not deploying
                // the same version again
                $status = $storage->getDeploymentStatus($project);
                print_r($status);exit;

                // -> Now clone it to a temp directory and check the .deployerfile
                $hasBeenFound = true;
                break;
            }
        }

        if (!$hasBeenFound) {
            throw new \Exception('Project "' . $repository . '" could not be found! Please check your spelling!');        
        }
        
    }

}
