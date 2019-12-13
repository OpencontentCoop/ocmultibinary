<?php
/** @var eZModule $Module */
$Module = $Params['Module'];
$http = eZHTTPTool::instance();

/** @var eZContentObjectAttribute $attribute */
$attribute = eZContentObjectAttribute::fetch(
    (int)$Params['AttributeID'],
    (int)$Params['Version'],
    array('language_code' => $Params['Language'])
);

if (!$attribute instanceof eZContentObjectAttribute) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

if ($attribute->attribute('data_type_string') != OCMultiBinaryType::DATA_TYPE_STRING) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

/** @var eZContentObject $object */
$object = $attribute->attribute('object');

if (!$object instanceof eZContentObject) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

if ($object->attribute('status') == eZContentObject::STATUS_ARCHIVED) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

if (!$object->attribute('can_edit')) {
    return $Module->handleError(eZError::KERNEL_ACCESS_DENIED, 'kernel');
}

$maxFileCount = $attribute->attribute('contentclass_attribute')->attribute(OCMultiBinaryType::MAX_NUMBER_OF_FILES_FIELD);
$fileCount = 0;
if ($attribute->hasContent()){
    $fileCount = count($attribute->content());
}

$response = array();
$response['errors'] = array();

if ($fileCount<$maxFileCount || $maxFileCount == 0){
    $siteaccess = eZSiteAccess::current();
    $options['upload_dir'] = eZSys::cacheDirectory() . '/fileupload/';
    $options['download_via_php'] = true;
    $options['param_name'] = "OcMultibinaryFiles";
    $options['image_versions'] = array();
    $options['max_file_size'] = $http->variable("upload_max_file_size", null);

    /** @var UploadHandler $uploadHandler */
    $uploadHandler = new OCMultiBinaryUploadHandler($options, false);
    $data = $uploadHandler->post(false);

    foreach ($data[$options['param_name']] as $file) {
        $filePath = $options['upload_dir'] . $file->name;
        $attribute->dataType()->insertRegularFile(
            $attribute->attribute('object'),
            $attribute->attribute('version'),
            $attribute->attribute('language_code'),
            $attribute,
            $filePath,
            $response
        );
        $file = eZClusterFileHandler::instance($filePath);
        if ($file->exists()) {
            $file->delete();
        }
    }

    $attribute = eZContentObjectAttribute::fetch(
        (int)$Params['AttributeID'],
        (int)$Params['Version'],
        array('language_code' => $Params['Language'])
    );
    $tpl = eZTemplate::factory();
    $tpl->setVariable('attribute',$attribute);
    $response['content'] = $tpl->fetch("design:content/datatype/view/filelist.tpl");
}else{
    $response['errors'] = array('Reached the maximum limit of files');
}

header('Content-Type: application/json');
echo json_encode($response);
eZExecution::cleanExit();