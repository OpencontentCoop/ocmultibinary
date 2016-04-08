<?php
//
// Created on: <28-Mar-2008 00:00:00 ar>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ Online Editor extension for eZ Publish
// SOFTWARE RELEASE: 1.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2010 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/*
 * Generates all i18n strings for the TinyMCE editor
 * and transforms them to the TinyMCE format for
 * translations.
 */

require_once( 'kernel/common/i18n.php' );

class xrowMultiBinaryServerFunctions extends ezjscServerFunctions
{
    /**
     * i18n
     * Provides all i18n strings for use by TinyMCE and other javascript dialogs.
     * 
     * @param array $args
     * @param string $fileExtension
     * @return string returns json string with translation data
    */
    public static function i18n( $args, $fileExtension )
    {
        $lang = 'en';
        $locale = eZLocale::instance();
        if ( $args && $args[0] )
            $lang = $args[0];

        $i18nArray =  array( $lang => array(
            'select_files' => ezpI18n::tr('extension/xrowmultibinary', 'Select files'),
            'filename' => ezpI18n::tr('extension/xrowmultibinary', 'Filename'),
            'status' => ezpI18n::tr('extension/xrowmultibinary', 'Status'),
            'size' => ezpI18n::tr('extension/xrowmultibinary', 'Size'),
            'add_file' => ezpI18n::tr('extension/xrowmultibinary', 'Add file'),
            'add_files' => ezpI18n::tr('extension/xrowmultibinary', 'Add files'),
            'start_upload' => ezpI18n::tr('extension/xrowmultibinary', 'Start upload'),
            'drag_file_here' => ezpI18n::tr('extension/xrowmultibinary', 'Drag files here.'),
            'file_exists' => ezpI18n::tr('extension/xrowmultibinary', 'A file with this name is already exists.'),
            'allowed_to_upload_one_file' => ezpI18n::tr('extension/xrowmultibinary', 'You are allowed to upload only one file.'),
            'allowed_to_upload_x_files' => ezpI18n::tr('extension/xrowmultibinary', 'You are allowed to upload only %s files.')
        ));
        $i18nString = json_encode( $i18nArray );

        return 'plupload.addI18n( ' . $i18nString . ' );';
    }
}

?>