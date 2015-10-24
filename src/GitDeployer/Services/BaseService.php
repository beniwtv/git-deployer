<?php
namespace GitDeployer\Services;

use \Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseService {

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

    //////////////////////////////////////////////////////////////////
    // Overridable functions
    //////////////////////////////////////////////////////////////////

    /**
     * Method to override in child services
     * @return boolean
     */
    public function login() {
        throw new \Exception('You must override the login() method in your service!');
    }

    /**
     * Method to override in child services
     * @return array of \GitDeployer\Objects\Project
     */
    public function getProjects($url = null) {
        throw new \Exception('You must override the getProjects() method in your service!');
    }

    /**
     * Method to override in child services
     * @return array of \GitDeployer\Objects\History
     */
    public function getHistory(\GitDeployer\Objects\Project $project, $url = null) {
        throw new \Exception('You must override the getHistory() method in your service!');
    }

    //////////////////////////////////////////////////////////////////
    // Helpers
    //////////////////////////////////////////////////////////////////

    /**
     * Gets the available Git services for the login command help
     * @return string
     */
    static function getVCSServicesForHelp() {

        // -> Get available services from services directory
        $services = self::_getVCServices();

        // -> Print in a human-readable way
        return implode("\n ", array_filter($services));

    }

    /**
     * Gets available services from the services directory
     * @return array
     */
    private static function _getVCServices() {

        $services = scandir(__DIR__);        
        $services = array_map(function($m) {
            if (!in_array($m, array('.','..','BaseService.php')))
                return str_replace('Service.php', '', $m);
        }, $services);

        return $services;

    }

    /**
     * Creates a new service instance to use throughout
     * the whole application
     * @param  string $service The service name to create
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @param  \Symfony\Component\Console\Helper\HelperSet       $helpers
     * @return class of type \BaseService
     */
    static function createServiceInstance($service, InputInterface $input, OutputInterface $output, HelperSet $helpers) {

        $availableServices = self::_getVCServices();

        if (!in_array($service, $availableServices))
            throw new \Exception('This VCS service does not exist! See "help login" for services.');

        $className = __NAMESPACE__ . '\\' . $service . 'Service';

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

}