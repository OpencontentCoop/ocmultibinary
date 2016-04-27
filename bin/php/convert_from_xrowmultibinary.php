<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ("Converte un attributo xrowmultibinary in ocmultibinary"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions('[class:][attribute:]',
    '',
    array(
        'class' => 'Identificatore della classe',
        'attribute' => "Identificatore dell'attributo"
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$db = eZDB::instance();

$class = eZContentClass::fetchByIdentifier($options['class']);
if ($class instanceof eZContentClass) {
    $attributes = $class->dataMap();
    if (isset($attributes[$options['attribute']])) {
        $attributeClass = $attributes[$options['attribute']];
        if($attributeClass instanceof eZContentClassAttribute){
            if ($attributeClass->attribute('data_type_string') == 'xrowmultibinary'){
                $attributeObjects = eZContentObjectAttribute::fetchObjectList(
                    eZContentObjectAttribute::definition(),
                    null,
                    array('contentclassattribute_id' => $attributeClass->attribute('id'))
                );
    
                $db->begin();
                $attributeClass->setAttribute('data_type_string',OCMultiBinaryType::DATA_TYPE_STRING);
                $attributeClass->store();
                foreach($attributeObjects as $attributeObject){
                    $attributeObject->setAttribute('data_type_string',OCMultiBinaryType::DATA_TYPE_STRING);
                    $attributeObject->store();
                }
                $db->commit();
                eZCache::clearAll();
            }
        }
    }
}

$script->shutdown();