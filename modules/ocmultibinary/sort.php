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


$response = array();

if ($http->hasPostVariable('files')){
    $data = json_decode($http->postVariable('files'), 1);
    if ( !empty($data) ) {
        OCMultiBinaryType::setFileOrder($attribute, $data);
    }
    $response['status'] = 'success';
}else{
    $response['status'] = 'error';
    $response['errors'] = array('Missing post param');
}

header('Content-Type: application/json');
echo json_encode($response);
eZExecution::cleanExit();