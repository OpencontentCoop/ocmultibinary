<?php

use Opencontent\Opendata\Api\AttributeConverter\File;
use Opencontent\Opendata\Api\PublicationProcess;
use Opencontent\Opendata\Api\Exception\InvalidInputException;

class OCMultiBinaryOpendataConverter extends File
{
    public function get(eZContentObjectAttribute $attribute)
    {
        $content = array(
            'id' => intval($attribute->attribute('id')),
            'version' => intval($attribute->attribute('version')),
            'identifier' => $this->classIdentifier . '/' . $this->identifier,
            'datatype' => $attribute->attribute('data_type_string'),
            'content' => $attribute->hasContent() ? $attribute->toString() : null
        );

        if ($attribute instanceof eZContentObjectAttribute
            && $attribute->hasContent()
        ) {
            /** @var \eZBinaryFile $file */
            $files = $attribute->content();
            $data = array();
            foreach ($files as $file) {


                $url = 'ocmultibinary/download/' . $attribute->attribute('contentobject_id')
                    . '/' . $attribute->attribute('id')
                    . '/' . $attribute->attribute('version')
                    . '/' . $file->attribute('filename')
                    . '/file'
                    . '/' . $file->attribute('original_filename');
                eZURI::transformURI($url, false, 'full');

                $data[] = array(
                    'filename' => $file->attribute('original_filename'),
                    'url' => $url
                );
            }

            $content['content'] = $data;
        }

        return $content;
    }

    public function set($data, PublicationProcess $process)
    {
        $values = array();
        foreach ($data as $item) {

            if (!isset($item['url'])) {
                $item['url'] = null;
            }

            if (!isset($item['file'])) {
                $item['file'] = null;
            }

            $values[] = $this->getTemporaryFilePath($item['filename'], $item['url'], $item['file']);
        }

        return implode('|', $values);
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if (is_array($data)) {
            foreach ($data as $item) {


                if (!isset($item['filename'])) {
                    throw new InvalidInputException('Missing filename', $identifier, $item);
                }

                if (isset($item['url']) && !eZHTTPTool::getDataByURL(trim($item['url']), true)) {
                    throw new InvalidInputException('Url not responding', $identifier, $item);
                }

                if (isset($item['file'])
                    && !(base64_encode(base64_decode($item['file'], true)) === $item['file'])
                ) {
                    throw new InvalidInputException('Invalid base64 encoding', $identifier, $item);
                }
            }

        } else {
            throw new InvalidInputException('Invalid data format', $identifier, $data);
        }
    }

    public function type(\eZContentClassAttribute $attribute)
    {
        return array(
            'identifier' => 'multifile',
            'format' => array(
                array(
                    'url' => 'public http uri',
                    'file' => 'base64 encoded file (url alternative)',
                    'filename' => 'string'
                )
            )
        );
    }

    public function toCSVString($content, $params = null)
    {
        $data = array();
        if (is_array($content)) {
            foreach ($content as $item) {
                $data[] = $item['url'];
            }

        }

        return implode('|', $data);
    }

}
