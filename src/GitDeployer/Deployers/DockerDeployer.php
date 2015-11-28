<?php
namespace GitDeployer\Deployers;

class DockerDeployer {   

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

 <comment>"host"</comment>: This variable overrides the DOCKER_HOST environment variable. If you do 
         not specify it, we will use the value from DOCKER_HOST instead.

         Valid formats are unix sockets, like <comment>unix:///var/run/docker.sock</comment>, and
         tcp sockets, like <comment>tcp://127.0.0.1:2375</comment>.

HELP;


    }

    /**
     * Uses Docker to deploy the given project to a live server
     * @param  \GitDeployer\Objects\Project $project The project to deploy
     * @param  string                       $gitpath The path to the checked-out project
     * @return mixed
     */
    public function deploy(\GitDeployer\Objects\Project $project, $gitpath) {
        
        
        
        return array(
            true,
            'No trace'
        );

    }

}
