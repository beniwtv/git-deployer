<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command {
    
    protected function configure() {

        $deployers = \GitDeployer\Deployers\BaseDeployer::getDeployersForHelp();

        $this
            ->setName('init')
            ->setDescription('Initializes a skeleton .deployerfile in your Git repository')
            ->setHelp(
              <<<HELP
 The <info>%command.name%</info> command allows you to initialize a skeletion .deployefile in your repository.
 This file contains the information that Git-Deployer needs to deploy your project.
 
 A typical .deployerfile looks like this (remove comments, as they are not valid for JSON):

 {
    # The deployers to use. This version of Git-Deployer supports the following deployers:
    # <comment>$deployers</comment>
    "type": "docker",

    # The configurations object is used to specify multiple configurations
    "configurations": {

        # You can add an arbitrary number of configurations, with any names in this section
        "production": {

            # Each deployer has it's own configuration options, specify them here. In our "docker"
            # example, we could specify the DOCKER_HOST variable, for example
            "host": "tcp://127.0.0.1:2375",

            # In order to not save any passwords or private data into the .deployerfile, you can use
            # parameter substitution. These parameters will be asked on deploy by Git-Deployer:
            "supersecret": "%subsituteme%"
        },
        "staging": {

            # You can override properties from other configurations
            # example, we could specify the DOCKER_HOST variable, for example
            "host": "tcp://192.168.0.1:2375"
        }
    },

    # For overriding to be able to work, you need to specify an inheritance chain, like so:
    inheritance": ["staging", "production"],

    # If you have any parameter substitutions, add them here with a description, so that
    # the user deploying knows what to put in:
    "parameters": {
        "subsituteme": "Example param question description?"
    }
 }

 For a list of configuration options for each deployer, you can use the deploy command help,
 for example, for the Docker deployer you would type:
 <info>help deploy docker</info>

HELP
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        if (getcwd()) {
            if (\Gitonomy\Git\Admin::isValidRepository(getcwd())) {
                // Repo is valid, drop the bare example file there
                copy(__DIR__ . '/../../../stuff/deployerfile.json.template', getcwd() . '/deployerfile');
                $output->writeln('Repository initialized!');
            } else {
                throw new \Exception('"' . getcwd() . '" does not appear to contain a Git repository!');
            }
        } else {
            throw new \Exception('Could not access "' . getcwd() . '"');
        }

    }

}
