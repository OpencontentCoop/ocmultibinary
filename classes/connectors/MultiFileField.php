<?php

use Opencontent\Ocopendata\Forms\Connectors\OpendataConnector\CleanableFieldConnectorInterface;
use Opencontent\Ocopendata\Forms\Connectors\OpendataConnector\UploadFieldConnector;

class MultiFileField extends UploadFieldConnector implements CleanableFieldConnectorInterface
{
    private static $cleanups = [];

    private $hasDecoration;

    public function __construct($attribute, $class, $helper)
    {
        parent::__construct($attribute, $class, $helper);
        $this->hasDecoration = $attribute->attribute(OCMultiBinaryType::ALLOW_DECORATIONS_FIELD);
    }

    public function getData()
    {
        if ($rawContent = $this->getContent()) {
            $attribute = eZContentObjectAttribute::fetch($rawContent['id'], $rawContent['version']);

            if ($attribute instanceof eZContentObjectAttribute && $attribute->hasContent()) {
                $files = $attribute->content();
                $data = [];
                /** @var eZBinaryFile $file */
                foreach ($files as $file) {
                    $url = 'ocmultibinary/download/' . $attribute->attribute('contentobject_id')
                        . '/' . $attribute->attribute('id')
                        . '/' . $attribute->attribute('version')
                        . '/' . urlencode($file->attribute('filename'))
                        . '/file'
                        . '/' . urlencode($file->attribute('original_filename'));
                    eZURI::transformURI($url, false, 'full');

                    $fileInfo = OCMultiBinaryType::storedSingleFileInformation(
                        $attribute,
                        $file->attribute('original_filename')
                    );

                    $data[] = [
                        'file' => [
                            'id' => OCMultiBinaryType::generateAlreadyStoredFileId($attribute, $file),
                            'name' => $file->attribute('original_filename'),
                            'size' => $file->attribute('filesize'),
                            'url' => $url,
                            'thumbnailUrl' => $this->getIconImageData($fileInfo['filepath']),
                            'deleteUrl' => $this->getServiceUrl(
                                'upload',
                                ['delete' => $file->attribute('original_filename')]
                            ),
                            'deleteType' => 'GET',
                        ],
                        'display_name' => $file->attribute('display_name'),
                        'display_group' => $file->attribute('display_group'),
                        'display_text' => $file->attribute('display_text'),
                    ];
                }

                return $data;
            }
        }

        return [];
    }

    public function getSchema()
    {
        return [
            'title' => $this->attribute->attribute('name'),
            'type' => 'array',
            'required' => (bool)$this->attribute->attribute('is_required'),
            'items' => [
                'type' => 'object',
                'properties' => [
                    'file' => [
                        'title' => 'File',
                        'type' => 'array',
                    ],
                    'display_name' => [
                        'title' => \ezpI18n::tr('extension/ocmultibinary', 'Display name'),
                        'type' => 'string',
                    ],
                    'display_group' => [
                        'title' => \ezpI18n::tr('extension/ocmultibinary', 'Display group'),
                        'type' => 'string',
                    ],
                    'display_text' => [
                        'title' => \ezpI18n::tr('extension/ocmultibinary', 'Text'),
                        'type' => 'string',
                    ],
                ],
            ],
        ];
    }

    public function getOptions()
    {
        $maxNum = (int)$this->attribute->attribute(OCMultiBinaryType::MAX_NUMBER_OF_FILES_FIELD);
        if ($maxNum === 0) {
            $maxNum = -1;
        }

        $classAttributeIdentifier = $this->class->attribute('identifier') . '/'
            . $this->attribute->attribute('identifier');
        $fileTypes = null;
        if (eZINI::instance('ocmultibinary.ini')->hasVariable('AcceptFileTypesRegex', 'ClassAttributeIdentifier')) {
            $acceptFileTypesClassAttributeIdentifier = eZINI::instance('ocmultibinary.ini')
                ->variable('AcceptFileTypesRegex', 'ClassAttributeIdentifier');
            if (isset($acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier])) {
                $fileTypes = $acceptFileTypesClassAttributeIdentifier[$classAttributeIdentifier];
            }
        }

        $options = [
            'helper' => $this->attribute->attribute('description'),
        ];

        if ($this->hasDecoration) {
            $options['type'] = 'table';
        } else {
            $options['toolbarSticky'] = true;
        }
        $options['items']['fields'] = [
            'file' => [
                'type' => 'upload',
                'upload' => [
                    'url' => $this->getServiceUrl('upload'),
                    'autoUpload' => true,
                    'showSubmitButton' => false,
                    'disableImagePreview' => true,
                    'maxFileSize' => 25000000, //@todo,
                    'acceptFileTypes' => $fileTypes,
                ],
                'showUploadPreview' => false,
                'maxNumberOfFiles' => 1,
                'fileTypes' => null,
                'label' => $this->attribute->attribute('name'),
                'multiple' => false,
                'hideDeleteButton' => true,
                'dropZoneMessage' => ' ',
            ],
            'display_name' => ['type' => $this->hasDecoration ? 'textarea' : 'hidden'],
            'display_group' => ['type' => $this->hasDecoration ? 'textarea' : 'hidden'],
            'display_text' => ['type' => $this->hasDecoration ? 'textarea' : 'hidden'],
        ];

        return $options;
    }

    private function getIconImageData($filePath)
    {
        $mime = eZMimeType::findByURL($filePath);
        return 'data:image/png;base64,' . base64_encode(file_get_contents($this->getIconByMimeType($mime['name'])));
    }

    private function getIconByMimeType($mimeName, $useFullPath = true, $size = '32x32')
    {
        $wtiOperator = new eZWordToImageOperator();
        $ini = eZINI::instance('icon.ini');
        $repository = $ini->variable('IconSettings', 'Repository');
        $theme = $ini->variable('IconSettings', 'Theme');
        $themeINI = eZINI::instance('icon.ini', $repository . '/' . $theme);
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
            $siteDir = rtrim(str_replace('index.php', '', eZSys::siteDir()), '\/');
        }
        return $siteDir . $iconPath;
    }

    public function setPayload($postData)
    {
        $data = [];
        if (count($postData)) {
            foreach ($postData as $datum) {
                $file = array_pop($datum['file']);
                $item = $this->parseFilePayload($file);
                if ($item) {
                    $item['displayName'] = $datum['display_name'] ?? '';
                    $item['group'] = $datum['display_group'] ?? '';
                    $item['text'] = $datum['display_text'] ?? '';
                    $data[] = $item;
                }
            }
        }

        return $data;
    }

    protected function calculateUploadParamName($paramNamePrefix)
    {
        $fileNames = array_keys($_FILES);
        foreach ($fileNames as $fileName) {
            if (strpos($fileName, $this->getIdentifier() . '_') === 0) {
                return $fileName;
            }
        }
        return parent::calculateUploadParamName($paramNamePrefix);
    }

    private function parseFilePayload($file): ?array
    {
        if (empty($file['id'])) {
            return null;
        }

        if ($info = OCMultiBinaryType::isAlreadyStoredFileId($file['id'])) {
            return [
                'stored' => $file['id'],
                'filename' => $info['filename'],
            ];
        } else {
            $filePath = $this->getUploadDir() . $file['name'];
            $fileHandler = new eZFSFileHandler($filePath);
            if ($fileHandler->exists()) {
                $fileContent = base64_encode($fileHandler->fetchContents());
                self::$cleanups[] = $filePath;
                self::$cleanups[] = $this->getUploadDir() . 'thumbnail/' . $file['name'];
                return [
                    'file' => $fileContent,
                    'filename' => $file['name'],
                ];
            }
        }

        return null;
    }

    protected function getUploadParamNameSuffix()
    {
        return '_files';
    }

    protected function getThumbnailUrl($filename)
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
