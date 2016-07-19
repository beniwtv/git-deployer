<?php
namespace GitDeployer\Storage;

use Symfony\Component\Console\Question\Question;

class LocalStorage extends BaseStorage {

    /**
     * Holds the path to the state file
     * @var string
     */
    protected $path;

    //////////////////////////////////////////////////////////////////
    // Service functions
    //////////////////////////////////////////////////////////////////

    /**
     * Gets a description text for this storage service
     * @return string
     */
    public function getDescription() {
        return 'The <info>Local</info> storage service stores the configuration locally on your computer.';
    }

    /**
     * Sets the deployment status of the requested project, and loads the
     * status file if not already done so
     * @param \GitDeployer\Objects\Project          $project The project to update
     * @param \GitDeployer\Objects\DeploymentStatus $status  The actual status of the project
     */
    public function setDeploymentStatus(\GitDeployer\Objects\Project $project, \GitDeployer\Objects\DeploymentStatus $status) {

        // -> Load status file, if not done so yet
        $this->loadDeploymentStatuses();

        // -> Check if we already have a status object
        // that matches our project
        $currentStatusObject = null;

        foreach ($this->deploymentStatuses as $key => $existingstatus) {
            if ($status->project() == $project->name()) {
                $currentStatusObject            = $status;
                $this->deploymentStatuses[$key] = $status;
            }
        }

        // -> If we have no status object yet, return an error, since something
        // obviously went wron when adding a status
        if ($currentStatusObject == null) {
            throw new \Exception('No existing status for project "' . $project->name() . '"! Did you forget to add it to Git-Deployer?');     
        }

        $this->saveDeploymentStatuses();

    }

    /**
     * Gets the deployment status of the requested project, and loads the
     * status file if not already done so
     * @param  \GitDeployer\Objects\Project $project The project to update
     * @return \GitDeployer\Objects\DeploymentStatus
     */
    public function getDeploymentStatus(\GitDeployer\Objects\Project $project) {

        // -> Load status file, if not done so yet
        $this->loadDeploymentStatuses();

        // -> Check if we already have a status object
        // that matches our project
        $currentStatusObject = null;

        foreach ($this->deploymentStatuses as $status) {
            if ($status->project() == $project->name()) {
                return $status;
            }
        }

        // -> If we have no status object yet, return an empty one
        $status = new \GitDeployer\Objects\DeploymentStatus();
        $status->project($project->name());

        return $status;

    }

    /**
     * Adds a new deployment status for requested project, loads the
     * status file if not already done so, and rejects the request
     * if the project was already added
     * @param  \GitDeployer\Objects\Project $project The project to add 
     * @return boolean
     */
    public function addNewDeploymentStatus(\GitDeployer\Objects\Project $project) {
        
        // -> Load status file, if not done so yet
        $status = $this->getDeploymentStatus($project);

        if ($status->added()) {
            // -> Project already added, inform user
            throw new \Exception('Project "' . $project->name() . '" is already added to Git-Deployer! Please check the status command!');     
        } else {
            // -> Ready to add project to statuses
            $status->added(true);
            $this->deploymentStatuses[] = $status;

            $this->saveDeploymentStatuses();
        }

    }

    /**
     * Removes the deployment status for requested project, loads the
     * status file if not already done so, and rejects the request
     * if the project does not exist
     * @param  string  $project The project to add 
     * @return boolean
     */
    public function removeDeploymentStatusForProject($project) {

        // -> Load status file, if not done so yet
        $this->loadDeploymentStatuses();

        // -> Check if we already have a status object
        // that matches our project
        $currentStatusObject = -1;

        foreach ($this->deploymentStatuses as $key => $status) {
            if ($status->project() == $project) {
                $currentStatusObject = $key;
            }
        }

        if ($currentStatusObject < 0) {
            // -> Project not found, inform user
            throw new \Exception('Project "' . $project . '" is not present in Git-Deployer! Please check the status command!');    
        } else {
            unset($this->deploymentStatuses[$currentStatusObject]);
            $this->deploymentStatuses = array_values($this->deploymentStatuses);

            $this->saveDeploymentStatuses();
        }

    }

    /**
     * Configures the storage service. In our case, we just
     * want the file name for the state file
     * @return boolean
     */
    public function configure() {

        $helper = $this->helpers->get('question');
        
        // -> Get file path from the user
        $xdg = new \XdgBaseDir\Xdg();
        $savePath = $xdg->getHomeConfigDir() . '/git-deployer';
        
        if (strlen($this->path) > 3) {
            $defText = '[' . $this->path . '] ';
        } else {
            $this->path = $savePath . '/localstorage.json';
            $defText = '[' . $savePath . '/localstorage.json] ';
        }

        $question = new Question('Please enter the file path for your local storage: ' . $defText, $this->path);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The file path can not be empty!'
                );
            }

            return $answer;
        });

        $this->path = $helper->ask($this->input, $this->output, $question);

        // -> Create file, if it does not exist yet
        if ( @file_put_contents($this->path, json_encode(array())) ) {
            return true;
        } else {
            throw new \Exception('Could not save file to path: "' . $this->path . '"! ' . "\n" .  'Error was: ' . error_get_last()['message']);
        }

    }

    /**
     * Loads the deployment status file, if not done so yet
     */
    private function loadDeploymentStatuses() {

        if ($this->deploymentStatuses == null) {
            if ($statuses = @file_get_contents($this->path)) {
                // Success!!
                $this->deploymentStatuses = \GitDeployer\Objects\BaseObject::jsonUnserialize(json_decode($statuses));
            } else {
                throw new \Exception('Could not open path: "' . $this->path . '"! ' . "\n" .  'Error was: ' . error_get_last()['message']);
            }
        }

    }

    /**
     * Saves the deployment status file to disk
     */
    private function saveDeploymentStatuses() {

        $statuses = json_encode($this->deploymentStatuses);

        if (@file_put_contents($this->path, $statuses)) {
            // Success!!
        } else {
            throw new \Exception('Could not save path: "' . $this->path . '"! ' . "\n" .  'Error was: ' . error_get_last()['message']);
        }

    }

    /**
     * Makes sure we only serialize needed data, else we may
     * put too much cruft in the serialized file that we can't restore
     * @return array
     */
    public function __sleep() {

        return array('path');
        
    }

}
