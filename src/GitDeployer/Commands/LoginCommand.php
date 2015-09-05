<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoginCommand extends Command {
    
    protected function configure() {

        // Get available VCS services to display to the user
        $services = \GitDeployer\Services\BaseService::getVCSServicesForHelp();

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
            // -> We need to throw an error if we're loggin in to another
            // service than the currently logged in!
            $instance = \GitDeployer\AppInstance::getInstance();
            $appService = $instance->service();

            preg_match('#.*\.*\\\(.*)Service#', get_class($appService), $matches);

            if ($matches[1] != $service) {
                $errorMessages = array(
                    '',
                    '[Error]',
                    'You are already logged in to ' . $matches[1] . '!',
                    'To change to another service, please logout first.',
                    ''
                );

                $formatter = $this->getHelper('formatter');
                $formattedBlock = $formatter->formatBlock($errorMessages, 'error');
                $output->writeln($formattedBlock);
                exit(1);
            }

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
        $output->writeln('Don\'t forget to configure your Git-Deployer instance by executing the <info>config</info> command!');

    }

}