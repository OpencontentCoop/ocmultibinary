{default attribute_base=ContentObjectAttribute}
    {def $upload_conflict_check_enabled = ezini('NameConflictSettings', 'EnableUploadConflictCheck', 'ocmultibinary.ini')|eq('enabled')}
    <div id="uploader_{$attribute_base}_data_multibinaryfilename_{$attribute.id}">

        {if $upload_conflict_check_enabled}
        <div id="upload-conflict-box-{$attribute.id}" class="upload-conflict-box alert alert-warning mb-3" style="display:none;" role="alert" tabindex="-1">
            <p class="mb-2">
                <strong>{'The following files have the same name as an attachment already present. Choose whether to replace it or keep both:'|i18n( 'extension/ocmultibinary' )}</strong>
            </p>
            <div class="upload-conflict-box-list" style="max-height: 220px; overflow-y: auto;"></div>
            <div class="upload-conflict-box-actions mt-2 text-right">
                <button type="button" class="btn btn-sm btn-outline-secondary upload-conflict-box-cancel">
                    {'Cancel'|i18n( 'extension/ocmultibinary' )}
                </button>
                <button type="button" class="btn btn-sm btn-warning upload-conflict-box-confirm">
                    {'Confirm'|i18n( 'extension/ocmultibinary' )}
                </button>
            </div>
        </div>
        <template class="upload-conflict-row-template">
            <div class="upload-conflict-row d-flex align-items-center justify-content-between py-1 border-bottom">
                <span class="upload-conflict-row-name"></span>
                <span class="upload-conflict-row-choice text-nowrap">
                    <div class="form-check form-check-inline mr-3 mb-0">
                        <input class="radio-input upload-conflict-row-replace" type="radio" value="replace" checked>
                        <label>{'Replace'|i18n( 'extension/ocmultibinary' )}</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="radio-input upload-conflict-row-keep" type="radio" value="keep">
                        <label>{'Keep both'|i18n( 'extension/ocmultibinary' )}</label>
                    </div>
                </span>
            </div>
        </template>
        {/if}

        <div class="clearfix upload-file-list" data-sorturl="{concat('ocmultibinary/sort/', $attribute.id, '/', $attribute.version, '/', $attribute.language_code  )|ezurl(no)}">
            {include uri="design:content/datatype/edit/filelist_decorated.tpl" attribute=$attribute}
        </div>

        {def $file_count = 0}
        {if $attribute.has_content}
            {set $file_count = $attribute.content|count()}
        {/if}
        {if or($file_count|lt( $attribute.contentclass_attribute.data_int2 ), $attribute.contentclass_attribute.data_int2|eq(0) )}
            <div class="clearfix upload-button-container">
                <span class="btn btn-success btn-sm fileinput-button">
                    <i class="fa fa-plus"></i>
                    <span>{'Add file'|i18n( 'extension/ocmultibinary' )}</span>
                    <input class="input-upload" multiple type="file" name="OcMultibinaryFiles[]"
                           data-url="{concat('ocmultibinary/upload/', $attribute.id, '/', $attribute.version, '/', $attribute.language_code  )|ezurl(no)}" />


                </span>
            </div>
            <div class="clearfix upload-button-spinner" style="display: none">
                <a class="btn btn-success btn-sm" href="#"><i class="fa a fa-circle-o-notch fa-spin"></i> {'Add file'|i18n( 'extension/ocmultibinary' )}</a>
            </div>
        {/if}


    </div>

{ezscript_require( array( 'ezjsc::jquery', 'ezjsc::jqueryio', 'ezjsc::jqueryUI', 'jquery.fileupload.js','jquery.ocmultibinary.js') )}
{ezcss_require( 'jquery.fileupload.css' )}


<script>
    $(document).ready(function(){ldelim}
        $('#uploader_{$attribute_base}_data_multibinaryfilename_{$attribute.id}').ocmultibinary();
    {rdelim});
</script>
{/default}