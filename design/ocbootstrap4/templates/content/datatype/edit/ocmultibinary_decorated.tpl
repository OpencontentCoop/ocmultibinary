{default attribute_base=ContentObjectAttribute}
    {def $upload_conflict_check_enabled = ezini('NameConflictSettings', 'EnableUploadConflictCheck', 'ocmultibinary.ini')|eq('enabled')}
    <div id="uploader_{$attribute_base}_data_multibinaryfilename_{$attribute.id}">

        {def $file_count = 0}
        {if $attribute.has_content}
            {set $file_count = $attribute.content|count()}
        {/if}
        {if or($file_count|lt( $attribute.contentclass_attribute.data_int2 ), $attribute.contentclass_attribute.data_int2|eq(0) )}
            <div class="clearfix upload-button-container mb-4">
                <span class="btn btn-success btn-sm fileinput-button">
                    <i class="fa fa-plus"></i>
                    <span>{'Add file'|i18n( 'extension/ocmultibinary' )}</span>
                    <input class="input-upload" multiple type="file" name="OcMultibinaryFiles[]"
                           data-url="{concat('ocmultibinary/upload/', $attribute.id, '/', $attribute.version, '/', $attribute.language_code  )|ezurl(no)}" />


                </span>
            </div>
            <div class="clearfix upload-button-spinner mb-4" style="display: none">
                <a class="btn btn-success btn-sm" href="#"><i class="fa a fa-circle-o-notch fa-spin"></i> {'Add file'|i18n( 'extension/ocmultibinary' )}</a>
            </div>
        {/if}

        {if $upload_conflict_check_enabled}
        <div id="upload-conflict-anchor-{$attribute.id}" class="upload-conflict-anchor" tabindex="-1">
            <div class="upload-conflict-box alert alert-warning mb-4" style="display:none;" role="alert">
                <p class="mb-2">
                    <strong>{'The following files need your attention before uploading:'|i18n( 'extension/ocmultibinary' )}</strong>
                </p>
                <div class="upload-conflict-box-list"></div>
                <div class="upload-conflict-box-actions mt-2 text-right">
                    <button type="button" class="btn btn-sm btn-outline-secondary upload-conflict-box-cancel">
                        {'Cancel'|i18n( 'extension/ocmultibinary' )}
                    </button>
                    <button type="button" class="btn btn-sm btn-success upload-conflict-box-confirm">
                        {'Confirm'|i18n( 'extension/ocmultibinary' )}
                    </button>
                </div>
            </div>
        </div>

        {* Reason "name": same filename as an existing attachment (possibly different content) -
           a real replace is technically possible, since the server matches by name. *}
        <template class="upload-conflict-row-template" data-reason="name">
            <div class="upload-conflict-row d-flex align-items-center justify-content-between py-1 border-bottom">
                <span class="upload-conflict-row-message">
                    <span class="upload-conflict-row-name d-block" style="font-size: 1.1em;"></span>
                    <em class="d-block">{'Same name as an existing attachment, but different size.'|i18n( 'extension/ocmultibinary' )}</em>
                </span>
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

        {* Reason "size": same size as an existing attachment with a DIFFERENT name - the server
           only matches by name, so a real replace is not possible here: this row can only let the
           editor skip uploading this specific file, never merge it into the existing one. *}
        <template class="upload-conflict-row-template" data-reason="size">
            <div class="upload-conflict-row d-flex align-items-center justify-content-between py-1 border-bottom">
                <span class="upload-conflict-row-message">
                    <span class="upload-conflict-row-name d-block" style="font-size: 1.1em;"></span>
                    <em class="d-block">{'Same size as an existing attachment, but different name, it could be the same file.'|i18n( 'extension/ocmultibinary' )}</em>
                </span>
                <span class="upload-conflict-row-choice text-nowrap">
                    <div class="form-check form-check-inline mr-3 mb-0">
                        <input class="radio-input upload-conflict-row-replace" type="radio" value="replace" checked>
                        <label>{'Upload anyway'|i18n( 'extension/ocmultibinary' )}</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="radio-input upload-conflict-row-keep" type="radio" value="keep">
                        <label>{"Don't upload"|i18n( 'extension/ocmultibinary' )}</label>
                    </div>
                </span>
            </div>
        </template>

        {* Reason "both": same name AND same size - almost certainly the identical file, but
           same size is only a heuristic, not a guarantee of identical content. Uses the same
           "Replace"/"Keep both" labels as reason "name": the server-side effect is identical
           in both cases (the existing binary is deleted server-side), so the label must be
           equally honest about it here. *}
        <template class="upload-conflict-row-template" data-reason="both">
            <div class="upload-conflict-row d-flex align-items-center justify-content-between py-1 border-bottom">
                <span class="upload-conflict-row-message">
                    <span class="upload-conflict-row-name d-block" style="font-size: 1.1em;"></span>
                    <em class="d-block">{'Same name and size as an existing attachment, it could be the same file.'|i18n( 'extension/ocmultibinary' )}</em>
                </span>
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

        <div class="clearfix mb-2">
            <label for="upload-file-search-{$attribute.id}" class="font-weight-bold">{'Search'|i18n( 'extension/ocmultibinary' )}</label>
            <input type="search" id="upload-file-search-{$attribute.id}" class="form-control form-control-sm upload-file-search"
                   placeholder="{'Search by file name or display name'|i18n( 'extension/ocmultibinary' )}" />
        </div>

        <div class="clearfix upload-file-list" data-sorturl="{concat('ocmultibinary/sort/', $attribute.id, '/', $attribute.version, '/', $attribute.language_code  )|ezurl(no)}">
            {include uri="design:content/datatype/edit/filelist_decorated.tpl" attribute=$attribute}
        </div>

    </div>

{ezscript_require( array( 'ezjsc::jquery', 'ezjsc::jqueryio', 'ezjsc::jqueryUI', 'jquery.fileupload.js','jquery.ocmultibinary.js') )}
{ezcss_require( 'jquery.fileupload.css' )}


<script>
    $(document).ready(function(){ldelim}
        $('#uploader_{$attribute_base}_data_multibinaryfilename_{$attribute.id}').ocmultibinary();
    {rdelim});
</script>
{/default}