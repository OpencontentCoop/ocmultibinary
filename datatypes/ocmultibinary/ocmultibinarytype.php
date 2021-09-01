<?php

class OCMultiBinaryType extends eZDataType
{
    const MAX_FILESIZE_FIELD = 'data_int1';
    const MAX_NUMBER_OF_FILES_FIELD = 'data_int2';
    const ALLOW_DECORATIONS_FIELD = 'data_int3';

    const MAX_FILESIZE_VARIABLE = '_ocmultibinary_max_filesize_';
    const MAX_NUMBER_OF_FILES_VARIABLE = '_ocmultibinary_max_number_of_files_';
    const ALLOW_DECORATIONS_VARIABLE = '_ocmultibinary_allow_decoration_';

    const DATA_TYPE_STRING = 'ocmultibinary';

    function __construct()
    {
        parent::__construct(self::DATA_TYPE_STRING,
            ezpI18n::tr('kernel/classes/datatypes', 'Multiple Files', 'Datatype name'),
            array('serialize_supported' => true));
    }

    /**
     * @return eZBinaryFileHandler
     */
    function fileHandler()
    {
        return eZBinaryFileHandler::instance();
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @param int $version
     *
     * @return eZPersistentObject[]
     */
    protected function getBinaryFiles($contentObjectAttribute, $version = null)
    {
        $contentObjectAttributeID = $contentObjectAttribute->attribute('id');
        if ($version === false) {
            $version = $contentObjectAttribute->attribute('version');
        }
        $binaryFiles = (array)eZMultiBinaryFile::fetch($contentObjectAttributeID, $version);

        $decorations = self::parseDecorations($contentObjectAttribute);
        if (!empty($decorations)) {
            foreach ($binaryFiles as $binaryFile) {
                if ($binaryFile instanceof eZMultiBinaryFile) {
                    foreach ($decorations as $key => $value) {
                        $displayName = $value['display_name'];
                        $group = $value['display_group'];
                        $text = $value['display_text'];
                        $order = $value['display_order'];
                        $originalFileName = $value['original_filename'];
                        if ($binaryFile->attribute('original_filename') == $originalFileName) {
                            $binaryFile->setAttribute('display_order', $order);
                            $binaryFile->setAttribute('display_name', $displayName);
                            $binaryFile->setAttribute('display_group', $group);
                            $binaryFile->setAttribute('display_text', $text);
                        }
                    }
                }
            }
            usort($binaryFiles, function($a, $b){
                if ($a->attribute('display_order') == $b->attribute('display_order')) {
                    return 0;
                }
                return ($a->attribute('display_order') < $b->attribute('display_order')) ? -1 : 1;
            });
        }

        return $binaryFiles;
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @param int $currentVersion
     * @param eZContentObjectAttribute $originalContentObjectAttribute
     */
    function initializeObjectAttribute($contentObjectAttribute, $currentVersion, $originalContentObjectAttribute)
    {
        if ($currentVersion != false) {
            $contentObjectAttributeID = $originalContentObjectAttribute->attribute('id');
            $version = $contentObjectAttribute->attribute('version');
            $oldFiles = eZMultiBinaryFile::fetch($contentObjectAttributeID, $currentVersion);
            foreach ($oldFiles as $oldFile) {
                $oldFile->setAttribute('contentobject_attribute_id', $contentObjectAttribute->attribute('id'));
                $oldFile->setAttribute('version', $version);
                $oldFile->store();
            }
            $dataText = $originalContentObjectAttribute->attribute( "data_text" );
            $contentObjectAttribute->setAttribute( "data_text", $dataText );
        }
    }

    /**
     * The object is being moved to trash, do any necessary changes to the attribute.
     * Rename file and update db row with new name, so that access to the file using old links no longer works.
     *
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @param null $version
     */
    function trashStoredObjectAttribute($contentObjectAttribute, $version = null)
    {
        $sys = eZSys::instance();
        $storageDirectory = $sys->storageDirectory();

        $binaryFiles = $this->getBinaryFiles($contentObjectAttribute, $version);

        if (is_array($binaryFiles) && count($binaryFiles) > 0) {
            foreach ($binaryFiles as $binaryFile) {
                if ($binaryFile instanceof eZMultiBinaryFile) {
                    $mimeType = $binaryFile->attribute("mime_type");
                    list($prefix, $suffix) = preg_split('[/]', $mimeType);
                    unset($suffix);
                    $originalDirectory = $storageDirectory . '/original/' . $prefix;
                    $fileName = $binaryFile->attribute("filename");

                    // Check if there are any other records in ezbinaryfile that point to that fileName.
                    $binaryObjectsWithSameFileName = eZMultiBinaryFile::fetchByFileName($fileName);

                    $filePath = $originalDirectory . "/" . $fileName;
                    $file = eZClusterFileHandler::instance($filePath);

                    if ($file->exists() and count($binaryObjectsWithSameFileName) <= 1) {
                        // create dest filename in the same manner as eZHTTPFile::store()
                        // grab file's suffix
                        $fileSuffix = eZFile::suffix($fileName);
                        // prepend dot
                        if ($fileSuffix) {
                            $fileSuffix = '.' . $fileSuffix;
                        }
                        // grab filename without suffix
                        $fileBaseName = basename($fileName, $fileSuffix);
                        // create dest filename
                        $newFileName = md5($fileBaseName . microtime() . mt_rand()) . $fileSuffix;
                        $newFilePath = $originalDirectory . "/" . $newFileName;

                        // rename the file, and update the database data
                        $file->move($newFilePath);
                        $binaryFile->setAttribute('filename', $newFileName);
                        $binaryFile->store();
                    }
                }
            }
        }
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @param null $version
     */
    function deleteStoredObjectAttribute($contentObjectAttribute, $version = null)
    {
        $sys = eZSys::instance();
        $storageDirectory = $sys->storageDirectory();

        if ($version == null) {
            $binaryFiles = $this->getBinaryFiles($contentObjectAttribute);
            eZMultiBinaryFile::removeByID($contentObjectAttribute->attribute('id'), null);

            foreach ($binaryFiles as $binaryFile) {
                if ($binaryFile instanceof eZMultiBinaryFile) {
                    $mimeType = $binaryFile->attribute("mime_type");
                    list($prefix, $suffix) = explode('/', $mimeType);
                    unset($suffix);
                    $originalDirectory = $storageDirectory . '/original/' . $prefix;
                    $fileName = $binaryFile->attribute("filename");
                    $binaryObjectsWithSameFileName = eZMultiBinaryFile::fetchByFileName($fileName);
                    $filePath = $originalDirectory . "/" . $fileName;
                    $file = eZClusterFileHandler::instance($filePath);

                    if ($file->exists() and count($binaryObjectsWithSameFileName) < 1) {
                        $file->delete();
                    }
                }
            }
        } else {
            $binaryFiles = $this->getBinaryFiles($contentObjectAttribute, $version);
            foreach ($binaryFiles as $binaryFile) {
                if ($binaryFile instanceof eZMultiBinaryFile) {
                    // delete filedata from dfs
                    $mimeType = $binaryFile->attribute("mime_type");
                    list($prefix, $suffix) = explode('/', $mimeType);
                    unset($suffix);
                    $originalDirectory = $storageDirectory . '/original/' . $prefix;
                    $fileName = $binaryFile->attribute("filename");
                    $binaryObjectsWithSameFileName = eZMultiBinaryFile::fetchByFileName($fileName);
                    $filePath = $originalDirectory . "/" . $fileName;
                    $file = eZClusterFileHandler::instance($filePath);

                    if ($file->exists() and count($binaryObjectsWithSameFileName) < 1) {
                        $file->delete();
                    }
                }
            }
            eZMultiBinaryFile::removeByID($contentObjectAttribute->attribute('id'), $version);
        }
    }

    static function checkFileUploads()
    {
        $isFileUploadsEnabled = ini_get('file_uploads') != 0;
        if (!$isFileUploadsEnabled) {
            $isFileWarningAdded = $GLOBALS['eZBinaryFileTypeWarningAdded'];
            if (!isset($isFileWarningAdded) or
                !$isFileWarningAdded
            ) {
                eZAppendWarningItem(array(
                    'error' => array(
                        'type' => 'kernel',
                        'number' => eZError::KERNEL_NOT_AVAILABLE
                    ),
                    'text' => ezpI18n::tr('kernel/classes/datatypes',
                        'File uploading is not enabled. Please contact the site administrator to enable it.')
                ));
                $GLOBALS['eZBinaryFileTypeWarningAdded'] = true;
            }
        }
    }

    /**
     * @param eZHTTPTool $http
     * @param string $base
     * @param eZContentObjectAttribute $contentObjectAttribute
     *
     * @return int
     */
    function validateObjectAttributeHTTPInput($http, $base, $contentObjectAttribute)
    {
        self::checkFileUploads();

        return eZInputValidator::STATE_ACCEPTED;
    }

    /*
     * Get the new file array after maybe deleting and check with the existing db table data
     */
    function fetchObjectAttributeHTTPInput($http, $base, $contentObjectAttribute)
    {
        $displayNames = $http->hasPostVariable($base . "_display_name_" . $contentObjectAttribute->attribute("id")) ?
            $http->postVariable($base . "_display_name_" . $contentObjectAttribute->attribute("id")) : [];
        $displayGroups = $http->hasPostVariable($base . "_display_group_" . $contentObjectAttribute->attribute("id")) ?
            $http->postVariable($base . "_display_group_" . $contentObjectAttribute->attribute("id")) : [];
        $displayTexts = $http->hasPostVariable($base . "_display_text_" . $contentObjectAttribute->attribute("id")) ?
            $http->postVariable($base . "_display_text_" . $contentObjectAttribute->attribute("id")) : [];
        $displayOrders = $http->hasPostVariable($base . "_sort_" . $contentObjectAttribute->attribute("id")) ?
            $http->postVariable($base . "_sort_" . $contentObjectAttribute->attribute("id")) : [];

        if (!empty($displayNames) || !empty($displayGroups) || !empty($displayOrders) || !empty($displayTexts)){
            $storedDecorations = self::parseDecorations($contentObjectAttribute);
            foreach ($storedDecorations as $index => $storedDecoration){
                $fileName = $storedDecoration['original_filename'];
                if (isset($displayNames[$fileName])){
                    $storedDecorations[$index]['display_name'] = $displayNames[$fileName];
                }
                if (isset($displayGroups[$fileName])){
                    $storedDecorations[$index]['display_group'] = $displayGroups[$fileName];
                }
                if (isset($displayOrders[$fileName])){
                    $storedDecorations[$index]['display_order'] = $displayOrders[$fileName];
                }
                if (isset($displayTexts[$fileName])){
                    $storedDecorations[$index]['display_text'] = $displayTexts[$fileName];
                }
            }
            self::storeDecorations($contentObjectAttribute, $storedDecorations);

            return true;
        }

        return false;
    }

    /**
     * @param eZHTTPTool $http
     * @param string $action
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @param array $parameters
     *
     * @return bool
     */
    function customObjectAttributeHTTPAction($http, $action, $contentObjectAttribute, $parameters)
    {
        self::checkFileUploads();

        $base = 'ContentObjectAttribute';
        $sys = eZSys::instance();
        $storageDirectory = $sys->storageDirectory();

        if ($action == 'delete_binary') {

            $version = $contentObjectAttribute->attribute('version');
            $this->deleteStoredObjectAttribute($contentObjectAttribute, $version);
            self::storeDecorations($contentObjectAttribute, []);

        } elseif ($action == 'upload_multibinary') {

            if (!eZHTTPFile::canFetch($base . "_data_multibinaryfilename_" . $contentObjectAttribute->attribute("id"))) {
                return false;
            }

            /** @var eZHTTPFile $HTTPFile */
            $HTTPFile = eZHTTPFile::fetch($base . "_data_multibinaryfilename_" . $contentObjectAttribute->attribute("id"));
            $mimeData = eZMimeType::findByFileContents($HTTPFile->attribute("original_filename"));
            $result = array();

            $this->insertHTTPFile(
                $contentObjectAttribute->attribute('object'),
                $contentObjectAttribute->attribute('version'),
                $contentObjectAttribute->attribute('language_code'),
                $contentObjectAttribute,
                $HTTPFile,
                $mimeData,
                $result
            );

        } elseif ($action == 'sort_binary') {
            if ( $http->hasPostVariable( $base . "_sort_" . $contentObjectAttribute->attribute( "id" ) ) ) {
                $data = $http->postVariable( $base . "_sort_" . $contentObjectAttribute->attribute( "id" ) );
                $files = array();
                foreach ($data as $k => $v) {
                    $files[$v] = $k;
                }
                ksort($files);
                self::setFileOrder($contentObjectAttribute, $files);
                return true;
            }
            return false;

        } elseif ($action == 'delete_multibinary') {

            $values = $http->postVariable('CustomActionButton');
            $fileToDelete = key($values[$contentObjectAttribute->attribute('id') . '_' . $action]);
            $binaryFiles = $this->getBinaryFiles(
                $contentObjectAttribute,
                $contentObjectAttribute->attribute('version')
            );

            if (is_array($binaryFiles) && count($binaryFiles) > 0) {
                foreach ($binaryFiles as $binaryFile) {
                    if ($binaryFile instanceof eZMultiBinaryFile) {
                        if ($binaryFile->attribute('filename') == $fileToDelete) {
                            // delete filedata from database
                            eZMultiBinaryFile::removeByFileName(
                                $binaryFile->attribute('filename'),
                                $binaryFile->attribute('contentobject_attribute_id'),
                                $binaryFile->attribute('version'));
                            // delete the file from storage
                            $mimeType = $binaryFile->attribute('mime_type');
                            list($prefix, $suffix) = explode('/', $mimeType);
                            unset($suffix);
                            $originalDirectory = $storageDirectory . '/original/' . $prefix;
                            $fileName = $binaryFile->attribute('filename');
                            $filePath = $originalDirectory . "/" . $fileName;

                            // Check if there are any other records in ezbinaryfile that point to that fileName.
                            $binaryObjectsWithSameFileName = eZMultiBinaryFile::fetchByFileName($fileName);

                            $file = eZClusterFileHandler::instance($filePath);
                            if ($file->exists() and count($binaryObjectsWithSameFileName) < 1) {
                                $file->delete();
                            }
                            self::removeFileFromDecorations($contentObjectAttribute, $binaryFile->attribute('original_filename'));
                        }
                    }
                }
            }
        }

        return true;
    }

    function isHTTPFileInsertionSupported()
    {
        return true;
    }

    function isRegularFileInsertionSupported()
    {
        return true;
    }

    /**
     * @param eZContentObject $object
     * @param int $objectVersion
     * @param string $objectLanguage
     * @param eZContentObjectAttribute $objectAttribute
     * @param string $filePath
     * @param array $result
     *
     * @return bool
     * @throws Exception
     */
    function insertRegularFile(
        $object,
        $objectVersion,
        $objectLanguage,
        $objectAttribute,
        $filePath,
        &$result
    )
    {
        $result = array(
            'errors' => array(),
            'require_storage' => false
        );

        $binaryFiles = $this->getBinaryFiles(
            $objectAttribute,
            $objectVersion
        );

        $attributeID = $objectAttribute->attribute('id');

        $binary = eZMultiBinaryFile::fetch($attributeID, $objectVersion);
        if ($binary === null || empty($binary)) {
            $binary = eZMultiBinaryFile::create($attributeID, $objectVersion);
        } elseif (is_array($binary)) {
            $binary = $binary[0];
        }

        $fileName = basename($filePath);
        $mimeData = eZMimeType::findByFileContents($filePath);
        $storageDir = eZSys::storageDirectory();
        list($group, $type) = explode('/', $mimeData['name']);
        unset($type);
        $destination = $storageDir . '/original/' . $group;

        if (!file_exists($destination)) {
            if (!eZDir::mkdir($destination, false, true)) {
                $result['errors'][] = ezpI18n::tr(
                    'extension/ocmultibinary',
                    'Can not create local file. Please contact the site administrator.'
                );

                return false;
            }
        }

        if (!file_exists($filePath)) {
            $result['errors'][] = ezpI18n::tr(
                'extension/ocmultibinary',
                'Original file not found. Please contact the site administrator.'
            );

            return false;
        }

        // create dest filename in the same manner as eZHTTPFile::store()
        // grab file's suffix
        $fileSuffix = eZFile::suffix($fileName);
        // prepend dot
        if ($fileSuffix) {
            $fileSuffix = '.' . $fileSuffix;
        }
        // grab filename without suffix
        $fileBaseName = basename($fileName, $fileSuffix);
        // create dest filename
        $destFileName = md5($fileBaseName . microtime() . mt_rand()) . $fileSuffix;
        $destination = $destination . '/' . $destFileName;

        copy($filePath, $destination);

        $fileHandler = eZClusterFileHandler::instance();
        $fileHandler->fileStore($destination, 'binaryfile', true, $mimeData['name']);

        $binary->setAttribute("contentobject_attribute_id", $attributeID);
        $binary->setAttribute("version", $objectVersion);
        $binary->setAttribute("filename", $destFileName);
        $binary->setAttribute("original_filename", $fileName);
        $binary->setAttribute("mime_type", $mimeData['name']);

        $binary->store();

        $files = array($binary->attribute('original_filename'));

        $sys = eZSys::instance();
        $storageDirectory = $sys->storageDirectory();

        foreach ($binaryFiles as $binaryFile) {
            if ($binaryFile instanceof eZMultiBinaryFile) {
                if ($binaryFile->attribute('original_filename') == $binary->attribute('original_filename')) {

                    // delete filedata from database
                    eZMultiBinaryFile::removeByFileName(
                        $binaryFile->attribute('filename'),
                        $binaryFile->attribute('contentobject_attribute_id'),
                        $binaryFile->attribute('version')
                    );

                    // delete the file from storage
                    $mimeType = $binaryFile->attribute('mime_type');
                    list($prefix, $suffix) = explode('/', $mimeType);
                    unset($suffix);

                    $originalDirectory = $storageDirectory . '/original/' . $prefix;
                    $fileName = $binaryFile->attribute('filename');
                    $filePath = $originalDirectory . "/" . $fileName;
                    $file = eZClusterFileHandler::instance($filePath);
                    $binaryObjectsWithSameFileName = eZMultiBinaryFile::fetchByFileName($fileName);
                    if ($file->exists() and count($binaryObjectsWithSameFileName) < 1) {
                        $file->delete();
                    }
                } else {
                    $files[] = $binaryFile->attribute('original_filename');
                }
            }
        }

        self::addFileToDecorations($objectAttribute, $binary->attribute('original_filename'));

        return true;
    }

    /**
     * @param eZContentObject $object
     * @param int $objectVersion
     * @param string $objectLanguage
     * @param eZContentObjectAttribute $objectAttribute
     * @param eZHTTPFile $httpFile
     * @param array $mimeData
     * @param array $result
     *
     * @return bool
     */
    function insertHTTPFile(
        $object,
        $objectVersion,
        $objectLanguage,
        $objectAttribute,
        $httpFile,
        $mimeData,
        &$result
    )
    {
        $result = array(
            'errors' => array(),
            'require_storage' => false
        );

        $binaryFiles = $this->getBinaryFiles(
            $objectAttribute,
            $objectVersion
        );

        $attributeID = $objectAttribute->attribute('id');

        $binary = eZMultiBinaryFile::fetch($attributeID, $objectVersion);
        if ($binary === null || empty($binary)) {
            $binary = eZMultiBinaryFile::create($attributeID, $objectVersion);
        } elseif (is_array($binary)) {
            $binary = $binary[0];
        }

        $httpFile->setMimeType($mimeData['name']);

        $db = eZDB::instance();
        $db->begin();

        if (!$httpFile->store("original", false, false)) {
            $result['errors'][] = array(
                'description' => ezpI18n::tr('kernel/classes/datatypes/ezbinaryfile',
                    'Failed to store file %filename. Please contact the site administrator.', null,
                    array('%filename' => $httpFile->attribute("original_filename")))
            );

            return false;
        }

        $binary->setAttribute("contentobject_attribute_id", $attributeID);
        $binary->setAttribute("version", $objectVersion);
        $binary->setAttribute("filename", basename($httpFile->attribute("filename")));
        $binary->setAttribute("original_filename", $httpFile->attribute("original_filename"));
        $binary->setAttribute("mime_type", $mimeData['name']);

        $binary->store();

        $filePath = $httpFile->attribute('filename');
        $fileHandler = eZClusterFileHandler::instance();
        $fileHandler->fileStore($filePath, 'binaryfile', true, $mimeData['name']);

        $files = array($binary->attribute('original_filename'));

        $sys = eZSys::instance();
        $storageDirectory = $sys->storageDirectory();

        foreach ($binaryFiles as $binaryFile) {
            if ($binaryFile instanceof eZMultiBinaryFile) {
                if ($binaryFile->attribute('original_filename') == $binary->attribute('original_filename')) {

                    // delete filedata from database
                    eZMultiBinaryFile::removeByFileName(
                        $binaryFile->attribute('filename'),
                        $binaryFile->attribute('contentobject_attribute_id'),
                        $binaryFile->attribute('version')
                    );

                    // delete the file from storage
                    $mimeType = $binaryFile->attribute('mime_type');
                    list($prefix, $suffix) = explode('/', $mimeType);
                    unset($suffix);

                    $originalDirectory = $storageDirectory . '/original/' . $prefix;
                    $fileName = $binaryFile->attribute('filename');
                    $filePath = $originalDirectory . "/" . $fileName;
                    $file = eZClusterFileHandler::instance($filePath);
                    $binaryObjectsWithSameFileName = eZMultiBinaryFile::fetchByFileName($fileName);
                    if ($file->exists() and count($binaryObjectsWithSameFileName) < 1) {
                        $file->delete();
                    }
                } else {
                    $files[] = $binaryFile->attribute('original_filename');
                }
            }
        }

        self::addFileToDecorations($objectAttribute, $binary->attribute('original_filename'));

        $db->commit();

        return true;
    }

    /**
     * @param eZContentObjectAttribute $objectAttribute
     * @param $id
     *
     * @return array|bool
     */
    static function storedSingleFileInformation($objectAttribute, $id)
    {
        $binaryFile = eZPersistentObject::fetchObject(eZMultiBinaryFile::definition(),
            null,
            array(
                'contentobject_attribute_id' => $objectAttribute->attribute('id'),
                'version' => $objectAttribute->attribute('version'),
                'filename' => $id
            )
        );

        if ($binaryFile instanceof eZMultiBinaryFile) {
            return $binaryFile->storedFileInfo();
        }

        return false;
    }

    /**
     * @param eZContentObjectAttribute $objectAttribute
     * @param string $filename
     *
     * @return bool
     */
    static function handleSingleDownload(
        $objectAttribute,
        $filename
    )
    {
        $binaryFile = eZPersistentObject::fetchObject(eZMultiBinaryFile::definition(),
            null,
            array(
                'contentobject_attribute_id' => $objectAttribute->attribute('id'),
                'version' => $objectAttribute->attribute('version'),
                'filename' => $filename
            )
        );

        $contentObjectAttributeID = $objectAttribute->attribute('id');
        $version = $objectAttribute->attribute('version');

        if ($binaryFile instanceof eZMultiBinaryFile) {
            $db = eZDB::instance();
            $filename = eZDB::instance()->escapeString($filename);
            $db->query("UPDATE ezbinaryfile
                         SET download_count = ( download_count+1 )
                         WHERE contentobject_attribute_id = '{$contentObjectAttributeID}'
                         AND version = '{$version}'
                         AND filename = '{$filename}'");

            return true;
        }

        return false;
    }

    /**
     * @param eZHTTPTool $http
     * @param string $base
     * @param eZContentClassAttribute $classAttribute
     */
    function fetchClassAttributeHTTPInput($http, $base, $classAttribute)
    {
        $filesizeName = $base . self::MAX_FILESIZE_VARIABLE . $classAttribute->attribute('id');
        if ($http->hasPostVariable($filesizeName)) {
            $filesizeValue = $http->postVariable($filesizeName);
            $classAttribute->setAttribute(self::MAX_FILESIZE_FIELD, $filesizeValue);
        }
        $filenumberName = $base . self::MAX_NUMBER_OF_FILES_VARIABLE . $classAttribute->attribute('id');
        if ($http->hasPostVariable($filenumberName)) {
            $filenumberValue = $http->postVariable($filenumberName);
            $classAttribute->setAttribute(self::MAX_NUMBER_OF_FILES_FIELD, $filenumberValue);
        }
        if ($http->hasPostVariable($filesizeName)) {
            $allowDecorationName = $base . self::ALLOW_DECORATIONS_VARIABLE . $classAttribute->attribute('id');
            if ($http->hasPostVariable($allowDecorationName)) {
                $classAttribute->setAttribute(self::ALLOW_DECORATIONS_FIELD, 1);
            } else {
                $classAttribute->setAttribute(self::ALLOW_DECORATIONS_FIELD, 0);
            }
        }
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @param string $name
     *
     * @return array|string
     */
    function title($contentObjectAttribute, $name = 'display_name')
    {
        $names = array();
        $version = $contentObjectAttribute->attribute('version');
        $binaryFiles = $this->getBinaryFiles($contentObjectAttribute, $version);

        if (is_array($binaryFiles) && count($binaryFiles) > 0) {
            foreach ($binaryFiles as $binaryFile) {
                if ($binaryFile instanceof eZMultiBinaryFile) {
                    $names[] = $binaryFile->attribute($name);
                }
            }
        }
        if (count($names) > 0) {
            return join(', ', $names);
        }

        return array();
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     *
     * @return bool
     */
    function hasObjectAttributeContent($contentObjectAttribute)
    {
        $version = $contentObjectAttribute->attribute('version');
        $binaryFiles = $this->getBinaryFiles($contentObjectAttribute, $version);

        return count($binaryFiles) > 0;
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     *
     * @return bool|eZPersistentObject[]
     */
    function objectAttributeContent($contentObjectAttribute)
    {
        $version = $contentObjectAttribute->attribute('version');
        $binaryFiles = $this->getBinaryFiles($contentObjectAttribute, $version);

        if (!is_array($binaryFiles) || count($binaryFiles) == 0) {
            return false;
        }

        return $binaryFiles;
    }

    function isIndexable()
    {
        return true;
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     *
     * @return mixed
     */
    function metaData($contentObjectAttribute)
    {
        $version = $contentObjectAttribute->attribute('version');
        $binaryFiles = $this->getBinaryFiles($contentObjectAttribute, $version);
        $useMetadataExtractor = eZINI::instance('ocmultibinary.ini')->variable('SearchSettings', 'UseMetaDataExtractor') == 'enabled';

        $metaData = array();
        foreach ($binaryFiles as $file) {
            if ($file instanceof eZMultiBinaryFile) {
                if ($useMetadataExtractor){
                    $metaData[] = $file->metaData();
                }else {
                    $metaData[] = $file->attribute('original_filename');
                    $metaData[] = $file->attribute('display_name');
                    $displayGroup = $file->attribute('display_group');
                    if (!empty($displayGroup)) {
                        $metaData[] = $displayGroup;
                    }
                    $displayText = $file->attribute('display_text');
                    if (!empty($displayText)) {
                        $metaData[] = $displayText;
                    }
                }
            }
        }

        return implode(',', $metaData);
    }

    /**
     * @param eZContentClassAttribute $classAttribute
     * @param DOMNode $attributeNode
     * @param DOMNode $attributeParametersNode
     */
    function serializeContentClassAttribute($classAttribute, $attributeNode, $attributeParametersNode)
    {
        $dom = $attributeParametersNode->ownerDocument;
        $maxSize = $classAttribute->attribute(self::MAX_FILESIZE_FIELD);
        $maxSizeNode = $dom->createElement('max-size');
        $maxSizeNode->appendChild($dom->createTextNode($maxSize));
        $attributeParametersNode->appendChild($maxSizeNode);

        $maxNumberOf = $classAttribute->attribute(self::MAX_NUMBER_OF_FILES_FIELD);
        $maxNumberOfNode = $dom->createElement('max-number-of');
        $maxNumberOfNode->appendChild($dom->createTextNode($maxNumberOf));
        $attributeParametersNode->appendChild($maxNumberOfNode);

        $allowDecoration = $classAttribute->attribute(self::ALLOW_DECORATIONS_FIELD);
        $allowDecorationNode = $dom->createElement('allow-decoration');
        $allowDecorationNode->appendChild($dom->createTextNode($allowDecoration));
        $attributeParametersNode->appendChild($allowDecorationNode);
    }

    /**
     * @param eZContentClassAttribute $classAttribute
     * @param DOMElement $attributeNode
     * @param DOMElement $attributeParametersNode
     */
    function unserializeContentClassAttribute($classAttribute, $attributeNode, $attributeParametersNode)
    {
        $maxSizeNode = $attributeParametersNode->getElementsByTagName('max-size')->item(0);
        $maxSize = $maxSizeNode->textContent;
        $classAttribute->setAttribute(self::MAX_FILESIZE_FIELD, $maxSize);

        $maxNumberOfNode = $attributeParametersNode->getElementsByTagName('max-number-of')->item(0);
        $maxNumberOf = $maxNumberOfNode->textContent;
        $classAttribute->setAttribute(self::MAX_NUMBER_OF_FILES_FIELD, $maxNumberOf);

        $allowDecorationNode = $attributeParametersNode->getElementsByTagName('allow-decoration')->item(0);
        $allowDecoration = $allowDecorationNode->textContent;
        $classAttribute->setAttribute(self::ALLOW_DECORATIONS_FIELD, $allowDecoration);
    }

    /**
     * @param eZPackage $package
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return DOMElement
     */
    function serializeContentObjectAttribute($package, $objectAttribute)
    {
        $node = $this->createContentObjectAttributeDOMNode($objectAttribute);
        $version = $objectAttribute->attribute('version');
        $binaryFiles = $this->getBinaryFiles($objectAttribute, $version);
        // sort the files
        $sortConditions = unserialize($objectAttribute->attribute('data_text'));

        /** @var eZMultiBinaryFile [] $sortedBinaryFiles */
        $sortedBinaryFiles = array();
        if (is_array($sortConditions) && count($sortConditions) > 0) {
            foreach ($binaryFiles as $binaryFile) {
                if ($binaryFile instanceof eZMultiBinaryFile) {
                    // don't use array_search because array_search didn't read value with the key 0. don't know why...
                    foreach ($sortConditions as $key => $value) {
                        if ($binaryFile->attribute('original_filename') == $value) {
                            $sortedBinaryFiles[$key] = $binaryFile;
                        }
                    }
                }
            }

            ksort($sortedBinaryFiles);
        } else {
            $sortedBinaryFiles = $binaryFiles;
        }

        if (is_array($sortedBinaryFiles) && count($sortedBinaryFiles) > 0) {
            foreach ($sortedBinaryFiles as $key => $binaryFile) {
                $fileKey = md5(mt_rand());
                $package->appendSimpleFile($fileKey, $binaryFile->attribute('filepath'));

                $dom = $node->ownerDocument;
                $fileNode = $dom->createElement('multibinary-file');
                $fileNode->setAttribute('filesize', $binaryFile->attribute('filesize'));
                $fileNode->setAttribute('sort-id', $key);
                $fileNode->setAttribute('filename', $binaryFile->attribute('filename'));
                $fileNode->setAttribute('original-filename', $binaryFile->attribute('original_filename'));
                $fileNode->setAttribute('mime-type', $binaryFile->attribute('mime_type'));
                $fileNode->setAttribute('filekey', $fileKey);
                $fileNode->setAttribute('display-name', $binaryFile->attribute('display_name'));
                $fileNode->setAttribute('display-group', $binaryFile->attribute('display_group'));
                $fileNode->setAttribute('display-order', $binaryFile->attribute('display_order'));
                $fileNode->setAttribute('display-text', $binaryFile->attribute('display_text'));
                $node->appendChild($fileNode);
            }
        }

        return $node;
    }

    /**
     * @param eZPackage $package
     * @param eZContentObjectAttribute $objectAttribute
     * @param DOMElement|DOMNode $attributeNode
     *
     * @return bool
     */
    function unserializeContentObjectAttribute($package, $objectAttribute, $attributeNode)
    {
        /** @var DOMElement[] $fileNodes */
        $fileNodes = $attributeNode->getElementsByTagName('multibinary-file');
        $decorations = array();
        foreach ($fileNodes as $fileNode) {
            if (!is_object($fileNode) or !$fileNode->hasAttributes()) {
                return false;
            }
            $binaryFile = eZMultiBinaryFile::create($objectAttribute->attribute('id'),
                $objectAttribute->attribute('version'));
            $sourcePath = $package->simpleFilePath($fileNode->getAttribute('filekey'));

            if (!file_exists($sourcePath)) {
                eZDebug::writeError(
                    'The file "$sourcePath" does not exist, cannot initialize file attribute with it',
                    'eZBinaryFileType::unserializeContentObjectAttribute');

                return false;
            }

            $mimeType = $fileNode->getAttribute('mime-type');
            list($mimeTypeCategory, $mimeTypeName) = explode('/', $mimeType);
            unset($mimeTypeName);
            $destinationPath = eZSys::storageDirectory() . '/original/' . $mimeTypeCategory . '/';
            if (!file_exists($destinationPath)) {
                $oldumask = umask(0);
                if (!eZDir::mkdir($destinationPath, false, true)) {
                    umask($oldumask);

                    return false;
                }
                umask($oldumask);
            }

            $basename = basename($fileNode->getAttribute('filename'));
            while (file_exists($destinationPath . $basename)) {
                $basename = substr(md5(mt_rand()), 0, 8) . '.' . eZFile::suffix($fileNode->getAttribute('filename'));
            }

            eZFileHandler::copy($sourcePath, $destinationPath . $basename);
            eZDebug::writeNotice('Copied: ' . $sourcePath . ' to: ' . $destinationPath . $basename,
                'eZBinaryFileType::unserializeContentObjectAttribute()');

            $binaryFile->setAttribute('contentobject_attribute_id', $objectAttribute->attribute('id'));
            $binaryFile->setAttribute('filename', $basename);
            $binaryFile->setAttribute('original_filename', $fileNode->getAttribute('original-filename'));
            $binaryFile->setAttribute('mime_type', $fileNode->getAttribute('mime-type'));

            $decorations[$fileNode->getAttribute('sort-id')] = [
                'display_name' => $fileNode->getAttribute('display-name'),
                'display_group' => $fileNode->getAttribute('display-group'),
                'display_order' => $fileNode->getAttribute('display-order'),
                'display_text' => $fileNode->getAttribute('display-text'),
            ];

            $binaryFile->store();

            $fileHandler = eZClusterFileHandler::instance();
            $fileHandler->fileStore($destinationPath . $basename, 'binaryfile', true);
        }

        self::storeDecorations($objectAttribute, $decorations);

        return true;
    }

    function supportsBatchInitializeObjectAttribute()
    {
        return true;
    }

    /**
     * @param eZContentObjectAttribute $old
     * @param eZContentObjectAttribute $new
     * @param bool $options
     *
     * @return null
     */
    function diff($old, $new, $options = false)
    {
        $diff = new eZDiff();
        $diff->setDiffEngineType($diff->engineType('text'));
        $diff->initDiffEngine();

        return $diff->diff($old->toString(), $new->toString());
    }

    /**
     * @param eZContentObjectAttribute $objectAttribute
     *
     * @return string original_filename##display_name##display_group##display_text|...
     */
    function toString($objectAttribute)
    {
        $files = $this->getBinaryFiles($objectAttribute, $objectAttribute->attribute('version'));
        $stringArray = array();
        foreach ($files as $file) {
            $stringArray[] = implode('##', [
                $file->attribute('original_filename'),
                $file->attribute('display_name'),
                $file->attribute('display_group'),
                $file->attribute('display_text'),
            ]);
        }

        return implode('|', $stringArray);
    }

    /**
     * @param $objectAttribute
     * @param string $string file_path##display_name##display_group##display_text|...
     * @return bool|void
     * @throws Exception
     */
    function fromString($objectAttribute, $string)
    {
        $version = $objectAttribute->attribute('version');
        $this->deleteStoredObjectAttribute($objectAttribute, $version);

        $filePaths = explode('|', $string);
        if (empty($filePaths)) {
            return true;
        }

        $errors = array();
        $insertFileCount = 0;
        $insertDecorations = [];
        foreach ($filePaths as $stringItem) {
            $filePathParts = explode('##', $stringItem);
            $filePath = $filePathParts[0];
            $insertDecorations[basename($filePath)] = $filePathParts;
            $result = array();
            if ($this->insertRegularFile(
                $objectAttribute->attribute('object'),
                $objectAttribute->attribute('version'),
                $objectAttribute->attribute('language_code'),
                $objectAttribute,
                $filePath,
                $result)
            ) {
                $insertFileCount++;
            }
            if (count($result['errors']) > 0) {
                $errors[$filePath] = $result['errors'];
            }
        }

        if (count($errors) > 0) {
            eZDebug::writeError(var_export($errors, 1), __METHOD__);
        }

        $decorations = self::parseDecorations($objectAttribute);
        foreach ($decorations as $index => $decoration){
            if (isset($insertDecorations[$decoration['original_filename']])){
                if ($insertDecorations[$decoration['original_filename']][1])
                    $decorations[$index]['display_name'] = $insertDecorations[$decoration['original_filename']][1];
                if ($insertDecorations[$decoration['original_filename']][2])
                    $decorations[$index]['display_group'] = $insertDecorations[$decoration['original_filename']][2];
                if ($insertDecorations[$decoration['original_filename']][3])
                    $decorations[$index]['display_text'] = $insertDecorations[$decoration['original_filename']][3];
            }
        }
        self::storeDecorations($objectAttribute, $decorations);

        return count($filePaths) == $insertFileCount;
    }

    public static function setFileOrder($contentObjectAttribute, $sortedFiles)
    {
        $decorations = self::parseDecorations($contentObjectAttribute);

        foreach ($sortedFiles as $index => $file) {
            $isMissing = true;
            foreach ($decorations as $key => $decoration) {
                if ($decoration['original_filename'] == $file) {
                    $decorations[$key]['display_order'] = $index;
                    $isMissing = false;
                }
            }
            if ($isMissing){
                $decorations[] = [
                    'original_filename' => $file,
                    'display_name' => self::cleanFileName($file),
                    'display_group' => '',
                    'display_text' => '',
                    'display_order' => $index,
                ];
            }
        }

        self::storeDecorations($contentObjectAttribute, $decorations);
    }

    public static function parseDecorations($contentObjectAttribute)
    {
        $decorations = array();
        $storedDecorations = unserialize($contentObjectAttribute->attribute('data_text'));
        if (!empty($storedDecorations)){
            foreach ($storedDecorations as $index => $storedDecoration){
                if (!is_array($storedDecoration)){
                    $decorations[] = [
                        'original_filename' => $storedDecoration,
                        'display_name' => self::cleanFileName($storedDecoration),
                        'display_group' => '',
                        'display_text' => '',
                        'display_order' => $index,
                    ];
                }else{
                    $decorations[] = array_merge([
                        'display_name' => '',
                        'display_group' => '',
                        'display_text' => '',
                        'display_order' => $index,
                    ], $storedDecoration);
                }
            }
        }
        usort($decorations, function ($a, $b){
            if ($a['display_order'] == $b['display_order']) {
                return 0;
            }
            return ($a['display_order'] < $b['display_order']) ? -1 : 1;
        });

        return $decorations;
    }

    private static function removeFileFromDecorations($contentObjectAttribute, $filename)
    {
        $storedDecorations = self::parseDecorations($contentObjectAttribute);
        foreach ($storedDecorations as $index => $storedDecoration){
            if ($storedDecoration['original_filename'] == $filename){
                unset($storedDecorations[$index]);
            }
        }
        self::storeDecorations($contentObjectAttribute, $storedDecorations);
    }

    private static function addFileToDecorations($contentObjectAttribute, $filename)
    {
        $binaryFiles = (array)eZMultiBinaryFile::fetch($contentObjectAttribute->attribute('id'), $contentObjectAttribute->attribute('version'));

        $decorations = $storedDecorations = self::parseDecorations($contentObjectAttribute);
        $storedDecorationsFiles = array_column($storedDecorations, 'original_filename');
        $lastIndex = count($storedDecorationsFiles);
        foreach ($binaryFiles as $binaryFile){
            if (!in_array($binaryFile->attribute('original_filename'), $storedDecorationsFiles)){
                $lastIndex++;
                $decorations[] = [
                    'original_filename' => $binaryFile->attribute('original_filename'),
                    'display_name' => self::cleanFileName($binaryFile->attribute('original_filename')),
                    'display_group' => '',
                    'display_text' => '',
                    'display_order' => $lastIndex,
                ];
            }
        }

        self::storeDecorations($contentObjectAttribute, $decorations);
    }

    private static function storeDecorations($contentObjectAttribute, $data)
    {
        $contentObjectAttribute->setAttribute('data_text', serialize($data));
        $contentObjectAttribute->store();
    }

    private static function cleanFileName($filename)
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

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @return string
     */
    function viewTemplate( $contentObjectAttribute )
    {
        $suffix = '';
        if ($contentObjectAttribute->contentClassAttribute()->attribute(self::ALLOW_DECORATIONS_FIELD)) {
            $suffix = '_decorated';
        }

        return $this->DataTypeString . $suffix;
    }

    function editTemplate( $contentObjectAttribute )
    {
        $suffix = '';
        if ($contentObjectAttribute->contentClassAttribute()->attribute(self::ALLOW_DECORATIONS_FIELD)) {
            $suffix = '_decorated';
        }

        return $this->DataTypeString . $suffix;
    }
}

eZDataType::register(OCMultiBinaryType::DATA_TYPE_STRING, 'ocMultiBinaryType');
