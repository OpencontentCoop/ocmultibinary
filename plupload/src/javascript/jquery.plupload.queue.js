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

	function renderUI(id, target , html ) {
		// Remove all existing non plupload items
		target.contents().each(function(i, node) {
			node = $(node);

			if (!node.is('.plupload')) {
				node.remove();
			}
		});
		target.prepend( html );
		
	}

	$.fn.pluploadQueue = function(settings,files, html) {
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
				$.each(files, function(i, file) { uploader.addFile(file)});

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

				function updateTotalProgress() {
					$('span.plupload_total_status', target).html(uploader.total.percent + '%');
					$('div.plupload_progress_bar', target).css('width', uploader.total.percent + '%');
					$('span.plupload_upload_status', target).text('Uploaded ' + uploader.total.uploaded + '/' + uploader.files.length + ' files');

					// All files are uploaded
					if (uploader.total.uploaded == uploader.files.length) {
						uploader.stop();
					}
				}

				function updateList() {
			
					var fileList = $('ul.plupload_filelist', target).html(''), inputCount = 0, inputHTML;

					$.each(uploader.files, function(i, file) {
						inputHTML = '';

						if (file.status == plupload.DONE) {
							if (file.target_name) {
								inputHTML += '<input type="hidden" name="plup_tmp_name[]' + id + '_' + inputCount + '_tmpname" value="' + plupload.xmlEncode(file.target_name) + '" />';
							}
							inputHTML += '<input type="hidden" name="plup_id[]" value="' + id + '" />';
							inputHTML += '<input type="hidden" name="plup_tmp_name[]" value="' + plupload.xmlEncode(file.name) + '" />';
							inputHTML += '<input type="hidden" name="plup_status[]" value="' + (file.status == plupload.DONE ? 'done' : 'failed') + '" />';
	
							inputCount++;

							//$('#' + id + '_count').val(inputCount);
						}

						fileList.append(
							'<li id="' + file.id + '" class="sortable">' +
								'<div class="plupload_file_name"><span>' + file.name + '</span></div>' +
								'<div id="'+ file.id +'_delete" class="plupload_file_action2">&nbsp;ssssssssssss</div>' +
								'<div class="plupload_file_action"><a href="#"></a></div>' +
								'<div class="plupload_file_status">' + file.percent + '%</div>' +
								'<div class="plupload_file_size">' + plupload.formatSize(file.size) + '</div>' +
								'<div class="plupload_clearer">&nbsp;</div>' +
								inputHTML +
							'</li>'
						);
						 $('#' + file.id + '.plupload_delete a').click(function(e) {
							 $('#' + file.id).remove();
							 uploader.removeFile(file);

							 e.preventDefault();
							 });
						// div.plupload_file_action2 a/
						$('#'+ file.id + '_delete').attr('title', 'Using ssssssssssruntime: ');
						$('#'+ file.id + '_delete').click(function(e) {
						
											$('#' + file.id).remove();
											uploader.removeFile(file);

											e.preventDefault();
										});
						alert("2");
						//handleStatus(file);
	
					});


		
					
	

					$('span.plupload_total_file_size', target).html(plupload.formatSize(uploader.total.size));

					if (uploader.total.queued === 0) {
						$('span.plupload_add_text', target).text(_('Add files.'));
					} else {
						$('span.plupload_add_text', target).text(uploader.total.queued + ' files queued.');
					}

					$('a.plupload_start', target).toggleClass('plupload_disabled', uploader.files.length === 0);

					// Scroll to end of file list
					fileList[0].scrollTop = fileList[0].scrollHeight;

					updateTotalProgress();

					// Re-add drag message if there is no files
					if (!uploader.files.length && uploader.features.dragdrop && uploader.settings.dragdrop) {
						$('#' + id + '_filelist').append('<li class="plupload_droptext">' + _("Drag files here.") + '</li>');
					}
					//$( 'ul.plupload_filelist' ).sortable();
				}

				uploader.bind("UploadFile", function(up, file) {
					$('#' + file.id).addClass('plupload_current_file');
				});

				uploader.bind('Init', function(up, res) {
					renderUI( id, target, html);

					updateList();
					// Enable rename support
					if (!settings.unique_names && settings.rename) {
						$('#' + id + '_filelist div.plupload_file_name span', target).live('click', function(e) {
							var targetSpan = $(e.target), file, parts, name, ext = "";

							// Get file name and split out name and extension
							file = up.getFile(targetSpan.parents('li')[0].id);
							name = file.name;
							parts = /^(.+)(\.[^.]+)$/.exec(name);
							if (parts) {
								name = parts[1];
								ext = parts[2];
							}

							// Display input element
							targetSpan.hide().after('<input type="text" />');
							targetSpan.next().val(name).focus().blur(function() {
								targetSpan.show().next().remove();
							}).keydown(function(e) {
								var targetInput = $(this);

								if (e.keyCode == 13) {
									e.preventDefault();

									// Rename file and glue extension back on
									file.name = targetInput.val() + ext;
									targetSpan.text(file.name);
									targetInput.blur();
								}
							});
						});
					}

					$('a.plupload_add', target).attr('id', id + '_browse');

					up.settings.browse_button = id + '_browse';

					// Enable drag/drop
					if (up.features.dragdrop && up.settings.dragdrop) {
						up.settings.drop_element = id + '_filelist';
						$('#' + id + '_filelist').append('<li class="plupload_droptext">' + _("Drag files here.") + '</li>');
					}

					$('#' + id + '_container').attr('title', 'Using runtime: ' + res.runtime);

					$('a.plupload_start', target).click(function(e) {
						if (!$(this).hasClass('plupload_disabled')) {
							uploader.start();
						}

						e.preventDefault();
					});

					$('a.plupload_stop', target).click(function(e) {
						uploader.stop();

						e.preventDefault();
					});

					$('a.plupload_start', target).addClass('plupload_disabled');
				});

				uploader.init();

				// Call setup function
				if (settings.setup) {
					settings.setup(uploader);
				}

				uploader.bind("Error", function(up, err) {
					var file = err.file, message;

					if (file) {
						message = err.message;

						if (err.details) {
							message += " (" + err.details + ")";
						}

						$('#' + file.id).attr('class', 'plupload_failed').find('a').css('display', 'block').attr('title', message);
					}
				});

				uploader.bind('StateChanged', function() {
					if (uploader.state === plupload.STARTED) {
						//$('li.plupload_delete a,div.plupload_buttons', target).hide();
						//$('span.plupload_upload_status,div.plupload_progress,a.plupload_stop', target).css('display', 'block');
						//$('span.plupload_upload_status', target).text('Uploaded 0/' + uploader.files.length + ' files');
					} else {
						$('a.plupload_stop,div.plupload_progress', target).hide();
						$('a.plupload_delete', target).css('display', 'block');
					}
				});

				uploader.bind('QueueChanged', updateList);

				uploader.bind('StateChanged', function(up) {
					if (up.state == plupload.STOPPED) {
						updateList();
					}
				});

				uploader.bind('FileUploaded', function(up, file) {
					handleStatus(file);
				});

				uploader.bind("UploadProgress", function(up, file) {
					// Set file specific progress
					$('#' + file.id + ' div.plupload_file_status', target).html(file.percent + '%');

					handleStatus(file);
					updateTotalProgress();
				});
			});

			return this;
		} else {
			// Get uploader instance for specified element
			return uploaders[$(this[0]).attr('id')];
		}
	};
})(jQuery);
