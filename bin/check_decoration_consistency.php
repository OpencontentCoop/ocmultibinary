<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ("Converte un attributo ezbinary in ocmultibinary"),
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

function cleanFileName($filename)
{
    $parts = explode('.', $filename);
    if (count($parts) > 1) {
        array_pop($parts);
    }
    $filename = implode('.', $parts);
    $filename = str_replace(array('_', '-', '+'), ' ', $filename);
    $filename = str_replace(':', '/', $filename);
    for ($i = 1; $i <= 100; $i++) {
        $filename = str_replace('('.$i.')', '', $filename);
    }
    $filename = trim($filename);

    return ucfirst($filename);
}

$class = eZContentClass::fetchByIdentifier($options['class']);
if ($class instanceof eZContentClass) {
    $attributes = $class->dataMap();
    if (isset($attributes[$options['attribute']])) {
        $attributeClass = $attributes[$options['attribute']];
        if ($attributeClass instanceof eZContentClassAttribute) {
            if ($attributeClass->attribute('data_type_string') == OCMultiBinaryType::DATA_TYPE_STRING) {
                $attributeObjects = eZContentObjectAttribute::fetchObjectList(
                    eZContentObjectAttribute::definition(),
                    null,
                    array('contentclassattribute_id' => $attributeClass->attribute('id'))
                );

                foreach ($attributeObjects as $contentObjectAttribute) {
                    if ($contentObjectAttribute->hasContent()) {
                        $storedDecorations = unserialize($contentObjectAttribute->attribute('data_text'));
                        if (empty($storedDecorations)) {
                            eZCLI::instance()->output($contentObjectAttribute->attribute('id') . ' ' . $contentObjectAttribute->attribute('version'));
                            $binaryFiles = (array)eZMultiBinaryFile::fetch($contentObjectAttribute->attribute('id'), $contentObjectAttribute->attribute('version'));
                            $decorations = [];
                            foreach ($binaryFiles as $index => $binaryFile) {
                                $decorations[] = [
                                    'original_filename' => $binaryFile->attribute('original_filename'),
                                    'display_name' => cleanFileName($binaryFile->attribute('original_filename')),
                                    'display_group' => '',
                                    'display_text' => '',
                                    'display_order' => $index,
                                ];
                            }
                            $contentObjectAttribute->setAttribute('data_text', serialize($decorations));
                            $contentObjectAttribute->store();
                        }
                    }
                }
            }
        }
    }
}

$script->shutdown();
