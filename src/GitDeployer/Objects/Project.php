<?php
namespace GitDeployer\Objects;

class Project extends BaseObject {

    /**
     * The project's ID, as it appears
     * on the Git service
     * @var string
     */
    protected $id;

    /**
     * The project's name, as it appears
     * on the Git service
     * @var string
     */
    protected $name;

    /**
     * The name space, i.e. "organization" or
     * "user" of the project on the Git service
     * @var string
     */
    protected $namespace = null;

    /**
     * The default branch of this Git repository
     * as configured on the Git service
     * @var string
     */
    protected $defaultBranch;

    /**
     * The project's description, as it appears
     * on the Git service
     * @var string
     */
    protected $description;

    /**
     * The project's git URL on the Git service
     * @var string
     */
    protected $url;

    /**
     * The project's homepage on the Git service
     * @var string
     */
    protected $homepage;

}
