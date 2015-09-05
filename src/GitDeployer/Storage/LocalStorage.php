<?php
namespace GitDeployer\Storage;

class LocalStorage extends BaseStorage {

    /**
     * Gets a description text for this storage service
     * @return string
     */
    public function getDescription() {
        return 'The <info>Local</info> storage service stores the configuration locally on your computer.';
    }

}