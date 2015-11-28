<?php
namespace GitDeployer\Objects;

class DeploymentStatus extends BaseObject {

    /**
     * The project's name, as it appears
     * on the Git service
     * @var string
     */
    protected $project;    

    /**
     * Holds the IS0-8601 date of the
     * last deployment
     * @var string
     */
    protected $deployedWhen;

    /**
     * Holds the Git-type that was deployed,
     * e.g. tag, revision, branch...
     * @var string
     */
    protected $deployedType;

    /**
     * Holds the Git revision or tag
     * that was deployed
     * @var string
     */
    protected $deployedString;

    /**
     * Whether we are added to Git-Deployer yet
     * @var boolean
     */
    protected $added = false;

    /**
     * Gets the version that has been deployed
     * as a human-readable string
     * @return string
     */
    public function getDeployedVersion() {
        return ($this->isDeployd() ? $this->deployedType . ':' . $this->deployedString : 'N/A');
    }

    /**
     * Whether this project already has been deployd
     * @return boolean
     */
    public function isDeployd() {
        return (strlen($this->deployedString) > 0 ? true : false);
    }

    /**
     * Gets a human-readable text that describes
     * the current deployment status
     * @return string
     */
    public function getDeploymentInfo() {
        return ($this->isDeployd() ? 'Deployed on ' . $this->deployedWhen : 'Has not been deployed yet.');
    }

}
