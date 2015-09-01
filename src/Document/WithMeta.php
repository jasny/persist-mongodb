<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Entity,
    Jasny\TypeCast,
    Doctrine\Common\Inflector\Inflector;

/**
 * Document implementation with Introspection and TypedObject implementation
 */
trait WithMeta
{
    use Basics,
        Entity\Meta\Implementation;
    
    /**
     * Get the database connection
     * 
     * @return \Jasny\DB
     */
    protected static function getDB()
    {
        $name = static::meta()['db'] ?: 'default';
        return \Jasny\DB::conn($name);
    }
    
    /**
     * Get the Mongo collection name.
     * 
     * @return string
     */
    protected static function getCollectionName()
    {
        if (isset(static::$collection)) {
            $name = static::$collection;
        } elseif (isset(self::meta()['dbCollection'])) {
            $name = self::meta()['dbCollection'];
        } else {
            $class = preg_replace('/^.+\\\\/', '', static::getDocumentClass());
            $plural = Inflector::pluralize($class);
            $name = Inflector::tableize($plural);
        }
        
        return $name;
    }
    
    /**
     * Cast data to use in DB
     * 
     * @param array $data
     * @return array
     */
    protected static function castForDB($data)
    {
        foreach ($data as $prop => &$value) {
            $prop = trim(strstr($prop, '(', true)) ?: $prop; // Remove filter directives
            
            if (!isset(static::meta()->$prop['dbFieldType'])) continue;
            $value = TypeCast::cast($value, static::meta()->$prop['dbFieldType']);
        }
        
        return $data;
    }
    
    /**
     * Get identifier property
     * 
     * @return string
     */
    public static function getIdProperty()
    {
        foreach (static::meta()->ofProperties() as $prop => $meta) {
            if (isset($meta['id'])) return $prop;
        }
        
        return 'id';
    }
    
    /**
     * Get the field map.
     * 
     * @return array
     */
    protected static function getFieldMap()
    {
        $fieldMap = ['_id' => static::getIdProperty()];
        
        foreach (static::meta()->ofProperties() as $prop => $meta) {
            if (!isset($meta['dbFieldName']) || $meta['dbFieldName'] === $prop) continue;
            $fieldMap[$meta['dbFieldName']] = $prop;
        }
        
        return $fieldMap;
    }
    
    /**
     * Filter object for json serialization
     * 
     * @param object $object
     * @return object
     */
    protected function jsonSerializeFilter($object)
    {
        foreach (get_object_vars($object) as $prop) {
            if (isset(static::meta()->$prop['ignore'])) {
                unset($object->$prop);
            }
        }
        
        return $object;
    }
}