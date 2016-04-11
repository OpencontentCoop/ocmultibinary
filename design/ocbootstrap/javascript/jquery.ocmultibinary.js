;(function($) {

    $.fn.ocmultibinary = function(method) {

        var methods = {

            init : function(options) {
                this.ocmultibinary.settings = $.extend({}, this.ocmultibinary.defaults, options);
                return this.each(function() {
                    var $element = $(this),
                        element = this;

                    var $buttonContainer = $element.find('.upload-button-container');
                    var $spinner = $element.find('.upload-button-spinner');
                    var $fileList = $element.find('.upload-file-list');

                    $element.find('.upload').fileupload({
                        dropZone: $element,
                        formData: function (form) {
                            return form.serializeArray();
                        },
                        dataType: 'json',
                        submit: function (e, data) {
                            $buttonContainer.hide();
                            $spinner.show();
                        },
                        done: function (e, data) {
                            if (data.result.errors.length > 0) {
                                var errorContainer = $('<div class="alert alert-danger"></div>');
                                $.each(data.result.errors, function() {
                                    $('<p>' + this+ '</p>').appendTo(errorContainer)
                                });
                                $buttonContainer.html(errorContainer);
                            } else if (typeof data.result.content != 'undefined') {
                                $fileList.html(data.result.content);
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