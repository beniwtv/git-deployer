<?php
namespace GitDeployer\Objects;

class History extends BaseObject {

    /**
     * The project's name, as it appears
     * on the Git service
     * @var string
     */
    protected $projectname;

    /**
     * The commits's SHA identifier
     * @var string
     */
    protected $commit;

    /**
     * The commits's author name
     * @var string
     */
    protected $author;

     /**
     * The commits's author e-mail
     * @var string
     */
    protected $authormail;

    /**
     * The commits's date
     * @var string
     */
    protected $date;

    /**
     * The commits's commit message
     * @var string
     */
    protected $message;

}