<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ConfigCommand extends Command {
    
    protected function configure() {       

        // -> Intro
        $this
            ->setName('config')
            ->setDescription('Configure Git-Deployer')           
            ->setHelp(
              <<<HELP
 The <info>%command.name%</info> command allows you to configure Git-Deployer.
HELP
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        // -> We check if there is already an app instance, else we
        // can not configure git-deployer yet
        $hasStorage = false;

        try {
            // -> We need to throw an error if we're loggin in to another
            // service than the currently logged in!
            $instance = \GitDeployer\AppInstance::getInstance();
            $storageService = $instance->storage();            

            if (is_object($storageService) && $storageService instanceof \GitDeployer\Storage\BaseStorage) {
                $hasStorage = str_replace(array('GitDeployer\Storage\\', 'Storage'), array('', ''), get_class($storageService));
            }
        } catch(\Exception $e) {
            throw new \RuntimeException(
                'Please log-in to a service first!'
            );
        }

        $output->writeln(
            <<<INTRO
Welcome to the configuration wizard for Git-Deployer!
Let's get started!

INTRO
        );

        // -> Storage service
        
        // Get available storage services to display to the user
        $services = \GitDeployer\Storage\BaseStorage::getStorageServicesForHelp();

        $output->writeln(
            <<<INTRO
First, you will need to configure a <info>storage service</info>:

        A <info>storage service</info> is responsible of storing Git-Deployer's state,
    i   such as what repositories are deployed, which version/tag was deployed, where
        they are deployed, etc...

The following <info>storage services</info> exist in this build:

<comment>$services</comment>
INTRO
        );

        $services = \GitDeployer\Storage\BaseStorage::getStorageServicesForIterating();
        $default = ($hasStorage ? array_search($hasStorage, $services) : 0 );

        $helper = $this->getHelper('question');
        
        // -> Get storage service to use
        $question = new ChoiceQuestion('Which storage service would you like to use?', $services, $default);
        $question->setValidator(function ($answer) use($services) {
            if (!isset($services[$answer])) {
                throw new \RuntimeException(
                    'Please select a correct value!'
                );
            }

            return $answer;
        });

        $number = $helper->ask($input, $output, $question);

        // If we selected the already existing storage instance, load
        // it up so we can see the already defined values
        if ($hasStorage && $number == $default) {
            $storageService->setInstances($input, $output, $this->getHelperSet());
        } else {
            $storageService = \GitDeployer\Storage\BaseStorage::createServiceInstance($services[$number], $input, $output, $this->getHelperSet());
        }
        
        // -> Now that we have a storage service, add this to 
        // the AppInstance, to be opened later again
        if ($storageService->configure()) {
            $instance->storage($storageService)                
                     ->save();
        }
        
        $output->writeln(
            <<<OUTRO

This finishes the configuration of Git-Deployer!
Thank you!
OUTRO
        );

    }

}
