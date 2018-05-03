<?php
require 'autoload.php';

$script = eZScript::instance(array(
    'description' => ("Converte un attributo ezobjectrelationlist in ocmultibinary"),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true
));

$script->startup();

$options = $script->getOptions('[class:][from_attribute:][to_attribute:][dry-run]',
    '',
    array(
        'class' => 'Identificatore della classe',
        'from_attribute' => "Identificatore dell'attributo di tipo ezobjectrelationlist",
        'to_attribute' => "Identificatore dell'attributo di tipo ocmultibinary",
        'dry-run' => "Mostra il risultato della conversione"
    )
);
$script->initialize();
$script->setUseDebugAccumulators(true);

$cli = eZCLI::instance();

$db = eZDB::instance();

$trans = eZCharTransform::instance();

$tempDir = eZDir::path( array( eZSys::cacheDirectory(), 'temp_convert' ) );
eZDir::mkdir($tempDir);

$dryRun = $options['dry-run'];

$fileSystem = new \Opencontent\Opendata\Api\Gateway\FileSystem();

$class = eZContentClass::fetchByIdentifier($options['class']);
if ($class instanceof eZContentClass) {
    $attributes = $class->dataMap();
    if (isset($attributes[$options['from_attribute']]) && isset($attributes[$options['to_attribute']])) {
        $ezobjectrelationlistClassAttribute = $attributes[$options['from_attribute']];
        $ocmultibinaryClassAttribute = $attributes[$options['to_attribute']];        
        if( 
            $ezobjectrelationlistClassAttribute instanceof eZContentClassAttribute 
            && $ezobjectrelationlistClassAttribute->attribute('data_type_string') == eZObjectRelationListType::DATA_TYPE_STRING
            && $ocmultibinaryClassAttribute  instanceof eZContentClassAttribute 
            && $ocmultibinaryClassAttribute->attribute('data_type_string') == OCMultiBinaryType::DATA_TYPE_STRING
        ){
            $ezobjectrelationlistObjectAttributes = eZContentObjectAttribute::fetchObjectList(
                eZContentObjectAttribute::definition(),
                null,
                array('contentclassattribute_id' => $ezobjectrelationlistClassAttribute->attribute('id'))
            );
            
            foreach ($ezobjectrelationlistObjectAttributes as $ezobjectrelationlistObjectAttribute) {                
                $currentObject = eZContentObject::fetch($ezobjectrelationlistObjectAttribute->attribute('contentobject_id'));
                if ($currentObject instanceof eZContentObject){                
                    $fileList = array();
                    $relationIdList = array();
                    $idList = explode('-', $ezobjectrelationlistObjectAttribute->toString());
                    foreach ($idList as $id) {
                        $object = eZContentObject::fetch((int)$id);
                        if ($object instanceof eZContentObject){
                            $dataMap = $object->dataMap();
                            foreach ($dataMap as $attribute) {
                                if ($attribute->attribute('data_type_string') == eZBinaryFileType::DATA_TYPE_STRING){
                                    list($filePath, $fileName) = explode('|', $attribute->toString());

                                    $parts = explode( '.', $fileName);
                                    $suffix = array_pop( $parts );
                                    $normalizedName = $trans->transformByGroup( implode( '.', $parts), 'identifier' );
                                    $normalizedName .= '.' . $suffix;

                                    $tempFile = $tempDir . '/' . $normalizedName;
                                    if (!$dryRun){
                                        eZFile::create($normalizedName, $tempDir, file_get_contents($filePath));
                                    }
                                    $fileList[] = $tempFile;   
                                    $relationIdList[] = $object->attribute('id');
                                }
                            }
                        }
                    }
                    $relationIdList = array_unique($relationIdList);
                    if (count($fileList) > 0){
                        $cli->warning("Oggetto #" . $currentObject->attribute('id') . ' ' . $currentObject->attribute('name'));
                        foreach ($fileList as $file) {
                            $cli->output(" - File " . $file);
                        }
                        if (!$dryRun){
                            $cli->output("Eseguo conversione");
                            $dataMap = $currentObject->dataMap();
                            if (isset($dataMap[$ocmultibinaryClassAttribute->attribute('identifier')])){
                                $dataMap[$ocmultibinaryClassAttribute->attribute('identifier')]->fromString(implode('|', $fileList));
                                $dataMap[$ocmultibinaryClassAttribute->attribute('identifier')]->store();

                                foreach ($relationIdList as $relationId) {
                                    $relation = eZContentObject::fetch((int)$relationId);
                                    if ($relation instanceof eZContentObject){
                                        foreach ($relation->assignedNodes() as $node) {
                                            eZContentObjectTreeNode::hideSubTree( $node );
                                        }
                                    }
                                }

                                $fileSystem->clearCache($currentObject->attribute('id'));
                                eZSearch::addObject($currentObject, true);
                                eZContentCacheManager::clearContentCache($currentObject->attribute('id'));
                            }else{
                                $cli->error("Attributo " . $ocmultibinaryClassAttribute->attribute('identifier') . " non trovato");
                            }

                            foreach ($fileList as $file) {
                                unlink($file);
                            }
                        }
                    }
                }
                eZContentObject::clearCache();
            }
        }
    }
}

$script->shutdown();