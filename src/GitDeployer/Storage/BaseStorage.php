<?php
namespace GitDeployer\Storage;

use \Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseStorage {

    /**
     * Holds the Console app input interface
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * Holds the Console app output interface
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * Holds the Console app helper set
     * @var \Symfony\Component\Console\Helper\HelperSet
     */
    protected $helpers;

    /**
     * Holds an array of DeploymentStatus objects
     * @var array of \GitDeployer\Objects\DeploymentStatus
     */
    protected $deploymentStatuses = null;

    //////////////////////////////////////////////////////////////////
    // Overridable functions
    //////////////////////////////////////////////////////////////////

    /**
     * Method to override in child services
     * @return string
     */
    public function getDescription() {
        throw new \Exception('You must override the getDescription() method in your storage service!');
    }

    /**
     * Method to override in child services
     * @return \GitDeployer\Objects\DeploymentStatus
     */
    public function getDeploymentStatus(\GitDeployer\Objects\Project $project) {
        throw new \Exception('You must override the getDeploymentStatus() method in your storage service!');
    }

    /**
     * Method to override in child services
     * @return boolean
     */
    public function addNewDeploymentStatus(\GitDeployer\Objects\DeploymentStatus $status) {
        throw new \Exception('You must override the addNewDeploymentStatus() method in your storage service!');
    }

     /**
     * Method to override in child services
     * @return boolean
     */
    public function removeDeploymentStatusForProject($project) {
         throw new \Exception('You must override the removeDeploymentStatusForProject() method in your storage service!');
    }

    /**
     * Method to override in child services
     * @return boolean
     */
    public function configure() {
        throw new \Exception('You must override the configure() method in your storage service!');
    }

    //////////////////////////////////////////////////////////////////
    // Helpers
    //////////////////////////////////////////////////////////////////

    /**
     * Gets the available storage services for the config command help
     * @return string
     */
    static function getStorageServicesForHelp() {

        // -> Get available services from storage directory
        $services = self::getStorageServices();

        // -> Print in a human-readable way
        $serviceStr = '';

        foreach ($services as $service) {
            $serviceStr = $service[0] . ' - ' . $service[1] . "\n";
        }

        return $serviceStr;

    }

    /**
     * Gets the available storage services for iterating them
     * in an array
     * @return array
     */
    static function getStorageServicesForIterating() {

        // -> Get available services from storage directory
        return array_values(array_map(function ($m) {
            return $m[0];
        }, self::getStorageServices()));

    }

    /**
     * Creates a new service instance to use throughout
     * the whole application
     * @param  string $service The service name to create
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @param  \Symfony\Component\Console\Helper\HelperSet       $helpers
     * @return class of type \BaseStorage
     */
    static function createServiceInstance($service, InputInterface $input, OutputInterface $output, HelperSet $helpers) {

        $availableServices = self::getStorageServices();
        $availableServices = array_values(array_map(function ($m) {
            return $m[0];
        }, self::getStorageServices()));

        if (!in_array($service, $availableServices))
            throw new \Exception('This storage service does not exist! See "help login" for services.');

        $className = __NAMESPACE__ . '\\' . $service . 'Storage';

        $servClass = new $className();
        $servClass->input   = $input;
        $servClass->output  = $output;
        $servClass->helpers = $helpers;

        return $servClass;

    }

    /**
     * Set's Symfony's console instances (they don't survive serialization)
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @param  \Symfony\Component\Console\Helper\HelperSet       $helpers
     */
    public function setInstances(InputInterface $input, OutputInterface $output, HelperSet $helpers) {

        $this->input   = $input;
        $this->output  = $output;
        $this->helpers = $helpers;

    }

    /**
     * Gets available services from the storage directory
     * @return array
     */
    private static function getStorageServices() {

        $services = scandir(__DIR__);        
        $services = array_map(function($m) {
            if (!in_array($m, array('.','..','BaseStorage.php'))) {

                $className = __NAMESPACE__ . '\\' . str_replace('Storage.php', '', $m) . 'Storage';
                $servClass = new $className();                

                return array(
                    str_replace('Storage.php', '', $m),
                    $servClass->getDescription()
                );
            }
        }, $services);

        return array_filter($services);

    }

}
