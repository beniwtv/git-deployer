<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class InfoCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('info')
            ->setDescription('Display more information of about a Git repository')
            ->addArgument(
                'repository',
                InputArgument::REQUIRED,
                'Which repository do you want to have information for?'
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

        // -> Get currently known repositories
        foreach ($appService->getProjects() as $key => $project) {
            if ($project->name() == $repository) {
                $status = $storage->getDeploymentStatus($project);

                // -> Print out status table
                $table = new Table($output);
                $table->setHeaders(array(
                    'Key',
                    'Value'
                ));

                // General information
                $output->writeln('<comment>General information about project</comment> <info>"' . $repository . '"</info>:' . "\n");

                $arrayKeys = array(
                    'Name'          => 'name',
                    'Description'   => 'description',
                    'Git URL'       => 'url',
                    'Homepage URL'  => 'homepage'
                );

                foreach ($arrayKeys as $text => $property) {
                    $table->addRow(array(
                        $text,
                        call_user_func(array($project, $property))
                    ));
                }        

                $table->addRow(array(
                    'Added to Git-Deployer?',
                    $status->added() ? 'Yes' : 'No'
                ));

                $table->render();

                // If available, print deployment information               
                if ($status->added()) {
                    $output->writeln("\n" . '<comment>Deployment information</comment>:' . "\n");

                    $table = new Table($output);
                    $table->setHeaders(array(
                        'Key',
                        'Value'
                    ));

                    $arrayKeys = array(
                        'Deployed version'  => 'getDeployedVersion',
                        'Deployment status' => 'getDeploymentInfo'
                    );

                    foreach ($arrayKeys as $text => $property) {
                        $table->addRow(array(
                            $text,
                            call_user_func(array($status, $property))
                        ));
                    }

                    $table->render();
                }

                exit(0);
            }
        }
        
        throw new \Exception('Project "' . $repository . '" could not be found! Please check your spelling!');       

    }

}