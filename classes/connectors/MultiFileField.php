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
                        . '/' . urlencode($file->attribute('filename'))
                        . '/file'
                        . '/' . urlencode($file->attribute('original_filename'));
                    eZURI::transformURI($url, false, 'full');

                    $fileinfo = OCMultiBinaryType::storedSingleFileInformation(
                        $attribute,
                        $file->attribute('original_filename')
                    );

                    $data[] = [
                        'id' => self::generateAlreadyStoredFileId($attribute, $file),
                        'name' => $file->attribute('original_filename'),
                        'size' => $file->attribute('filesize'),
                        'url' => $url,
                        'thumbnailUrl' => $this->getIconImageData($fileinfo['filepath']),
                        'deleteUrl' => $this->getServiceUrl(
                            'upload',
                            ['delete' => $file->attribute('original_filename')]
                        ),
                        'deleteType' => "GET",
                    ];
                }

                return $data;
            }
        }

        return null;
    }

    private static function generateAlreadyStoredFileId(eZContentObjectAttribute $attribute, eZMultiBinaryFile $file)
    {
        return 'stored_' . base64_encode(
                $attribute->attribute('id') . '_' .
                $attribute->attribute('version') . '_' .
                $file->attribute('original_filename')
            );
    }

    private static function isAlreadyStoredFileId($filePath)
    {
        if (strpos($filePath, 'stored_') !== false) {
            $info = base64_decode(str_replace('stored_', '', $filePath));
            [$storedId, $storedVersion, $storedFilename] = explode('_', $info, 3);
            return [
                'id' => $storedId,
                'version' => $storedVersion,
                'filename' => $storedFilename,
            ];
        }

        return false;
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
        $icon = $wtiOperator->iconGroupMapping(
            $ini,
            $themeINI,
            'MimeIcons',
            'MimeMap',
            strtolower($mimeName)
        );
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

        $classAttributeIdentifier = $this->class->attribute('identifier') . '/' . $this->attribute->attribute(
                'identifier'
            );
        $fileTypes = null;
        if (eZINI::instance('ocmultibinary.ini')->hasVariable('AcceptFileTypesRegex', 'ClassAttributeIdentifier')) {
            $acceptFileTypesClassAttributeIdentifier = eZINI::instance('ocmultibinary.ini')
                ->variable('AcceptFileTypesRegex', 'ClassAttributeIdentifier');
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
                if (empty($file['id'])) {
                    continue;
                }
                if ($info = self::isAlreadyStoredFileId($file['id'])) {
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

    private function doUpload($paramNamePrefix)
    {
        $acceptFileTypes = null;
        $classAttributeIdentifier = $this->class->attribute('identifier')
            . '/' . $this->attribute->attribute('identifier');
        if (eZINI::instance('ocmultibinary.ini')->hasVariable('AcceptFileTypesRegex', 'ClassAttributeIdentifier')) {
            $acceptFileTypesClassAttributeIdentifier = eZINI::instance('ocmultibinary.ini')->variable(
                'AcceptFileTypesRegex',
                'ClassAttributeIdentifier'
            );
            if (isset($acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier])) {
                $acceptFileTypes = $acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier];
            }
        }

        return parent::handleUpload($paramNamePrefix, $acceptFileTypes);
    }

    protected function getUploadErrors(): ?array
    {
        return [
            1 => ezpI18n::tr(
                'extension/ocmultibinary',
                'The uploaded file exceeds the upload_max_filesize directive in php.ini'
            ),
            2 => ezpI18n::tr(
                'extension/ocmultibinary',
                'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'
            ),
            3 => ezpI18n::tr('extension/ocmultibinary', 'The uploaded file was only partially uploaded'),
            4 => ezpI18n::tr('extension/ocmultibinary', 'No file was uploaded'),
            6 => ezpI18n::tr('extension/ocmultibinary', 'Missing a temporary folder'),
            7 => ezpI18n::tr('extension/ocmultibinary', 'Failed to write file to disk'),
            8 => ezpI18n::tr('extension/ocmultibinary', 'A PHP extension stopped the file upload'),
            'post_max_size' => ezpI18n::tr(
                'extension/ocmultibinary',
                'The uploaded file exceeds the post_max_size directive in php.ini'
            ),
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
        ];
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
