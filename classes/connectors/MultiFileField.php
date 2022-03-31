<?php

use Opencontent\Ocopendata\Forms\Connectors\OpendataConnector\CleanableFieldConnectorInterface;
use Opencontent\Ocopendata\Forms\Connectors\OpendataConnector\UploadFieldConnector;

class MultiFileField extends UploadFieldConnector implements CleanableFieldConnectorInterface
{
    private static $cleanups = [];

    public function getData()
    {
        if ($rawContent = $this->getContent()) {
            $attribute = eZContentObjectAttribute::fetch($rawContent['id'], $rawContent['version']);

            if ($attribute instanceof eZContentObjectAttribute && $attribute->hasContent()) {
                $files = $attribute->content();
                $data = [];
                /** @var \eZBinaryFile $file */
                foreach ($files as $file) {
                    $url = 'ocmultibinary/download/' . $attribute->attribute('contentobject_id')
                        . '/' . $attribute->attribute('id')
                        . '/' . $attribute->attribute('version')
                        . '/' . $file->attribute('filename')
                        . '/file'
                        . '/' . $file->attribute('original_filename');
                    eZURI::transformURI($url, false, 'full');

                    $fileinfo = OCMultiBinaryType::storedSingleFileInformation($attribute, $file->attribute('original_filename'));

                    $data[] = [
                        'id' => OCMultiBinaryType::generateAlreadyStoredFileId($attribute, $file),
                        'name' => $file->attribute('original_filename'),
                        'size' => $file->attribute('filesize'),
                        'url' => $url,
                        'thumbnailUrl' => $this->getIconImageData($fileinfo['filepath']),
                        'deleteUrl' => $this->getServiceUrl('upload', ['delete' => $file->attribute('original_filename')]),
                        'deleteType' => "GET",
                    ];
                }

                return $data;
            }
        }

        return null;
    }

    private function getIconImageData($filePath)
    {
        $mime = eZMimeType::findByURL($filePath);
        return 'data:image/png;base64,' . base64_encode(file_get_contents($this->getIconByMimeType($mime['name'])));
    }

    private function getIconByMimeType($mimeName, $useFullPath = true, $size = '32x32')
    {
        $wtiOperator = new \eZWordToImageOperator();
        $ini = \eZINI::instance('icon.ini');
        $repository = $ini->variable('IconSettings', 'Repository');
        $theme = $ini->variable('IconSettings', 'Theme');
        $themeINI = \eZINI::instance('icon.ini', $repository . '/' . $theme);
        $icon = $wtiOperator->iconGroupMapping($ini, $themeINI,
            'MimeIcons', 'MimeMap',
            strtolower($mimeName));
        $iconPath = '/' . $repository . '/' . $theme;
        $iconPath .= '/' . $size;
        $iconPath .= '/' . $icon;
        $siteDir = '';
        if ($useFullPath) {
            $siteDir = rtrim(str_replace('index.php', '', \eZSys::siteDir()), '\/');
        }
        return $siteDir . $iconPath;
    }

    public function getSchema()
    {
        return [
            "title" => $this->attribute->attribute('name'),
            "type" => "array",
            'required' => (bool)$this->attribute->attribute('is_required'),
        ];
    }

    public function getOptions()
    {
        $maxNum = (int)$this->attribute->attribute(OCMultiBinaryType::MAX_NUMBER_OF_FILES_FIELD);
        if ($maxNum === 0) {
            $maxNum = -1;
        }

        $classAttributeIdentifier = $this->class->attribute('identifier') . '/' . $this->attribute->attribute('identifier');
        $fileTypes = null;
        if (eZINI::instance('ocmultibinary.ini')->hasVariable('AcceptFileTypesRegex', 'ClassAttributeIdentifier')) {
            $acceptFileTypesClassAttributeIdentifier = eZINI::instance('ocmultibinary.ini')->variable('AcceptFileTypesRegex', 'ClassAttributeIdentifier');
            if (isset($acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier])) {
                $fileTypes = $acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier];
            }
        }

        return [
            "helper" => $this->attribute->attribute('description'),
            "type" => "upload",
            "upload" => [
                "url" => $this->getServiceUrl('upload'),
                "autoUpload" => true,
                "showSubmitButton" => false,
                "disableImagePreview" => true,
                "maxFileSize" => 25000000, //@todo,
                "maxNumberOfFiles" => $maxNum,
                "acceptFileTypes" => $fileTypes,
            ],
            "showUploadPreview" => false,
            "maxNumberOfFiles" => $maxNum,
            "fileTypes" => null,
            "label" => $this->attribute->attribute('name'),
            "multiple" => true,
        ];
    }

    public function setPayload($files)
    {
        if (count($files)) {
            $data = [];
            foreach ($files as $file) {
                if (empty($file['id'])) continue;
                if ($info = OCMultiBinaryType::isAlreadyStoredFileId($file['id'])) {
                    $data[] = [
                        'stored' => $file['id'],
                        'filename' => $info['filename'],
                    ];
                } else {
                    $filePath = $this->getUploadDir() . $file['name'];
                    $fileHandler = eZClusterFileHandler::instance($filePath);
                    if ($fileHandler->exists()) {
                        $fileContent = base64_encode($fileHandler->fetchContents());
                        $data[] = [
                            'file' => $fileContent,
                            'filename' => $file['name'],
                        ];
                        self::$cleanups[] = $filePath;
                        self::$cleanups[] = $this->getUploadDir() . 'thumbnail/' . $file['name'];
                    }
                }
            }
            return $data;
        }

        return null;
    }

    protected function getUploadDir()
    {
        $directory = md5(\eZUser::currentUserID() . $this->class->attribute('identifier') . $this->getIdentifier() . $this->getUploadParamNameSuffix());
        $uploadDir = eZSys::varDirectory() . '/fileupload/' . $directory . '/';
        \eZDir::mkdir($uploadDir, false, true);

        return $uploadDir;
    }

    protected function getUploadParamNameSuffix()
    {
        return '_files';
    }

    public function handleUpload($paramNamePrefix = null)
    {
        if ($this->getHelper()->hasParameter('preview')) {
            return $this->doPreview();

        } elseif ($this->getHelper()->hasParameter('delete')) {
            return $this->doDelete();

        } else {
            return $this->doUpload($paramNamePrefix);
        }
    }

    private function doPreview()
    {
        $fileName = $this->getHelper()->getParameter('preview');
        $filePath = $this->getUploadDir() . $fileName;

        if ($this->isImage($filePath)) {
            $filePath = $this->getUploadDir() . 'thumbnail/' . $fileName;
        }

        $file = eZClusterFileHandler::instance($filePath);

        if ($file->exists()) {
            $mime = eZMimeType::findByURL($filePath);

            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: inline; filename="' . $fileName . '"');
            header('Content-Length: ' . $file->size());
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $file->mtime()));
            if ($mime['name']) {
                header('Content-Type: ' . $mime['name']);
            }

            echo $file->fetchContents();
            eZExecution::cleanExit();
        }

        return false;
    }

    protected function isImage($filePath)
    {
        $mime = eZMimeType::findByURL($filePath);
        list($group, $type) = explode('/', $mime['name']);

        return $group == 'image';
    }

    private function doDelete()
    {
        $fileName = $this->getHelper()->getParameter('delete');

        $filePath = $this->getUploadDir() . $fileName;
        $file = eZClusterFileHandler::instance($filePath);
        if ($file->exists()) {
            $file->delete();
            $file->purge();
        }

        $filePath = $this->getUploadDir() . 'thumbnail/' . $fileName;
        $file = eZClusterFileHandler::instance($filePath);
        if ($file->exists()) {
            $file->delete();
            $file->purge();
        }

        return [
            'files' => [
                [
                    $fileName => true,
                ],
            ],
        ];
    }

    private function doUpload($paramNamePrefix)
    {
        $paramName = $this->getIdentifier() . $this->getUploadParamNameSuffix();
        if ($paramNamePrefix) {
            $fileNames = array_keys($_FILES);
            foreach ($fileNames as $fileName) {
                if (strpos($fileName, $paramNamePrefix) === 0 && strpos($fileName, $paramName) !== false) {
                    $paramName = $fileName;
                }
            }
        }

        $options = [];
        $classAttributeIdentifier = $this->class->attribute('identifier') . '/' . $this->attribute->attribute('identifier');
        if (eZINI::instance('ocmultibinary.ini')->hasVariable('AcceptFileTypesRegex', 'ClassAttributeIdentifier')) {
            $acceptFileTypesClassAttributeIdentifier = eZINI::instance('ocmultibinary.ini')->variable('AcceptFileTypesRegex', 'ClassAttributeIdentifier');
            if (isset($acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier])) {
                $options['accept_file_types'] = $acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier];
            }
        }
        $options['upload_dir'] = $this->getUploadDir();
        $options['download_via_php'] = true;
        $options['param_name'] = $paramName;

        /** @var UploadHandler $uploadHandler */
        $uploadHandler = new UploadHandler($options, false, [
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

        $files = [];
        foreach ($data[$options['param_name']] as $file) {
            if (isset($file->error)) {
                $files[] = [
                    'id' => false,
                    'name' => $file->error,
                    'size' => 0,
                    'url' => false,
                    'thumbnailUrl' => false,
                    'tempFileCheck' => false,
                ];
            }else{
                $thumbnailUrl = $this->getThumbnailUrl($file->name);
                $tempFileCheck = file_exists($this->getUploadDir() . $file->name);
                if ($tempFileCheck) {
                    $filePath = $this->getUploadDir() . $file->name;
                    eZClusterFileHandler::instance($filePath)->storeContents(file_get_contents($filePath));
                    eZClusterFileHandler::instance($filePath)->deleteLocal();

                    $thumbPath = $this->getUploadDir() . 'thumbnail/' . $file->name;
                    if (file_exists($thumbPath)) {
                        eZClusterFileHandler::instance($thumbPath)->storeContents(file_get_contents($thumbPath));
                        eZClusterFileHandler::instance($thumbPath)->deleteLocal();
                    }
                }
                $files[] = [
                    'id' => uniqid($file->name),
                    'name' => $file->name,
                    'size' => $file->size,
                    'url' => $this->getServiceUrl('upload', ['preview' => $file->name]),
                    'thumbnailUrl' => $thumbnailUrl,
                    'deleteUrl' => $this->getServiceUrl('upload', ['delete' => $file->name]),
                    'deleteType' => "GET",
                    'tempFileCheck' => $tempFileCheck,
                ];
            }
        }

        return ['files' => $files];
    }

    private function getThumbnailUrl($filename)
    {
        $thumbnailUrl = $this->getServiceUrl('upload', ['preview' => $filename]);
        $filePath = $this->getUploadDir() . $filename;
        if (!$this->isImage($filePath)) {
            $thumbnailUrl = $this->getIconImageData($filePath);
        }

        return $thumbnailUrl;
    }

    /**
     * @param \SplFileObject[] $fileObjectList
     */
    public function insertFiles($fileObjectList)
    {
        $files = [];
        foreach ($fileObjectList as $file) {

            $filePath = $this->getUploadDir() . $file->getBasename();
            eZClusterFileHandler::instance($filePath)->storeContents(file_get_contents($file->getRealPath()));

            $tempFileCheck = file_exists($this->getUploadDir() . $file->getBasename());

            if ($this->isImage($this->getUploadDir() . $file->getBasename())) {
                if (!is_dir($this->getUploadDir() . 'thumbnail')) {
                    \eZDir::mkdir($this->getUploadDir() . 'thumbnail');
                }
                $thumbnailPath = $this->getUploadDir() . 'thumbnail/' . $file->getBasename();
                $cmd = 'convert ' . escapeshellarg($filePath) . ' -auto-orient -coalesce  -resize ' . escapeshellarg('80X80^') . ' -gravity center  -crop ' . escapeshellarg('80X80+0+0') . ' +repage ' . escapeshellarg($thumbnailPath);
                exec($cmd, $output, $error);
                if ($error) {
                    \eZDebug::writeError(implode('\n', $output), __METHOD__);
                }
            }

            $files[] = [
                'id' => uniqid($file->getBasename()),
                'name' => $file->getBasename(),
                'size' => $file->getSize(),
                'url' => $this->getServiceUrl('upload', ['preview' => $file->getBasename()]),
                'thumbnailUrl' => $this->getThumbnailUrl($file->getBasename()),
                'deleteUrl' => $this->getServiceUrl('upload', ['delete' => $file->getBasename()]),
                'deleteType' => "GET",
                'tempFileCheck' => $tempFileCheck,
            ];
        }

        return ['files' => $files];
    }

    public function cleanup()
    {
        foreach (self::$cleanups as $filePath) {
            $file = eZClusterFileHandler::instance($filePath);
            if ($file->exists()) {
                $file->delete();
                $file->purge();
            }
        }
        self::$cleanups = [];
    }
}
