<?php
namespace GitDeployer\Builders;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseBuilder {

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
    public function build(\GitDeployer\Objects\Project $project, $gitpath, $config) {
        throw new \Exception('You must override the build() method in your service!');
    }

    //////////////////////////////////////////////////////////////////
    // Helpers
    //////////////////////////////////////////////////////////////////

    /**
     * Gets the available builders for the init command help
     * @return string
     */
    static function getBuildersForHelp() {

        // -> Get available services from builders directory
        $builders = self::getBuilders();

        // -> Print in a human-readable way        
        return implode(' ', $builders);

    }

    /**
     * Creates a new service instance to use throughout
     * the whole application
     * @param  string $builder The builder name to create
     * @param  \Symfony\Component\Console\Input\InputInterface   $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @param  \Symfony\Component\Console\Helper\HelperSet       $helpers
     * @return class of type \BaseBuilder
     */
    static function createServiceInstance($builder, InputInterface $input, OutputInterface $output, HelperSet $helpers) {

        $availableBuilders = self::getBuilders();

        if (!in_array($builder, $availableBuilders))
            throw new \Exception('This builder does not exist! See "help init" for builders.');

        $className = __NAMESPACE__ . '\\' . $builder . 'Builder';

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
     * Gets available builders from the builders directory
     * @return array
     */
    private static function getBuilders() {

        $builders = scandir(__DIR__);        
        $builders = array_map(function($m) {
            if (!in_array($m, array('.','..','BaseBuilder.php'))) {

                $className = __NAMESPACE__ . '\\' . str_replace('Builder.php', '', $m) . 'Builder';
                
                if (class_exists($className)) {                
                    return str_replace('Builder.php', '', $m);
                }
            }
        }, $builders);

        return array_filter($builders);

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
