<?php
namespace GitDeployer\Objects;

class BaseObject implements \JsonSerializable {

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

    /**
     * Encodes the project class and returns an array
     * @return array
     */
    public function jsonSerialize() {

        $vars = get_object_vars($this);
        $vars['__CLASS__'] = get_class($this);

        return $vars;

    }

    /**
     * Decodes the array given by jsonSerialize() and restores
     * the original object
     * @return GitDeployer\Objects\BaseObject
     */
    public static function jsonUnserialize($objectOrArray) {

        if (is_array($objectOrArray)) {
            $unserializeArray = array();

            foreach ($objectOrArray as $key => $object) {
                $unserializeArray[$key] = self::jsonUnserialize($object);
            }

            return $unserializeArray;
        } elseif (is_object($objectOrArray)) {
            $className = 'stdClass';

            if (property_exists($objectOrArray, '__CLASS__')) $className = $objectOrArray->__CLASS__;
            $object = new $className;

            foreach ($objectOrArray as $property => $value) {
                if ($property == '__CLASS__') continue;
                $object->$property = $value;
            }

            return $object;
        }

    }
    
}