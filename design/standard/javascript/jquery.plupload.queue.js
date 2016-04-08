/**
 * jquery.plupload.queue.js
 *
 * Copyright 2009, Moxiecode Systems AB
 * Released under GPL License.
 *
 * License: http://www.plupload.com/license
 * Contributing: http://www.plupload.com/contributing
 */

// JSLint defined globals
/*global plupload:false, jQuery:false */

(function($) {
    var uploaders = {};

    function _(str) {
        return plupload.translate(str) || str;
    }

    function renderUI(id, target , html) {
        // Remove all existing non plupload items
        target.contents().each(function(i, node) {
            node = $(node);
            
            if (!node.is('.plupload')) {
                node.remove();
            }
        });
        target.prepend(html);

        
    }

    $.fn.pluploadQueue = function(settings, files, html, suffix) {
        if (settings) {
            this.each(function() {
                var uploader, target, id;

                target = $(this);
                id = target.attr('id');

                if (!id) {
                    id = plupload.guid();
                    target.attr('id', id);
                }

                uploader = new plupload.Uploader($.extend({
                    dragdrop : true,
                    container : id
                }, settings));
                
                // Call preinit function
                if (settings.preinit) {
                    settings.preinit(uploader);
                }
                
                $.each( files, function( i, file ) {
                    uploader.addFile( file );
                });

                uploaders[id] = uploader;
                
                function handleStatus(file) {
                    var actionClass;

                    if (file.status == plupload.DONE) {
                        actionClass = 'plupload_done';
                    }

                    if (file.status == plupload.FAILED) {
                        actionClass = 'plupload_failed';
                    }

                    if (file.status == plupload.QUEUED) {
                        actionClass = 'plupload_delete';
                    }

                    if (file.status == plupload.UPLOADING) {
                        actionClass = 'plupload_uploading';
                    }

                    $('#' + file.id).attr('class', actionClass).find('a').css('display', 'block');
                }

                // after clicking the 'Start upload' button
                function updateTotalProgress() {
                    // calculate total percent of the upload
                    var total_percent = 0;
                    var needed_percent = 0;
                    //alert(uploader.total.percent);
                    uploader.total.percent = 100;
                    if (uploader.files.length > 0)
                    {
                        $.each( uploader.files, function( i, file ) {
                            $( 'div.plupload_file_percent',  $( '#' + file['id'] ) ).html(file['percent'] + '%');
                            total_percent = parseInt( total_percent ) + parseInt( file['percent'] );
                            needed_percent = parseInt( needed_percent ) + 100;
                        });
                    }
                    if ( total_percent != needed_percent )
                    {
                        uploader.total.percent = Math.round( parseInt( total_percent ) * 100 / parseInt( needed_percent ) );
                    }
                    
                    $('span.plupload_total_status', target).html(uploader.total.percent + '%');
                    $('div.plupload_progress_bar', target).css('width', uploader.total.percent + '%');
                    //$('span.plupload_upload_status', target).text('Uploaded ' + uploader.total.uploaded + '/' + uploader.files.length + ' files');

                    // All files are uploaded
                    if (uploader.total.uploaded == uploader.files.length) {
                        uploader.stop();

                    }
                }

                // after loading the attribute xrowmultibinary and the content (if there are files or not)
                function updateList() {
                    var fileList = $('ul.plupload_filelist', target).html(''), 
                        inputCount = 0,
                        inputHTML;
                    
                    $.each( uploader.files, function( i, file ) {
                        inputHTML = '';
                        if (file.status == plupload.DONE) {
                            if (file.target_name) {
                                inputHTML += '<input type="hidden" name="plup_tmp_name[]' + id + '_' + inputCount + '_tmpname" value="' + plupload.xmlEncode(file.target_name) + '" />';
                            }
                            inputHTML += '<input type="hidden" name="plup_id[]" value="' + id + '" />';
                            inputHTML += '<input type="hidden" name="plup_tmp_name' + suffix + '[]" value="' + plupload.xmlEncode(file.name) + '" />';
                            inputHTML += '<input type="hidden" name="plup_status[]" value="' + (file.status == plupload.DONE ? 'done' : 'failed') + '" />';
    
                            inputCount++;
                        }

                        // If the file id includes a point ()
                        var ID = file.id;
                        if ( ID.indexOf( '.' ) )
                        {
                            var file_id = ID.split( '.' );
                            file.id = file_id[0];
                        }

                        fileList.append(
                            '<li id="' + file.id + '" class="sortable">' +
                                '<div class="plupload_file_name"><span>' + file.name + '</span></div>' +
                                '<div id="'+ file.id +'_delete" class="plupload_file_action2"></div>' +
                                '<div class="plupload_file_action"><a href="#"></a></div>' +
                                '<div class="plupload_file_size">' + plupload.formatSize(file.size) + '</div>' +
                                '<div class="plupload_file_percent">' + file.percent + '%</div>' +
                                '<div class="plupload_clearer">&nbsp;</div>' +
                                inputHTML +
                            '</li>'
                        );

                        $('#'+ file.id + '_delete').click(function(e)
                        {
                            $('#' + file.id).remove();
                            uploader.removeFile(file);
                            if ( uploader.files.length < uploader.settings.max_number_of_files )
                            {
                                $('div.plupload_buttons', target).show();
                            }

                            e.preventDefault();
                        });
                        //handleStatus(file);
                    });

                    $('span.plupload_total_file_size', target).html(plupload.formatSize(uploader.total.size));

                    if ( uploader.total.queued === 0 ) {
                        if ( settings.max_number_of_files )
                        {
                            var file_text = 'Add files.';
                            if ( settings.max_number_of_files == 1 )
                            {
                                var file_text = 'Add file.';
                            }
                        }
                        $('span.plupload_add_text', target).text(_( file_text ));
                    } else {
                        $('span.plupload_add_text', target).text(uploader.total.queued + ' files queued.');
                    }
                    
                    // disable the button Add file/s if the maximum is reached
                    var check_max_number_of_files = 'disabled', max_number_of_files = 0;
                    if ( settings.max_number_of_files )
                    {
                        check_max_number_of_files = 'enabled';
                        max_number_of_files = settings.max_number_of_files;
                    }            
                    

                    $( 'a.plupload_start', target ).toggleClass( 'plupload_disabled', uploader.files.length === 0 );

                    // Scroll to end of file list
                    fileList[0].scrollTop = fileList[0].scrollHeight;

                    updateTotalProgress();

                    // Re-add drag message if there is no files
                    if (!uploader.files.length && uploader.features.dragdrop && uploader.settings.dragdrop) {
                        $( '#' + id + '_filelist' ).append( '<li class="plupload_droptext">' + _( 'drag_file_here' ) + '</li>' );
                    }
                    $( 'ul.plupload_filelist' ).sortable();
                }

                uploader.bind( 'UploadFile', function( up, file ) {
                    $( '#' + file.id ).addClass( 'plupload_current_file' );
                });

                uploader.bind( 'Init', function( up, res ) {
                    renderUI( id, target, html );

                    updateList();
                    // Enable rename support
                    if ( !settings.unique_names && settings.rename ) {
                        $( '#' + id + '_filelist div.plupload_file_name span', target ).live( 'click', function( e ) {
                            var targetSpan = $( e.target ), file, parts, name, ext = '';

                            // Get file name and split out name and extension
                            file = up.getFile(targetSpan.parents('li')[0].id);
                            name = file.name;
                            parts = /^(.+)(\.[^.]+)$/.exec(name);
                            if (parts) {
                                name = parts[1];
                                ext = parts[2];
                            }

                            // Display input element
                            targetSpan.hide().after( '<input type="text" />' );
                            targetSpan.next().val( name ).focus().blur( function() {
                                targetSpan.show().next().remove();
                            }).keydown( function( e ) {
                                var targetInput = $( this );

                                if ( e.keyCode == 13 ) {
                                    e.preventDefault();

                                    // Rename file and glue extension back on
                                    file.name = targetInput.val() + ext;
                                    targetSpan.text(file.name);
                                    targetInput.blur();
                                }
                            });
                        });
                    }

                    $( 'a.plupload_add', target ).attr( 'id', id + '_browse' );

                    up.settings.browse_button = id + '_browse';

                    // Enable drag/drop
                    if ( up.features.dragdrop && up.settings.dragdrop ) {
                        up.settings.drop_element = id + '_filelist';
                        $( '#' + id + '_filelist' ).append( '<li class="plupload_droptext">' + _( 'drag_file_here' ) + '</li>' );
                    }

                    $( '#' + id + '_container' ).attr( 'title', 'Using runtime: ' + res.runtime );

                    $( 'a.plupload_start', target ).click( function( e ) {
                        if ( !$( this ).hasClass( 'plupload_disabled' ) ) {
                            uploader.start();
                        }

                        e.preventDefault();
                    });

                    $( 'a.plupload_stop', target ).click( function( e ) {
                        uploader.stop();
                        e.preventDefault();
                    });

                    $( 'a.plupload_start', target ).addClass( 'plupload_disabled' );

                    if ( $( 'input[name=PublishButton]' ) && settings.upload_on_publish )
                    {
                        $( 'a.plupload_start', target ).hide();
                        $( 'input[name=PublishButton]' ).bind('click', function(event) 
                        {
                            event.preventDefault();
                            $( 'body' ).css( 'cursor', 'wait' );
                            uploader.start();
                        });
                    }
                });

                uploader.init();

                // Call setup function
                if ( settings.setup ) {
                    settings.setup( uploader );
                }

                uploader.bind( 'Error', function( up, err ) {
                    var file = err.file, message;

                    if ( file ) {
                        message = err.message;

                        if ( err.details ) {
                            message += " (" + err.details + ")";
                        }

                        $( '#' + file.id ).attr( 'class', 'plupload_failed' ).find( 'a' ).css( 'display', 'block' ).attr( 'title', message );
                    }
                });

                uploader.bind( 'StateChanged', function() {
                    if ( uploader.state === plupload.STARTED ) {
                        $('li.plupload_delete a,div.plupload_buttons', target).hide();
                        $('span.plupload_upload_status,div.plupload_progress,a.plupload_stop', target).css('display', 'block');
                        //$('span.plupload_upload_status', target).text('Caricati 0/' + uploader.files.length + ' documenti');
                        $( 'input[type=submit]' ).attr('disabled', true);
                    } else {
                        $( 'a.plupload_stop,div.plupload_progress', target ).hide();
                        $( 'a.plupload_delete', target ).css( 'display', 'block' );
                    }
                });

                uploader.bind( 'QueueChanged', updateList );

                uploader.bind( 'StateChanged', function( up ) {
                    if ( up.state == plupload.STOPPED ) {
                        updateList();
                        $( 'a.plupload_start', target ).addClass( 'plupload_disabled' );
                        $( 'input[type=submit]' ).removeAttr('disabled');
                        
                        $('li.plupload_delete a,div.plupload_buttons', target).show();
                        if ( $( 'input[name=PublishButton]' ) && settings.upload_on_publish )
                        {
                            $( '#editform' ).prepend( '<input type="hidden" name="PublishButton" value="PublishButton"/>' );
                            $( '#editform' ).submit();
                        }
                    }
                });

                uploader.bind( 'FileUploaded', function( up, file ) {
                    handleStatus( file );
                    if ( up.files.length >= up.settings.max_number_of_files )
                    {
                        $('div.plupload_buttons', target).hide();
                    }
                });
            
                uploader.bind( 'UploadProgress', function( up, file ) {
                    // Set file specific progress
                    $( '#' + file.id + ' div.plupload_file_status', target ).html( file.percent + '%' );

                    handleStatus( file );
                    updateTotalProgress();
                });
            });

            return this;
        } else {
            // Get uploader instance for specified element
            return uploaders[$( this[0] ).attr( 'id' )];
        }
    };
})( jQuery );
