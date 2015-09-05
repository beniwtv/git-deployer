<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
    ❓   such as what repositories are deployed, which version/tag was deployed, where
        they are deployed, etc...

The following <info>storage services</info> exist in this build:

<comment>$services</comment>
INTRO
        );

        $services = \GitDeployer\Storage\BaseStorage::getStorageServicesForIterating();
        $helper = $this->getHelper('question');
        
        // -> Get storage service to use
        $question = new ChoiceQuestion('Which storage service would you like to use?', $services);
        $question->setValidator(function ($answer) use($services) {
            if (!isset($services[$answer])) {
                throw new \RuntimeException(
                    'Please select a correct value!'
                );
            }

            return $answer;
        });

        $number = $helper->ask($input, $output, $question);
        $storageService = \GitDeployer\Storage\BaseStorage::createServiceInstance($services[$number], $input, $output, $this->getHelperSet());
        $storageService->configure();
        
        print_r($storageService);
    }

}