<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LogoutCommand extends Command {
    
    protected function configure() {

        $this
            ->setName('logout')
            ->setDescription('Log out of the Git service (if logged in)');

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        // -> We check if there is already an app instance, and use that
        // if it exists to log-in or pre-populate data
        try {
            $instance = \GitDeployer\AppInstance::getInstance();
            $instance->delete();
        } catch(\Exception $e) {
            // No app instance? We're done here.
        }

        $output->writeln('You have successfully logged out!');

    }

}