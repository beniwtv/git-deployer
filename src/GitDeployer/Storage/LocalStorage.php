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
     * Gets the deployment status of the requested project, and loads the
     * status file if not already done so
     * @return \GitDeployer\Objects\DeploymentStatus
     */
    public function getDeploymentStatus($project) {

        // -> Load status file, if not done so yet
        if ($this->deploymentStatuses == null) {
            if ( $statuses = @file_get_contents($this->path) ) {
                // Success!!
                $statuses = json_decode($statuses);
            } else {
                throw new \Exception('Could not open path: "' . $this->path . '"! ' . "\n" .  'Error was: ' . error_get_last()['message']);
            }
        }

        // -> Check if we already have a status object
        // that matches our project
        $currentStatusObject = null;

        foreach ($statuses as $status) {
            if ($status->project() == $project->name()) {
                $currentStatusObject = $status;
            }
        }

        // -> If we have no status object yet, return an empty one
        if ($currentStatusObject == null) {
            $status = new \GitDeployer\Objects\DeploymentStatus();
            $status->project($project->name());
        }

        return $status;
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
            return false;
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