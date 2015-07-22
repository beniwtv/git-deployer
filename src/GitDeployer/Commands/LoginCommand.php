<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoginCommand extends Command {
    
    protected function configure() {

        // Get available services to display to the user
        $services = \GitDeployer\Services\BaseService::getServicesForHelp();

        $this
            ->setName('login')
            ->setDescription('Login to a Git service')
            ->addArgument(
                'service',
                InputArgument::REQUIRED,
                'Which service do you want to use?'
            )
            ->setHelp(
              <<<HELP
 The <info>%command.name%</info> command allows you to log-in to a Git service like
 GitLab, GitHub, etc... The following <info>services</info> exist in this build:

 <comment>$services</comment>

 Once you know which provider you would like to use, simply execute 
 the <info>%command.name%</info> command as follows: 

    ./git-deployer <info>%command.name%</info> <service>
HELP
            );

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        $service = $input->getArgument('service');

        // -> We check if there is already an app instance, and use that
        // if it exists to log-in or pre-populate data
        try {
            $instance = \GitDeployer\AppInstance::getInstance();
            $appService = $instance->service();
            $appService->setInstances($input, $output, $this->getHelperSet());
        } catch(\Exception $e) {
            $instance = new \GitDeployer\AppInstance();

            // -> We first create a new instance of the service, and let it
            // configure itself (it may ask questions, etc...)
            $appService = \GitDeployer\Services\BaseService::createServiceInstance($service, $input, $output, $this->getHelperSet());
        }

        $appService->login();

        // -> Once the configuration goes through (no exception thrown), we
        // can save the service to the AppInstance, and save it to the file system        
        $instance->service($appService)
                 ->save();       

        $output->writeln('You have successfully logged in to <info>' . $service . '</info>');

    }

}