<?php
namespace GitDeployer\Deployers;

class DockerDeployer extends BaseDeployer {   

    //////////////////////////////////////////////////////////////////
    // Service functions
    //////////////////////////////////////////////////////////////////

    /**
     * Gets the help text that shows when "help deploy docker" is
     * executed by the user
     * @return strinf
     */
    public static function getHelp() {
        return <<<HELP
 The <info>docker</info> deployer allows you to deploy containers to Docker / Docker Swarm instances.
 It will use the <comment>Dockerfile</comment> in your repository to create an image to start the
 container from.

 For the <info>docker</info> deployer, we currently have the following configuration options:

 <comment>"host"</comment>:     (string) This variable overrides the DOCKER_HOST environment variable. If 
             you do not specify it, we will use the value from DOCKER_HOST instead.

             Valid formats are unix sockets, like <comment>unix:///var/run/docker.sock</comment>, and tcp 
             sockets, like <comment>tcp://127.0.0.1:2375</comment>.

 <comment>"restrart"</comment>: (string) This variable controls whether Docker will restart the container
             and under what condition. Possible values are: no (default), on-failure[:max-retries], 
             always, unless-stopped.

 <comment>"ssh"</comment>:      (object) This variable allows you to specify a SSH-tunnel that will be created
             and used to connect to the DOCKER_HOST variable. Only supported with tcp://-style URLs.
    
             You will need to specify the following sub-parameters:

             <comment>"tunnel"</comment>:     Set to true to enable SSH-tunneling (required).
             <comment>"user"</comment>:       The user for the SSH connection on the remote host (optional, will use "root" as default).
             <comment>"host"</comment>:       The remote host for the SSH connection (required).
             <comment>"port"</comment>:       The remote host port for the SSH connection (optional, will use 22 as default).
             <comment>"key"</comment>:        The SSH private key file for authentication to the remote host (required)
             <comment>"password"</comment>:   The password for the SSH private key file (optional, you will be asked for a password if needed)

 <comment>"ports"</comment>:    (array) Specifies the ports you want Docker to expose. If supports the complete Docker
             port description syntax, which is: [[hostIp:][hostPort]:]port[/protocol]. Examples:

             80
             80/tcp
             8080:80
             ...

HELP;

    }

    /**
     * Uses Docker to deploy the given project to a live server
     * @param  \GitDeployer\Objects\Project $project The project to deploy
     * @param  string                       $gitpath The path to the checked-out project
     * @param  array                        $config  The configuration options to pass to this deployer
     * @return mixed
     */
    public function deploy(\GitDeployer\Objects\Project $project, $gitpath, $config) {

        // -> Connect to the docker daemon on a tcp or unix socket
        if (!isset($config['host']) || strlen($config['host']) < 1) $config['host'] = getenv('DOCKER_HOST');
        if (strlen($config['host']) < 1) throw new \Exception('Neither the "host" parameter was specified in the .deployer file nor is the DOCKER_HOST environment variable set!');
        
        if (stristr($config['host'], 'tcp://')) {
            // Setting the docker host to tcp:// may enable usage of the SSH tunnel functionality
            if (isset($config['ssh']) && is_array($config['ssh'])) {
                if (isset($config['ssh']['tunnel']) && $config['ssh']['tunnel'] == true) {
                    parent::showMessage('DOCKER', 'Connecting to Docker daemon via SSH...', $this->output);

                    // Check if the ssh binary is executable, else bail out
                    // since we can't open a tunnel without it
                    if (!$this->command_exists('ssh') || !extension_loaded('expect')) {
                        throw new \Exception('SSH client not found: Please make sure the "ssh" command is available, and that you have installed the PHP expect extension!');
                    } else {
                        if (!isset($config['ssh']['host']) || strlen($config['ssh']['host']) < 1) throw new \Exception('Please specify at least a SSH host in your .deployerfile to connect to!');                        
                        if (!isset($config['ssh']['user']) || strlen($config['ssh']['user']) < 1) $config['ssh']['user'] = "root";
                        $config['ssh']['port'] = isset($config['ssh']['port']) && strlen($config['ssh']['port']) > 0 ? $config['ssh']['port'] : 22;

                        if (!isset($config['ssh']['privatekey']) || strlen($config['ssh']['privatekey']) < 1) throw new \Exception('Please correctly specify your SSH private key in the .deployerfile!');                        

                        // -> Open tunnel via SSH command
                        $randport = rand(60000, 65000);
                        $remotedesc = str_replace('tcp://', '', $config['host']);

                        $cmdstring = 'ssh -n -i ' . escapeshellarg($config['ssh']['privatekey']) . ' -L ' . $randport . ':' . $remotedesc . ' -p ' . $config['ssh']['port'] . ' ' . $config['ssh']['user'] . '@' . $config['ssh']['host'];                        

                        if (isset($config['ssh']['password']) || strlen($config['ssh']['password']) > 1) {
                            $stream = fopen('expect://' . $cmdstring, 'r');

                            $cases = array (
                                array ('Enter passphrase', PASSWORD)
                            );

                            ini_set("expect.timeout", -1);

                            switch (expect_expectl ($stream, $cases)) {
                                case PASSWORD:
                                    fwrite ($stream, $config['ssh']['password'] . "\n");

                                    // Wait for tunnel port to be available
                                    while(true) {
                                        $socket = @fsockopen('127.0.0.1', $randport, $errno, $errstr, 5);
                                           
                                        if ($socket) {
                                            fclose($socket);
                                            break;
                                        }
                                    }                                    

                                    break;
                                default:
                                    throw new \Exception('Unable to connect to the remote SSH host! Invalid string received: Expected passphrase prompt.');                                    
                            }
                        } else {
                            $stream = popen($cmdstring, 'r');                            

                            // Wait for tunnel port to be available
                            while(true) {
                                $socket = @fsockopen('127.0.0.1', $randport, $errno, $errstr, 5);
                                           
                                if ($socket) {
                                    fclose($socket);
                                    break;
                                }
                            }  
                        }
                    }
                }
            }
        }

        $client = new \Docker\Http\Client('tcp://127.0.0.1:' . $randport);
        $docker = new \Docker\Docker($client);

        // -> Build the docker image if a Dockerfile is present
        if (!file_exists($gitpath . '/Dockerfile')) {
            throw new \Exception('No Dockerfile found - aborting build!');
        }

        parent::showMessage('DOCKER', 'Building image (no-cache)...', $this->output);

        $context = new \Docker\Context\Context($gitpath);
        $apiResponse = $docker->build($context, 'git-deployer/' . $project->name(), false, false);

        if (!$apiResponse->getStatusCode() == 200) {
            throw new \Exception('Could not build docker image: Error ' . $apiResponse->getStatusText() );            
        }

        // -> Stop and remove the old container with the same name, sicne we're going
        // to replace the app here with the newly built container
        parent::showMessage('DOCKER', 'Getting running containers...', $this->output);

        $containersOnHost = $docker->getContainerManager()->findAll();
        
        if (count($containersOnHost) > 0) {
            // We check for a container with the same name as the one we are going to deploy
            foreach ($containersOnHost as $key => $container) {
                if ($container->getData()['Image'] == 'git-deployer/' . $project->name()) {
                    parent::showMessage('DOCKER', 'Stopping old container ' . $container->getId() .  '...', $this->output);

                    $docker->getContainerManager()->stop($container);
                    $docker->getContainerManager()->remove($container);
                }
            }
        }

        // -> Start the container up if we have built sucessfully
        parent::showMessage('DOCKER', 'Starting new container...', $this->output);

        $container = new \Docker\Container(['Image' => 'git-deployer/' . $project->name()]);

        // Add exposed ports from the config file, if any
        if (isset($config['ports']) && is_array($config['ports']) && count($config['ports']) > 0) {
            $portCollection = new \Docker\PortCollection();

            foreach ($config['ports'] as $portdesc) {
                $port = new \Docker\Port($portdesc);
                $portCollection->add($port);
            }

            $container->setExposedPorts($portCollection);
        }

        // Add restart policy
        $restartPolicy = 'no';
        if (isset($config['restart']) && strlen($config['restart']) > 0) $restartPolicy = $config['restart'];

        $docker->getContainerManager()->run($container, null, ['PortBindings' => $portCollection->toSpec(), 'RestartPolicy' => $this->parseRestartPolicy($restartPolicy)], true);

        // -> Clean up and close the SSH tunnel
        fclose ($stream);

        return array(
            true,
            'No trace'
        );

    }

    /**
     * Determines if a command exists on the current environment
     *
     * @param  string $command The command to check
     * @return bool
     */
    private function command_exists($command) {
        
        $whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';

        $process = proc_open($whereIsCommand . " " . $command, array(
          0 => array("pipe", "r"), //STDIN
          1 => array("pipe", "w"), //STDOUT
          2 => array("pipe", "w"), //STDERR
        ), $pipes);

        if ($process !== false) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $stdout != '';
        }

        return false;

    }

    /**
     * Parses a Docker restart policy and returns an array 
     * with the policy options
     * @param  string $policy The Docker restart policy
     * @return array
     */
    private function parseRestartPolicy($policy) {

        $policyExplode = explode(':', $policy);
        
        $policy = array(
            'Name' => $policyExplode[0]
        );

        if (isset($policyExplode[1]) && is_numeric($policyExplode[1]) && $policyExplode[0] == 'on-failure') {
            $policy['MaximumRetryCount'] = $policyExplode[1];
        }

        return $policy;

    }

}
