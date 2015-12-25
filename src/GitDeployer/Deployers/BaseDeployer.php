<?php
namespace GitDeployer\Deployers;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseDeployer {

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
     * @return string
     */
    public static function getHelp() {
        throw new \Exception('You must override the getHelp() method in your service!');
    }

    /**
     * Method to override in child services
     * @return mixed
     */
    public function deploy(\GitDeployer\Objects\Project $project, $gitpath, $config) {
        throw new \Exception('You must override the deploy() method in your service!');
    }

    //////////////////////////////////////////////////////////////////
    // Helpers
    //////////////////////////////////////////////////////////////////

    /**
     * Gets the available deployers for the init command help
     * @return string
     */
    static function getDeployersForHelp() {

        // -> Get available services from deployers directory
        $deployers = self::getDeployers();

        // -> Print in a human-readable way        
        return implode(' ', $deployers);

    }

    /**
     * Creates a new service instance to use throughout
     * the whole application
     * @param  string $deployer The deployer name to create
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @param  \Symfony\Component\Console\Helper\HelperSet       $helpers
     * @return class of type \BaseDeployer
     */
    static function createServiceInstance($deployer, InputInterface $input, OutputInterface $output, HelperSet $helpers) {

        $availableDeployers = self::getDeployers();

        if (!in_array($deployer, $availableDeployers))
            throw new \Exception('This deployer does not exist! See "help init" for deployers.');

        $className = __NAMESPACE__ . '\\' . $deployer . 'Deployer';

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
     * Gets available deployers from the deployers directory
     * @return array
     */
    private static function getDeployers() {

        $deployers = scandir(__DIR__);        
        $deployers = array_map(function($m) {
            if (!in_array($m, array('.','..','BaseDeployer.php'))) {

                $className = __NAMESPACE__ . '\\' . str_replace('Deployer.php', '', $m) . 'Deployer';
                $servClass = new $className();                

                return str_replace('Deployer.php', '', $m);
            }
        }, $deployers);

        return array_filter($deployers);

    }

    /**
     * Shows a message on the terminal
     * @param  string          $section  "Section" for the message
     * @param  string          $message  The message to display
     * @param  OutputInterface $output   The Symfony output interface
     */
    protected function showMessage($section, $message, $output, $style = 'info') {

        $formatter = $this->helpers->get('formatter');
        $formattedLine = $formatter->formatSection(
            str_pad($section, 6, ' ', STR_PAD_LEFT),
            $message,
            $style
        );

        $output->writeln($formattedLine);

    }

}
