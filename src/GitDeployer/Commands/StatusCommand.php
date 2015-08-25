<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class StatusCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('status')
            ->setDescription('Display the current deployment status of your Git repositories');

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        // -> Get logged-in service       
        $instance = \GitDeployer\AppInstance::getInstance();
        $appService = $instance->service();
        $appService->setInstances($input, $output, $this->getHelperSet());

        // .. and storage
        $storage = $instance->storage();

        $projects = $appService->getProjects();
        
        // -> Print out status table
        $table = new Table($output);
        $table->setHeaders(array(
            '#',
            'Name',
            'Version',
            'Status'
        ));

        foreach ($projects as $key => $project) {
            $table->addRow(array(
                $key,
                $project->name(),
                $storage->getDeployedVersion($project),
                $storage->getDeploymentStatus($project) ? $storage->getDeploymentStatus($project) : 'Not added to Git-Deployer yet',
            ));   
        }

        $table->render();

    }

}