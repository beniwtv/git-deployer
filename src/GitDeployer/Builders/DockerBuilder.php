<?php
namespace GitDeployer\Builders;

class DockerBuilder extends BaseBuilder {   

    //////////////////////////////////////////////////////////////////
    // Service functions
    //////////////////////////////////////////////////////////////////

    /**
     * Gets the help text that shows when "help build docker" is
     * executed by the user
     * @return string
     */
    public static function getHelp() {
        return <<<HELP
 The <info>docker</info> builder allows you to build Docker images, and push it to any registry you need.
 It will use the <comment>Dockerfile</comment> in your repository to create this image.

 For the <info>docker</info> builder, we currently have the following configuration options:

 <comment>"host"</comment>:        (string) This variable overrides the DOCKER_HOST environment variable. If 
                you do not specify it, we will use the value from DOCKER_HOST instead.

                Valid formats are unix sockets, like <comment>unix:///var/run/docker.sock</comment>, and tcp/tls 
                sockets, like <comment>tcp://127.0.0.1:2375</comment> or <comment>tls://127.0.0.1:2375</comment>.

 <comment>"ssh"</comment>:         (object) This variable allows you to specify a SSH-tunnel that will be created
                and used to connect to the DOCKER_HOST variable. Only supported with tcp:// or tls://-style URLs.
    
                You will need to specify the following sub-parameters:

                <comment>"tunnel"</comment>:     Set to true to enable SSH-tunneling (required).
                <comment>"user"</comment>:       The user for the SSH connection on the remote host (optional, will use "root" as default).
                <comment>"host"</comment>:       The remote host for the SSH connection (required).
                <comment>"port"</comment>:       The remote host port for the SSH connection (optional, will use 22 as default).
                <comment>"key"</comment>:        The SSH private key file for authentication to the remote host (required)
                <comment>"password"</comment>:   The password for the SSH private key file (optional, you will be asked for a password if needed)

 <comment>"push"</comment>:        (object) This variable allows you to push the image to a remote Docker registry
                after building, in case this is another Docker daemon than the one used for deployment.

HELP;

    }

    /**
     * Uses Docker to build the given project as a Docker image
     * @param  \GitDeployer\Objects\Project $project The project to build
     * @param  string                       $gitpath The path to the checked-out project
     * @param  array                        $config  The configuration options to pass to this builder
     * @return mixed
     */
    public function build(\GitDeployer\Objects\Project $project, $gitpath, $config) {

        $useTunnel  = false;
        $dockerHost = -1;
        $dockerPort = -1;

        // -> Connect to the docker daemon on a tcp or unix socket
        if (!isset($config['host']) || strlen($config['host']) < 1) $config['host'] = getenv('DOCKER_HOST');
        if (strlen($config['host']) < 1) throw new \Exception('Neither the "host" parameter was specified in the .deployer file nor is the DOCKER_HOST environment variable set!');
        
        if (stristr($config['host'], 'tcp://') || stristr($config['host'], 'tls://')) {
            // Setting the docker host to tcp:// may enable usage of the SSH tunnel functionality
            if (isset($config['ssh']) && is_array($config['ssh'])) {
                if (isset($config['ssh']['tunnel']) && $config['ssh']['tunnel'] == true) {
                    $useProc    = false;
                    $useTunnel  = true;
                    $dockerHost = '127.0.0.1';

                    parent::showMessage('DOCKER', 'Connecting to Docker daemon via SSH...', $this->output);

                    // Check if the ssh binary is executable, else bail out
                    // since we can't open a tunnel without it
                    if (!$this->commandExists('ssh')) {
                        throw new \Exception('SSH client not found: Please make sure the "ssh" command is available, and in your $PATH!');
                    } else {
                        if (!isset($config['ssh']['host']) || strlen($config['ssh']['host']) < 1) throw new \Exception('Please specify at least a SSH host in your .deployerfile to connect to!');                        
                        if (!isset($config['ssh']['user']) || strlen($config['ssh']['user']) < 1) $config['ssh']['user'] = "root";
                        $config['ssh']['port'] = isset($config['ssh']['port']) && strlen($config['ssh']['port']) > 0 ? $config['ssh']['port'] : 22;

                        if (!isset($config['ssh']['key']) || strlen($config['ssh']['key']) < 1) throw new \Exception('Please correctly specify your SSH private key in the .deployerfile!');                        

                        // -> Open tunnel via SSH command
                        $randport   = rand(60000, 65000);
                        $remotedesc = str_replace(array('tcp://', 'tls://'), '', $config['host']);

                        // Assume standard port if not provided
                        if (count(explode(':', $remotedesc)) < 2) {
                            $remotedesc = rtrim($remotedesc, '/') . ':' . ( stristr($dockerHost, 'tls://') ? '2376' : '2375' );
                        }

                        $cmdstring  = 'ssh -N -i ' . escapeshellarg($config['ssh']['key']) . ' -L ' . $randport . ':' . $remotedesc . ' -p ' . $config['ssh']['port'] . ' ' . $config['ssh']['user'] . '@' . $config['ssh']['host'];                        
                        
                        if (isset($config['ssh']['password']) && strlen($config['ssh']['password']) > 1) {
                            if (!extension_loaded('expect')) {
                                throw new \Exception('Expect extension not found: Please make sure the PHP expect extension is available in your PHP installation!');
                            }

                            $stream = fopen('expect://' . $cmdstring, 'r');

                            $cases = array (
                                array ('Enter passphrase', PASSWORD)
                            );

                            ini_set("expect.timeout", 30);

                            switch (expect_expectl ($stream, $cases)) {
                                case PASSWORD:
                                    fwrite ($stream, $config['ssh']['password'] . "\n");

                                    // Wait for tunnel port to be available
                                    while (true) {
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
                            $stream = proc_open('exec ' . $cmdstring, array(), $pipes);   
                            $useProc = true;

                            // Wait for tunnel port to be available
                            while (true) {
                                $socket = @fsockopen('127.0.0.1', $randport, $errno, $errstr, 5);

                                if ($socket) {
                                    fclose($socket);
                                    break;
                                }
                            }                            
                        }    

                        $dockerHost = ( stristr($config['host'], 'tls://') ? '2376' : '2375' ) . '://127.0.0.1:' . $randport;                    
                    }
                }
            } else {
                if (!stristr($config['host'], 'tcp://') && !stristr($config['host'], 'tls://')) throw new \Exception('Unable to parse host string: Only tcp:// and tls:// URLs are supported for remote Docker hosts!');
                $dockerHost = $config['host'];
            }
        } else {
            $dockerHost = $config['host'];
        }

        // Assume standard port if not provided
        if ((stristr($dockerHost, 'tcp://') || stristr($dockerHost, 'tls://')) && count(explode(':', $dockerHost)) < 3) {
            $dockerHost = rtrim($dockerHost, '/') . ':' . ( stristr($dockerHost, 'tls://') ? '2376' : '2375' );
        }

        $client = @stream_socket_client($dockerHost, $errno, $errstr);
       
        if (!$client) {
            throw new \Exception('Could not connect to Docker: ' . $errstr . ' (' . $errno . ')!');
        }    

        // -> Build the docker image if a Dockerfile is present
        if (!file_exists($gitpath . '/Dockerfile')) {
            throw new \Exception('No Dockerfile found - aborting build!');
        }

        parent::showMessage('DOCKER', 'Building image (no-cache)...', $this->output);
        parent::showMessage('DOCKER', 'Uploading context...', $this->output);

        // Need a TAR.GZ with the contents of the directory
        $tarData    = $this->getDockerContextAsTar($gitpath);
        $imagename  = "gdp-" . $this->cleanName($project->name());

        $context  = "POST /build?t=" . $imagename . "&nocache=true HTTP/1.0\r\n";
        $context .= "Accept: */*\r\n";
        $context .= "Content-Type: application/x-tar\r\n";
        $context .= "Content-Length: " . strlen($tarData) . "\r\n\r\n";
        $context .= $tarData;

        /*fwrite($client, $context);

        // Display output log of Docker daemon while building
        while (!feof($client)) {
            $jsonstring = stream_get_line($client, 2048, "\n");

            if ($data = json_decode($jsonstring)) {
                if (isset($data->stream)) {
                    echo $data->stream;
                } elseif (isset($data->error)) {
                    throw new \Exception('BUILD FAILED: ' . $data->error);
                }
            }
        }*/

        fclose($client);
       
        // -> Push image to registry, if it's not the real Docker host
        // that is going to be used for deployment
        if (isset($config['push']) && is_array($config['push'])) {
            if (!isset($config['push']['remote'])) throw new \Exception('You need to specify a repository to push the image to!');

            // ->  Tag the image with the remote registry first
            parent::showMessage('DOCKER', 'Tagging image for remote push...', $this->output);

            // Open a new client
            $client = @stream_socket_client($dockerHost, $errno, $errstr);
       
            if (!$client) {
                throw new \Exception('Could not connect to Docker: ' . $errstr . ' (' . $errno . ')!');
            }

            $context  = "POST /images/" . $imagename . "/tag?repo=" . rawurlencode($config['push']['remote'] . '/' . $imagename) . " HTTP/1.0\r\n";
            $context .= "Accept: */*\r\n\r\n";

            /*fwrite($client, $context);

            while (!feof($client)) {
                echo stream_get_line($client, 2048, "\n");
            }*/

            fclose($client);

            // -> Now push the image to the registry
            parent::showMessage('DOCKER', 'Pushing image to remote (this may take a while)...', $this->output);

            // Open a new client
            $client = @stream_socket_client($dockerHost, $errno, $errstr);
       
            if (!$client) {
                throw new \Exception('Could not connect to Docker: ' . $errstr . ' (' . $errno . ')!');
            }

            $authJson = json_encode(array(
                "username"      => $config['push']['username'],
                "password"      => $config['push']['password'],
                "email"         => $config['push']['email'],
                "serveraddress" => $config['push']['remote']
            ));

            $context  = "POST /images/" . rawurlencode($config['push']['remote'] . '/' . $imagename) . "/push HTTP/1.0\r\n";
            $context .= "X-Registry-Auth: " . base64_encode($authJson) . "\r\n";
            $context .= "Accept: */*\r\n\r\n";

            fwrite($client, $context);

            $preparingStrings = array();

            while (!feof($client)) {
                $jsonstring = stream_get_line($client, 2048, "\n");

                if ($data = json_decode($jsonstring)) {
                    if (isset($data->status) && !isset($data->id)) {
                        echo $data->status . "\n";
                    } elseif (isset($data->status) && isset($data->id)) {                        
                        if ($data->status == 'Preparing') {
                            $bar = new \Symfony\Component\Console\Helper\ProgressBar($this->output, 100);
                            $bar->setFormat("%message% %current%/%max% [%bar%]");
                            $bar->setOverwrite(false);

                            $preparingStrings[$data->id] = array(
                                'bar'       => $bar,
                                'status'    => 'Preparing',
                                'progress'  => 0
                            );                            
                        } elseif ($data->status == 'Waiting') {
                            // Set our bar to waiting
                            $preparingStrings[$data->id]['status'] = 'Waiting';
                        } elseif ($data->status == 'Layer already exists') {
                            // Set our bar to layer exists, and finish it
                            $preparingStrings[$data->id]['status']      = 'Layer already exists';
                            $preparingStrings[$data->id]['progress']    = 100;
                        } else {
                            echo "this";
                            var_dump($data);
                            exit;
                        }
                    } else {
                        echo "that";
                        var_dump($data);
                        exit;
                    }
                } elseif (isset($data->error)) {
                    throw new \Exception('PUSH FAILED: ' . $data->error);
                }

                /*if (count($preparingStrings) > 0) {
                    sleep(1);
                    
                    // Display them here
                    foreach ($preparingStrings as $id => $preparingString) {           
                        $this->output->write("\033[K");

                        // Now make change to bar
                        if ($preparingString['progress'] == 100) {
                            echo $id . ': ' . $preparingString['status'];
                        } else {
                            $preparingString['bar']->setMessage($id . ': ' . $preparingString['status']);
                            $preparingString['bar']->setProgress($preparingString['progress']);
                            $preparingString['bar']->display();
                        }

                        echo "\n";
                    }
                    
                    // Up one line
                    for ($i = 0; $i < count($preparingStrings); $i++) {                        
                        $this->output->write("\033[1A");
                    }
                }*/
            }            
        }

        fclose($client);

        // -> Clean up and close the SSH tunnel
        if ($useTunnel) {
            if ($useProc) {
                proc_terminate($stream, 9);
                proc_close($stream);
            } else {
                fclose($stream);
            }
        }

        // Return our result object, which can later be used by a deployer
        // to retrieve the artifact
        return array(
            true,
            array('docker_image' => $imagename)
        );

    }

    /**
     * Determines if a command exists on the current environment
     *
     * @param  string $command The command to check
     * @return bool
     */
    private function commandExists($command) {
        
        $whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';

        $process = proc_open($whereIsCommand . " " . $command, array(
          0 => array("pipe", "r"), //STDIN
          1 => array("pipe", "w"), //STDOUT
          2 => array("pipe", "w"), //STDERR
        ), $pipes);

        if ($process !== false) {
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            proc_close($process);

            return $stdout != '';
        }

        return false;

    }

    /**
     * Gets the directory specified as tar archive data
     * @param  string $path The path to the directory to tar up
     * @return mixed
     */
    private function getDockerContextAsTar($path) {

        $ignoredFile = '';

        if (file_exists($path . '/.dockerignore')) {
            $ignoredFile = '--exclude-from=' . $path . '/.dockerignore ';
        }

        $process = new \Symfony\Component\Process\Process('/usr/bin/env tar cz ' . $ignoredFile . ' -C ' . $path . ' .');
        $process->run();
        
        if (!$process->isSuccessful()) {
            throw new \Symfony\Component\Process\Exception\ProcessFailedException($process);
        }

        return $process->getOutput();
    
    }

    /**
     * Makes sure the project name is Docker compatible
     * @param  string $projectname The Git-Deployer project name
     * @return string
     */
    private function cleanName($projectName) {

        // Special handling for dot '.', as it
        // should remain constant in the string always
        $projectName = str_replace('.', '_', $projectName);

        // Remove any characters that don't match
        // [a-zA-Z0-9_-]
        $projectName = preg_replace('#([^a-zA-Z0-9_\-]*)#', '', $projectName);

        return $projectName;

    }

}
