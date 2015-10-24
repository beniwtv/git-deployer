<?php
namespace GitDeployer\Objects;

class Tag extends BaseObject {

    /**
     * The tags's name, as it appears
     * on the Git service
     * @var string
     */
    protected $name;

    /**
     * The tags's commit SHA identifier
     * @var string
     */
    protected $commit;
    
}