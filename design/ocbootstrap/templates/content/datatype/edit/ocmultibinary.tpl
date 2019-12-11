{default attribute_base=ContentObjectAttribute}
    <div id="uploader_{$attribute_base}_data_multibinaryfilename_{$attribute.id}">

        <div class="clearfix upload-file-list" data-sorturl="{concat('ocmultibinary/sort/', $attribute.id, '/', $attribute.version, '/', $attribute.language_code  )|ezurl(no)}">
            {include uri="design:content/datatype/view/filelist.tpl" attribute=$attribute}
        </div>

        {def $file_count = 0}
        {if $attribute.has_content}
            {set $file_count = $attribute.content|count()}
        {/if}
        {if or($file_count|lt( $attribute.contentclass_attribute.data_int2 ), $attribute.contentclass_attribute.data_int2|eq(0) )}
            <div class="clearfix upload-button-container">
                <span class="btn btn-info fileinput-button">
                    <i class="fa fa-plus"></i>
                    <span>{'Add file'|i18n( 'ocmultibinary' )}</span>
                    <input class="upload" type="file" name="OcMultibinaryFiles[]"
                           data-url="{concat('ocmultibinary/upload/', $attribute.id, '/', $attribute.version, '/', $attribute.language_code  )|ezurl(no)}"/>
                </span>
            </div>
            <div class="clearfix upload-button-spinner" style="display: none">
                <i class="fa fa-cog fa-spin fa-3x"></i>
            </div>
        {/if}

    </div>
{/default}


{ezscript_require( array( 'ezjsc::jquery', 'ezjsc::jqueryio', 'ezjsc::jqueryUI', 'jquery.fileupload.js','jquery.ocmultibinary.js') )}
{ezcss_require( 'jquery.fileupload.css' )}


<script>
    $(document).ready(function () {ldelim}
        $('#uploader_{$attribute_base}_data_multibinaryfilename_{$attribute.id}').ocmultibinary();
        {rdelim});
</script>
