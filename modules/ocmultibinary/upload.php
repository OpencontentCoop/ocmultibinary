<?php
/** @var eZModule $Module */
$Module = $Params['Module'];

/** @var eZContentObjectAttribute $attribute */
$attribute = eZContentObjectAttribute::fetch($Params['AttributeID'], $Params['Version'],
    array('language_code' => $Params['Language']));

if (!$attribute) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

if ($attribute->attribute('data_type_string') != OCMultiBinaryType::DATA_TYPE_STRING) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

$object = $attribute->attribute('object');

if (!$object) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

// If the object has status Archived (trash) we redirect to content/restore
// which can handle this status properly.
if ($object->attribute('status') == eZContentObject::STATUS_ARCHIVED) {
    return $Module->handleError(eZError::KERNEL_NOT_AVAILABLE, 'kernel');
}

if (!$object->attribute('can_edit')) {
    return $Module->handleError(eZError::KERNEL_ACCESS_DENIED, 'kernel');
}

$response = array();
$siteaccess = eZSiteAccess::current();
$options['upload_dir'] = eZSys::cacheDirectory() . '/fileupload/';
$options['download_via_php'] = true;
$options['param_name'] = "DocFile";
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

header('Content-Type: application/json');
echo json_encode($response);
eZExecution::cleanExit();