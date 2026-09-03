{default attribute_base=ContentObjectAttribute}
<table class="table list table-condensed" cellpadding="0" cellspacing="0" style="table-layout: fixed;">
    <colgroup>
        <col style="width: 7%;">
        <col style="width: 32%;">
        <col style="width: 23%;">
        <col style="width: 17%;">
        <col style="width: 17%;">
        <col style="width: 4%;">
    </colgroup>
    <thead>
    <tr style="display:none;">
        <th colspan="2">
            {'Attached files:'|i18n( 'extension/ocmultibinary' )}
        </th>
        <th colspan="4"></th>
    </tr>
    <tr>
        <th colspan="2"></th>
        <th>{'Display name'|i18n( 'extension/ocmultibinary' )}</th>
        <th style="padding-left: 12px;">{'Display group'|i18n( 'extension/ocmultibinary' )}</th>
        <th style="padding-left: 12px;">{'Text'|i18n( 'extension/ocmultibinary' )}</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    {if $attribute.has_content}
        {foreach $attribute.content as $key => $file}
            <tr>
                <td style="vertical-align:middle">
                    <button class="ocmultibutton btn btn-danger btn-xs" type="submit"
                            name="CustomActionButton[{$attribute.id}_delete_multibinary][{$file.filename}]"
                            title="{'Remove this file'|i18n( 'extension/ocmultibinary' )}">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
                <td style="vertical-align:middle; overflow-wrap: anywhere; padding-left: 12px;">
                    {$file.original_filename|wash( xhtml )}&nbsp;<small class="d-block mt-1" style="font-family: 'Lora', serif; font-size: 0.75rem;">{$file.filesize|si( byte )} - {$file.upload_date|l10n( shortdatetime )}</small>
                </td>
                <td style="vertical-align:middle">
                    <input type="text" value="{$file.display_name|wash}" placeholder="{'Display name'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_name_{$attribute.id}[{$file.filename}]" class="form-control form-control-sm" data-filename="{$file.filename}" />
                </td>
                <td style="vertical-align:middle; padding-left: 12px;">
                    <input type="text" value="{$file.display_group|wash}" placeholder="{'Display group'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_group_{$attribute.id}[{$file.filename}]" class="form-control form-control-sm" data-filename="{$file.filename}" />
                </td>
                <td style="padding-left: 12px;">
                    <textarea placeholder="{'Text'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_text_{$attribute.id}[{$file.filename}]" class="form-control form-control-sm">{$file.display_text|wash}</textarea>
                </td>
                <td style="vertical-align:middle">
                    <input type="hidden" value="{$file.display_order|wash}" name="{$attribute_base}_sort_{$attribute.id}[{$file.filename}]" class="sort" data-filename="{$file.filename}" data-original-filename="{$file.original_filename|wash( xhtml )}" data-filesize="{$file.filesize}" />
                    <i class="fa fa-arrows pull-right" style="cursor: grab;"></i>
                </td>

            </tr>
        {/foreach}
        <tr class="upload-search-empty" style="display:none;">
            <td colspan="6">
                <p class="mb-0">{'No files match this search.'|i18n( 'extension/ocmultibinary' )}</p>
            </td>
        </tr>
    {else}
        <tr>
            <td>
                <p>{'No files uploaded'|i18n( 'extension/ocmultibinary' )}</p>
            </td>
        </tr>
    {/if}
    </tbody>
</table>
{if $attribute.has_content}
    <div class="clearfix mt-2">
        <button class="btn btn-danger btn-sm upload-delete-all-btn" type="submit"
                name="CustomActionButton[{$attribute.id}_delete_binary]" title="{'Delete all files'|i18n( 'extension/ocmultibinary' )}">
            <i class="fa fa-trash"></i> {'Delete all files'|i18n( 'extension/ocmultibinary' )}
        </button>
    </div>
{/if}
{/default}