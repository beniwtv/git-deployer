<?php
namespace GitDeployer;

class AppInstance implements \Serializable {
  
    /**
     * The currently used service
     * in this application
     * @var Services\BaseService
     */
    protected $service;

    /**
     * Re-creates this class from the file
     * found in the XDG directory
     * @return AppInstance
     */
    public static function getInstance() {

        $xdg = new \XdgBaseDir\Xdg();
        $savePath = $xdg->getHomeConfigDir() . '/git-deployer';
        $saveFile = $savePath . '/appinstance.ser';

        if (!file_exists($saveFile)) {
            throw new \Exception('Could not open the app instance! Did you forget to login?');        
        }

        return unserialize(file_get_contents($saveFile));

    }

    /**
     * Saves this class' data to the XDG
     * home directory
     * @return boolean
     */
    public function save() {

        $xdg = new \XdgBaseDir\Xdg();
        $savePath = $xdg->getHomeConfigDir() . '/git-deployer';
        $saveFile = $savePath . '/appinstance.ser';

        if (!file_exists($savePath)) mkdir($savePath);
        return file_put_contents($saveFile, serialize($this));

    }

    /**
     * Deletes this class' data to the XDG
     * home directory
     * @return boolean
     */
    public function delete() {

        $xdg = new \XdgBaseDir\Xdg();
        $savePath = $xdg->getHomeConfigDir() . '/git-deployer';
        $saveFile = $savePath . '/appinstance.ser';

        if (!file_exists($saveFile)) return true;
        return unlink($saveFile);

    }

    /**
     * Serialize this class' data, so we
     * can store it in the file system
     * @return string
     */
    public function serialize() {

        return serialize(array(
            $this->service
        ));

    }

    /**
     * Unserializes string data to this class
     * @param  string $data The data to unserialize
     */
    public function unserialize($data) {

        $data = unserialize($data);
        $this->service = $data[0];

    }

    /**
     * Makes AppInstance properties chainable
     * @param  string $method The property name to access
     * @param  array  $args   The value to pass to the property
     * @return AppInstance
     */
    public function __call($method, $args) {

        if (property_exists($this, $method)) {
            if (count($args) == 0) return $this->$method;
            $this->$method = $args[0];
            return $this;
        } else {
            throw new \Exception('AppInstance does not have this property: ' . $method . '!');            
        }

    }

}