<?php
namespace GitDeployer\Objects;

class BaseObject {

    /**
     * Makes all object properties chainable
     * @param  string $method The property name to access
     * @param  array  $args   The value to pass to the property
     * @return BaseObject
     */
    public function __call($method, $args) {

        if (property_exists($this, $method)) {
            if (count($args) == 0) return $this->$method;
            $this->$method = $args[0];
            return $this;
        } else {
            throw new \Exception('Object does not have this property: ' . $method . '!');            
        }

    }
    
}