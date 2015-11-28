<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeployCommand extends Command {
    
    protected function configure() {
        
        // -> Get arguments to command to see if we need to show
        // a deployer's help output
        $argv = $_SERVER['argv'];
        if ($argv[1] == 'help' && isset($argv[2]) && $argv[2] == 'deploy' && isset($argv[3]) && strlen($argv[3]) > 0) {
            // Load a deployer that matches our name
            $className = '\GitDeployer\Deployers\\' . ucwords(strtolower($argv[3])) . 'Deployer';
            if (!class_exists($className)) throw new \Exception('The deployer with the name' . ucwords(strtolower($argv[3])) . ' was not found!');

            $helpText = $className::getHelp();
        } else {
            $helpText = <<<HELP
 The <info>%command.name%</info> command allows you to deploy a Git repository to a remote server.
 Please note that for this, your Git repository has to be added to Git-Deployer fist. Check the <info>add</info> command for help.

 To use this command, you have to specify a valid <comment>repository</comment>, and a valid version string that tells Git-Deployer
 what exactly you want to deploy. This can be:

 - A <comment>tag</comment>, by specifying for example <info>tag:1.0></info>
 - A <comment>branch</comment>, by specifying for example <info>branch:bugfix-for-this</info>
 - A <comment>revision</comment>, by specifying for example <info>rev:c2356768</info>
 
 Also, you will need to have a .deployefile located in your repository. Check the <info>init</info> command for help.

HELP;
        }   

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
                'Which revision/tag/branch do you want to deploy?'
            )->addOption(
                'configuration',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Which configuration do you want to use for the deployment?'
            )->setHelp($helpText);

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        $repository = $input->getArgument('repository');
        $revision = $input->getArgument('revision');
        $configuration = $input->getOption('configuration');

        // -> Check that the revision matches what we expect
        preg_match('#^(tag|branch|rev):(.*)#iu' , $revision, $matches);

        if (count($matches) < 3) {
            throw new \Exception('You must correctly specify the tag/branch/revision you want to deploy!' . "\n" . 'Check "help deploy" for an explanation.');        
        } else {
            $type = $matches[1];
            $version = $matches[2];
        }

        // -> Get logged-in service       
        $instance = \GitDeployer\AppInstance::getInstance();
        $appService = $instance->service();
        $appService->setInstances($input, $output, $this->getHelperSet());

        // .. and storage
        $storage = $instance->storage();       

        $hasBeenFound = false;

        $this->showMessage('INIT', 'Getting repository...', $output);

        foreach ($appService->getProjects() as $key => $project) {
            if ($project->name() == $repository) {                
                $hasBeenFound   = true;
                $status         = $storage->getDeploymentStatus($project);
                
                // -> Check if we have already been added to Git-Deployer yet
                if (!$status->added()) {
                    throw new \Exception('This repository is not yet added to Git-Deployer!' . "\n" . 'Please add it first via the add command.');        
                }

                // -> Check we are not deploying the same version again. Ask
                // if we do, so the user can decide 
                if ($status->isDeployd()) {
                    if ($status->getDeployedVersion() == $revision) {
                        $continue = false; 

                        $helper     = $this->getHelper('question');
                        $question   = new ConfirmationQuestion('Version ' . $status->getDeployedVersion() . ' was already deployed on ' . $status->deployedWhen() . '. Continue anyway? (y/n) ', false);

                        if (!$helper->ask($input, $output, $question)) {
                            break;
                        }
                    }
                }                               
                
                // -> Now clone it to a temp directory, if it doesn't exist already
                $tmpDir = sys_get_temp_dir() . '/git-deploy-' . strtolower($project->name());

                if (\Gitonomy\Git\Admin::isValidRepository($tmpDir)) {      
                    $this->showMessage('GIT', 'Pulling latest changes from repository...', $output);

                    $repository = \Gitonomy\Git\Admin::init($tmpDir, false);
                    $repository->run('pull');
                } else {
                    $this->showMessage('GIT', 'Cloning repository...', $output);
                    $repository = \Gitonomy\Git\Admin::cloneTo($tmpDir, $project->url(), false);
                }

                // -> Check out the correct branch/revision/tag
                $this->showMessage('GIT', 'Checking out ' . $revision . '...', $output);                
                
                $wc = $repository->getWorkingCopy();
                $wc->checkout($version);

                // -> Open .deployerfile and parse it
                $this->showMessage('DEPLOY', 'Checking .deployerfile...', $output);

                if (file_exists($tmpDir . '/.deployerfile')) {
                    $deployerfile = json_decode(file_get_contents($tmpDir . '/.deployerfile'));
                } else {
                    throw new \Exception('This repository has no .deployerfile!' . "\n" . 'Please add one first!');
                }

                if ($deployerfile == null) {
                    throw new \Exception('Could not parse .deployerfile: '. json_last_error_msg() . "\n" . 'Please check that your JSON is valid!');
                }

                // -> Load the correct deployer from the .deployerfile, and fire it up with
                // the correct configuration options
                if (!isset($deployerfile->type)) {
                    throw new \Exception('Could not parse .deployerfile: ' . "\n" . 'Please specify a deployer via the "type" option!');
                }

                if (!isset($deployerfile->configurations) || (isset($deployerfile->configurations) && !is_object($deployerfile->configurations))) {
                    throw new \Exception('Could not parse .deployerfile: ' . "\n" . 'Please specify at least one deployment configuration in the "configurations" option!');
                }

                // -> Merge the current configuration inheritance chain, if any
                if (count(get_object_vars($deployerfile->configurations)) > 1) {
                    // If no configuration has been specified via command line,
                    // ask the user which one to use
                    if ($configuration == null) {
                        $continue = false; 

                        $helper     = $this->getHelper('question');
                        $question   = new ConfirmationQuestion('Version ' . $status->getDeployedVersion() . ' was already deployed on ' . $status->deployedWhen() . '. Continue anyway? (y/n)', false);

                        if (!$helper->ask($input, $output, $question)) {
                            break;
                        }
                    }
                }       

                $deployer = \GitDeployer\Deployers\BaseDeployer::createServiceInstance(ucwords($deployerfile->type), $input, $output, $this->getHelperSet());
                list($statusok, $trace) = $deployer->deploy($project, $tmpDir);

                // -> Check if the deployment went well, return any errors,
                // or update the deployment status for the project otherwise
                if ($statusok) {
                    $status->deployedWhen(date('c'));
                    $status->deployedType($type);
                    $status->deployedString($version);

                    $storage->setDeploymentStatus($project, $status);
                }

                // -> Finish up!
                $this->showMessage('FINISH', '<info>Done!</info>', $output);
                break;
            }
        }

        if (!$hasBeenFound) {
            throw new \Exception('Project "' . $repository . '" could not be found! Please check your spelling!');        
        }
        
    }

    /**
     * Shows a message on the terminal
     * @param  string          $section  "Section" for the message
     * @param  string          $message  The message to display
     * @param  OutputInterface $output   The Symfony output interface
     */
    private function showMessage($section, $message, $output, $style = 'info') {

        $formatter = $this->getHelper('formatter');
        $formattedLine = $formatter->formatSection(
            str_pad($section, 6, ' ', STR_PAD_LEFT),
            $message,
            $style
        );

        $output->writeln($formattedLine);

    }

}
