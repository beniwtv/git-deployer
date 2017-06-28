<?php
namespace GitDeployer\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class DeployCommand extends Command {
    
    protected function configure() {
        
        // -> Get arguments to command to see if we need to show
        // a deployer's help output
        $argv = $_SERVER['argv'];
        if (isset($argv[1]) && $argv[1] == 'help' && isset($argv[2]) && $argv[2] == 'deploy' && isset($argv[3]) && strlen($argv[3]) > 0) {
            // Load a deployer that matches our name
            $className = '\GitDeployer\Deployers\\' . ucwords(strtolower($argv[3])) . 'Deployer';
            if (!class_exists($className)) throw new \Exception('The deployer with the name' . ucwords(strtolower($argv[3])) . ' was not found!');

            $helpText = $className::getHelp();
        } elseif (isset($argv[1]) && $argv[1] == 'help' && isset($argv[2]) && $argv[2] == 'build' && isset($argv[3]) && strlen($argv[3]) > 0) {
            // Load a builder that matches our name
            $className = '\GitDeployer\Builders\\' . ucwords(strtolower($argv[3])) . 'Builder';
            if (!class_exists($className)) throw new \Exception('The builder with the name' . ucwords(strtolower($argv[3])) . ' was not found!');

            $helpText = $className::getHelp();
        } else {
            $helpText = <<<HELP
 The <info>%command.name%</info> command allows you to deploy a Git repository to a remote server.
 Please note that for this, your Git repository has to be added to Git-Deployer first. Check the <info>add</info> command for help.

 To use this command, you have to specify a valid <comment>repository</comment>, and a valid version string that tells Git-Deployer
 what exactly you want to deploy. This can be:

 - A <comment>tag</comment>, by specifying for example <info>tag:1.0</info>
 - A <comment>branch</comment>, by specifying for example <info>branch:bugfix-for-this</info>
 - A <comment>revision</comment>, by specifying for example <info>rev:c2356768</info>
 
 Also, you will need to have a .deployefile located in your repository. Check the <info>init</info> command for help.

HELP;
        }   

        $this
            ->setName('deploy')
            ->setAliases(array('build'))
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
            )->addOption(
                'bag',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Which saved parameter bag do you want to use for the deployment?'
            )->addOption(
                'force-redeploy',
                'f',
                InputOption::VALUE_NONE,
                'Don\'t ask for confirmation when re-deploying the same version'
            )->setHelp($helpText);

    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        
        $repository     = $input->getArgument('repository');
        $revision       = $input->getArgument('revision');
        $configuration  = $input->getOption('configuration');
        $bag            = $input->getOption('bag');
        $force          = $input->getOption('force-redeploy');

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

        // -> Check if we have a namespace declaration too, in that
        // case make sure we honor that
        if (stristr($repository, '/')) {
            $repoTmp    = explode('/', $repository);
            $namespace  = trim($repoTmp[0]);
            $repository = trim($repoTmp[1]);
        } else {
            $namespace  = null;
        }

        $this->showMessage('INIT', 'Getting repository...', $output);

        foreach ($appService->getProjects() as $key => $project) {
            if ($project->name() == $repository && $project->namespace() == $namespace) {                
                $hasBeenFound   = true;
                $status         = $storage->getDeploymentStatus($project);
            } elseif( $project->name() == $repository && $namespace == null ) {
                $hasBeenFound   = true;
                $status         = $storage->getDeploymentStatus($project);
            }
        }

        if ($hasBeenFound) {
            // -> Check if we have already been added to Git-Deployer yet
            if (!$status->added()) {
                throw new \Exception('This repository is not yet added to Git-Deployer!' . "\n" . 'Please add it first via the add command.');        
            }

            // -> Check we are not deploying the same version again. Ask
            // if we do, so the user can decide
            if ($status->isDeployd()) {
                if ($status->getDeployedVersion() == $revision && !$force) {
                    $helper     = $this->getHelper('question');
                    $question   = new ConfirmationQuestion('Version ' . $status->getDeployedVersion() . ' was already deployed on ' . $status->deployedWhen() . '. Continue anyway? (y/[n]) ', false);

                    if (!$helper->ask($input, $output, $question)) {
                        throw new \Exception('Aborting, not deploying same version,' . "\n" . 'as per your request!');
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

                // -> Get available configurations, and if not set, ask the user 
                // which configuration to use
                $configs = get_object_vars($deployerfile->configurations);

                if ($configuration == null && count(get_object_vars($deployerfile->configurations)) > 1) {
                    // If no configuration has been specified via command line,
                    // ask the user which one to use
                    $confignames = array();

                    foreach ($configs as $name => $values) {
                        $confignames[] = $name;
                    }

                    // -> Get storage service to use
                    $question = new ChoiceQuestion('Which deployment configuration would you like to use?', $confignames);
                    $question->setValidator(function ($answer) use($configs, $confignames) {
                        if (!isset($configs[$confignames[$answer]])) {
                            throw new \RuntimeException(
                                'Please select a correct value!'
                            );
                        }

                        return $confignames[$answer];
                    });

                    $configuration = $helper->ask($input, $output, $question);
                } elseif ($configuration != null) {
                    // Try to use the current specified configuration
                    if (!isset($configs[$configuration])) {
                        throw new \Exception('The configuration "' . $configuration . '" was not found in this .deployefile!');
                    }
                } else {
                    foreach ($configs as $name => $values) {
                        $configuration = $name;
                    }
                }            

                // -> Merge the current configuration inheritance chain, if any                
                if (!isset($deployerfile->inheritance)) {
                    $mergedConfig = json_decode(json_encode($configs[$configuration]), true);
                } else {
                    $chain          = $deployerfile->inheritance;
                    $minChainFound  = false; 
                    $smallerChain   = array();

                    foreach ($chain as $key) {
                        if ($key == $configuration) {
                            $minChainFound  = true;
                            $smallerChain[] = $key;
                        } else {
                            if ($minChainFound) $smallerChain[] = $key;
                        }
                    }

                    if (count($smallerChain) > 1) {
                        $mergedConfig = array();

                        foreach (array_reverse($smallerChain) as $configmerge) {
                            $mergedConfig = array_replace_recursive($mergedConfig, json_decode(json_encode($configs[$configmerge]), true));
                        }
                    } else {
                        $mergedConfig = json_decode(json_encode($configs[$smallerChain[0]]), true);                       
                    }
                }

                // -> Check if we have saved parameter bags, and offer the user
                // to choose one if so, or use the one provided from the command line
                $xdg = new \XdgBaseDir\Xdg();
                $bagPath = $xdg->getHomeConfigDir() . '/git-deployer';

                if ($bag != null) {
                    if (!file_exists($bagPath . '/' . $project->name() . '-' . $bag . '.bag')) {
                        throw new \Exception('This parameter bag has not been found!' . "\n" . 'Please check your naming!');
                    } else {
                        $answers = unserialize(file_get_contents($bagPath . '/' . $project->name() . '-' . $bag . '.bag'));
                    }
                } else {                    
                    $availableBags = array();

                    if (file_exists($bagPath)) {
                        $dh = opendir($bagPath);

                        while (($file = readdir($dh)) !== false) {
                            $fileInfo = pathinfo($file);

                            if ($fileInfo['extension'] == 'bag') {
                                if (stristr($fileInfo['filename'], $project->name())) {
                                    $availableBags[] = str_replace($project->name() . '-', '', $fileInfo['filename']);
                                }
                            }
                        }

                        closedir($dh);
                    }

                    if (count($availableBags) > 0) {
                        array_unshift($availableBags, "Don't use a bag");

                        $helper = $this->getHelper('question');

                        $question = new ChoiceQuestion('One or more parameter bags have been found. Which one do you want to use?', $availableBags);
                        $question->setValidator(function ($answer) use($availableBags) {
                            if ($answer == 0) return false;
                            if (!isset($availableBags[$answer])) {
                                throw new \RuntimeException(
                                    'Please select a correct value!'
                                );
                            }

                            return $availableBags[$answer];
                        });

                        $parameterBag = $helper->ask($input, $output, $question);

                        if ($parameterBag) {
                            $answers = unserialize(file_get_contents($bagPath . '/' . $project->name() . '-' . $parameterBag . '.bag'));
                        } else {
                            $answers = array();
                        }
                    } else {
                        $answers = array();
                    }
                }

                // -> Replace placeholders in our config using parameter bags
                $bagModified = false;

                if (isset($deployerfile->parameters)) {
                    foreach ($deployerfile->parameters as $key => $questionhelp) {
                        if (!isset($answers[$key])) {
                            $helper = $this->getHelper('question');

                            $question = new Question($questionhelp . ' ');

			                $questionanswer = $helper->ask($input, $output, $question);
			                
                            if ($questionanswer == null) {
                                $answers[$key] = '';
                            } else {
                                $answers[$key] = $questionanswer;
                            }

                            $bagModified = true;
                        }
                    }

                    // -> Ask the user to save this parameter bag
                    if ($bagModified) {
                        $helper     = $this->getHelper('question');
                        $question   = new ConfirmationQuestion('Do you want to save these answers in a parameter bag for next time? ([y]/n) ', true);

                        if ($helper->ask($input, $output, $question)) {
                            $question = new Question('Please provide a name for the new (or existing, will overwrite) parameter bag: ');
                            $question->setValidator(function ($answer) {
                                if (strlen($answer) < 1) {
                                    throw new \RuntimeException('Please provide a name for this parameter bag!');
                                } else {
                                    if (!preg_match('#\w+#iu' , $answer)) {
                                        throw new \RuntimeException('The name provided for this parameter bag is invalid!');
                                    }
                                }

                                return $answer;
                            });

                            $bagname = $helper->ask($input, $output, $question);

                            // -> Save this bag!
                            $xdg = new \XdgBaseDir\Xdg();
                            $savePath = $xdg->getHomeConfigDir() . '/git-deployer';
                            $saveFile = $savePath . '/' . $project->name() . '-' . $bagname . '.bag';

                            if (!file_exists($savePath)) mkdir($savePath);
                            file_put_contents($saveFile, serialize($answers));
                        }
                    }

                    // -> Now traverse the array to replace the values, and as such
                    // get our merged config ready to pass it into the deployer
                    $replacedConfig = $this->replacePlaceholders($mergedConfig, $answers);
                }

                // -> Execute the requested deployer with our merged configuration array
                $deployer = \GitDeployer\Deployers\BaseDeployer::createServiceInstance(ucwords($deployerfile->type), $input, $output, $this->getHelperSet());
                list($statusok, $trace) = $deployer->deploy($project, $tmpDir, $replacedConfig);

            // -> Check if the deployment went well, return any errors,
            // or update the deployment status for the project otherwise
            if ($statusok) {
                $status->deployedWhen(date('c'));
                $status->deployedType($type);
                $status->deployedString($version);

                $storage->setDeploymentStatus($project, $status);
            } else {
                $output->writeln($trace);
                throw new \Exception('Deployment did not completely finish! See trace above.');
            }

            // -> Finish up!
            $this->showMessage('FINISH', '<info>Done!</info>', $output);
            break;
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

    /**
     * Replaces placeholders in the config with their values
     * @param  mixed $object       The object to replace placeholders in
     * @param  array $replacements The placeholders & values to replace
     * @return mixed
     */
    private function replacePlaceholders($object, $replacements) {

        // Get our keys & replacements
        $keys = array_map(function ($m) {
            return '%' . $m . '%';
        }, array_keys($replacements));

        $replaces = array_values($replacements);

        foreach ($object as $key => $value) {
            if (is_array($value)) {
                $object[$key] = $this->replacePlaceholders($value, $replacements);
            } else {
                $object[$key] = str_replace($keys, $replaces, $value);
            }
        }

        return $object;

    }

}
