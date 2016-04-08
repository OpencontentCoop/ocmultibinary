<?php

class eZBinaryFile2 extends eZBinaryFile
{
    function __construct( $row )
    {
        $this->eZPersistentObject( $row );
    }

    static function definition()
    {
        static $definition = array( 'fields' => array( 'contentobject_attribute_id' => array( 'name' => 'ContentObjectAttributeID',
                                                                                'datatype' => 'integer',
                                                                                'default' => 0,
                                                                                'required' => true,
                                                                                'foreign_class' => 'eZContentObjectAttribute',
                                                                                'foreign_attribute' => 'id',
                                                                                'multiplicity' => '1..*' ),
                                         'version' => array( 'name' => 'Version',
                                                             'datatype' => 'integer',
                                                             'default' => 0,
                                                             'required' => true ),
                                         'filename' =>  array( 'name' => 'Filename',
                                                               'datatype' => 'string',
                                                               'default' => '',
                                                               'required' => true ),
                                         'original_filename' =>  array( 'name' => 'OriginalFilename',
                                                                        'datatype' => 'string',
                                                                        'default' => '',
                                                                        'required' => true ),
                                         'mime_type' => array( 'name' => 'MimeType',
                                                               'datatype' => 'string',
                                                               'default' => '',
                                                               'required' => true ),
                                         'download_count' => array( 'name' => 'DownloadCount',
                                                                    'datatype' => 'integer',
                                                                    'default' => 0,
                                                                    'required' => true ) ),
                      'keys' => array( 'contentobject_attribute_id', 'version', 'filename' ),
                      'relations' => array( 'contentobject_attribute_id' => array( 'class' => 'ezcontentobjectattribute',
                                                                                   'field' => 'id' ) ),
                      'function_attributes' => array( 'filesize' => 'fileSize',
                                                      'filepath' => 'filePath',
                                                      'mime_type_category' => 'mimeTypeCategory',
                                                      'mime_type_part' => 'mimeTypePart' ),
                      'class_name' => 'eZBinaryFile2',
                      'name' => 'ezbinaryfile' );
        return $definition;
    }


    static function create( $contentObjectAttributeID, $version )
    {
        $row = array( 'contentobject_attribute_id' => $contentObjectAttributeID,
                      'version' => $version,
                      'filename' => '',
                      'original_filename' => '',
                      'mime_type' => ''
                      );
        return new eZBinaryFile2( $row );
    }

    /**
     * @param int $id
     * @param null $version
     * @param bool $asObject
     *
     * @return eZPersistentObject[]
     */
    static function fetch( $id, $version = null, $asObject = true )
    {
        if ( $version == null )
        {
            return eZPersistentObject::fetchObjectList( eZBinaryFile2::definition(),
                                                        null,
                                                        array( 'contentobject_attribute_id' => $id ),
                                                        null,
                                                        null,
                                                        $asObject );
        }
        else
        {
            return eZPersistentObject::fetchObjectList( eZBinaryFile2::definition(),
                                                    null,
                                                    array( 'contentobject_attribute_id' => $id,
                                                           'version' => $version ),
                                                    $asObject );
        }
    }

    static function fetchByFileName( $filename, $version = null, $asObject = true )
    {
        if ( $version == null )
        {
            return eZPersistentObject::fetchObjectList( eZBinaryFile2::definition(),
                                                        null,
                                                        array( 'filename' => $filename ),
                                                        null,
                                                        null,
                                                        $asObject );
        }
        else
        {
            return eZPersistentObject::fetchObject( eZBinaryFile2::definition(),
                                                    null,
                                                    array( 'filename' => $filename,
                                                           'version' => $version ),
                                                    $asObject );
        }
    }

    static function removeByID( $id, $version )
    {
        if ( $version == null )
        {
            eZPersistentObject::removeObject( eZBinaryFile2::definition(),
                                              array( 'contentobject_attribute_id' => $id ) );
        }
        else
        {
            eZPersistentObject::removeObject( eZBinaryFile2::definition(),
                                              array( 'contentobject_attribute_id' => $id,
                                                     'version' => $version ) );
        }
    }

    static function removeByFileName( $filename, $id, $version )
    {
        if ( $version == null )
        {
            eZPersistentObject::removeObject( eZBinaryFile2::definition(),
                                              array( 'filename' => $filename,
                                                     'contentobject_attribute_id' => $id ) );
        }
        else
        {
            eZPersistentObject::removeObject( eZBinaryFile2::definition(),
                                              array( 'filename' => $filename,
                                                     'contentobject_attribute_id' => $id,
                                                     'version' => $version ) );
        }
    }

}

?>