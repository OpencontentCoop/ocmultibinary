;(function($) {
    $.fn.ocmultibinary = function(method) {
        var methods = {
            init : function(options) {
                this.ocmultibinary.settings = $.extend({}, this.ocmultibinary.defaults, options);
                return this.each(function() {
                    var $element = $(this), element = this;
                    var $buttonContainer = $element.find('.upload-button-container');
                    var $spinner = $element.find('.upload-button-spinner');
                    var $fileList = $element.find('.upload-file-list');
                    var csrfToken;
                    var tokenNode = document.getElementById('ezxform_token_js');
                    if ( tokenNode ){
                        csrfToken = tokenNode.getAttribute('title');
                    }
                    var _sort = function(el) {
                        $(el).sortable({
                            update: function( event, ui ) {
                                var files = [];
                                $(this).children().each(function(index) {
                                    $(this).find('.sort').val(index);
                                    files.push($(this).find('.sort').data('filename'));
                                });
                                $.ajax({
                                    url: $fileList.data('sorturl'),
                                    dataType: "json",
                                    headers: {'X-CSRF-TOKEN': csrfToken},
                                    type: "post",
                                    cache: false,
                                    data: {
                                        files: JSON.stringify(files)
                                    },
                                    success: function (response) {
                                        //console.log(response)
                                    }
                                });
                            }
                        });
                    };
                    _sort($fileList.find('.list tbody'));
                    $element.find('.upload').fileupload({
                        dropZone: $element,
                        formData: function (form) {
                            return form.serializeArray();
                        },
                        dataType: 'json',
                        submit: function (e, data) {
                            $buttonContainer.find('.alert-danger').remove();
                            $buttonContainer.hide();
                            $spinner.show();
                        },
                        done: function (e, data) {
                            if (data.result.errors.length > 0) {
                                var errorContainer = $('<div class="alert alert-danger alert-dismissible text-left" role="alert" style="margin: 2px 0 !important;"></div>');
                                $('<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>').appendTo(errorContainer)
                                $.each(data.result.errors, function() {
                                    $('<p style="margin-bottom: 0 !important;">' + this+ '</p>').appendTo(errorContainer)
                                });
                                $buttonContainer.append(errorContainer);
                            } else if (typeof data.result.content != 'undefined') {
                                $fileList.html(data.result.content);
                                _sort($fileList.find('.list tbody'));
                            }
                            $buttonContainer.show();
                            $spinner.hide();
                        }
                    });
                });
            }
        };

        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error( 'Method "' +  method + '" does not exist in ocmultibinary plugin!');
        }
    };
    $.fn.ocmultibinary.defaults = {};
    $.fn.ocmultibinary.settings = {}
})(jQuery);