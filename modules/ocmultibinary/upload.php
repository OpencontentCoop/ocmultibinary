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
if ($attribute->hasContent()) {
    $attributeContent = (array)$attribute->content();
    $fileCount = count($attributeContent);
}

$response = array();
$response['errors'] = array();

if ($fileCount < $maxFileCount || $maxFileCount == 0) {

    //store current decorations
    $attribute->dataType()->fetchObjectAttributeHTTPInput(eZHTTPTool::instance(), 'ContentObjectAttribute', $attribute);

    $siteaccess = eZSiteAccess::current();
    $options['upload_dir'] = eZSys::cacheDirectory() . '/fileupload/';
    $options['download_via_php'] = true;
    $options['param_name'] = "OcMultibinaryFiles";
    $options['image_versions'] = array();
    $options['max_file_size'] = $http->variable("upload_max_file_size", null);

    if (eZINI::instance('ocmultibinary.ini')->hasVariable('AcceptFileTypesRegex', 'ClassAttributeIdentifier')) {
        $acceptFileTypesClassAttributeIdentifier = eZINI::instance('ocmultibinary.ini')->variable('AcceptFileTypesRegex', 'ClassAttributeIdentifier');
        if (isset($acceptFileTypesClassAttributeIdentifier[$object->attribute('class_identifier') . '/' . $attribute->attribute('contentclass_attribute_identifier')])) {
            $options['accept_file_types'] = $acceptFileTypesClassAttributeIdentifier[$object->attribute('class_identifier') . '/' . $attribute->attribute('contentclass_attribute_identifier')];
        }
    }

    /** @var OCMultiBinaryUploadHandler $uploadHandler */
    $uploadHandler = new OCMultiBinaryUploadHandler($options, false, [
        1 => ezpI18n::tr('extension/ocmultibinary', 'The uploaded file exceeds the upload_max_filesize directive in php.ini'),
        2 => ezpI18n::tr('extension/ocmultibinary', 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
        3 => ezpI18n::tr('extension/ocmultibinary', 'The uploaded file was only partially uploaded'),
        4 => ezpI18n::tr('extension/ocmultibinary', 'No file was uploaded'),
        6 => ezpI18n::tr('extension/ocmultibinary', 'Missing a temporary folder'),
        7 => ezpI18n::tr('extension/ocmultibinary', 'Failed to write file to disk'),
        8 => ezpI18n::tr('extension/ocmultibinary', 'A PHP extension stopped the file upload'),
        'post_max_size' => ezpI18n::tr('extension/ocmultibinary', 'The uploaded file exceeds the post_max_size directive in php.ini'),
        'max_file_size' => ezpI18n::tr('extension/ocmultibinary', 'File is too big'),
        'min_file_size' => ezpI18n::tr('extension/ocmultibinary', 'File is too small'),
        'accept_file_types' => ezpI18n::tr('extension/ocmultibinary', 'Filetype not allowed'),
        'max_number_of_files' => ezpI18n::tr('extension/ocmultibinary', 'Maximum number of files exceeded'),
        'max_width' => ezpI18n::tr('extension/ocmultibinary', 'Image exceeds maximum width'),
        'min_width' => ezpI18n::tr('extension/ocmultibinary', 'Image requires a minimum width'),
        'max_height' => ezpI18n::tr('extension/ocmultibinary', 'Image exceeds maximum height'),
        'min_height' => ezpI18n::tr('extension/ocmultibinary', 'Image requires a minimum height'),
        'abort' => ezpI18n::tr('extension/ocmultibinary', 'File upload aborted'),
        'image_resize' => ezpI18n::tr('extension/ocmultibinary', 'Failed to resize image'),
    ]);
    $data = $uploadHandler->post(false);

    foreach ($data[$options['param_name']] as $file) {
        if ($file->error) {
            $response['errors'][] = $file->error;
        } else {
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
    }

    $attribute = eZContentObjectAttribute::fetch(
        (int)$Params['AttributeID'],
        (int)$Params['Version'],
        array('language_code' => $Params['Language'])
    );
    $tpl = eZTemplate::factory();
    $tpl->setVariable('attribute', $attribute);
    if ($attribute->contentClassAttribute()->attribute(OCMultiBinaryType::ALLOW_DECORATIONS_FIELD)) {
        $response['content'] = $tpl->fetch("design:content/datatype/edit/filelist_decorated.tpl");
    }else{
        $response['content'] = $tpl->fetch("design:content/datatype/view/filelist.tpl");
    }

} else {
    $response['errors'] = array(
        ezpI18n::tr('extension/ocmultibinary', 'Maximum number of files exceeded')
    );
}

header('Content-Type: application/json');
echo json_encode($response);
eZExecution::cleanExit();