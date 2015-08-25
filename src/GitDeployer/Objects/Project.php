<?php
namespace GitDeployer\Objects;

class Project extends BaseObject {

    /**
     * The project's name, as it appears
     * on the Git service
     * @var string
     */
    protected $name;

    /**
     * The project's description, as it appears
     * on the Git service
     * @var string
     */
    protected $description;

    /**
     * The project's URL on the Git service
     * @var string
     */
    protected $url;


}