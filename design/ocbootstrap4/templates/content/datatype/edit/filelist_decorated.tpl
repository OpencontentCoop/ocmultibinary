<table class="table list table-condensed" cellpadding="0" cellspacing="0">
    <thead>
    <tr>
        <th colspan="2">
            {'Attached files:'|i18n( 'extension/ocmultibinary' )}
        </th>
        <th colspan="4">
            {if $attribute.has_content}
                <button class="btn btn-danger btn-sm pull-right" type="submit"
                        name="CustomActionButton[{$attribute.id}_delete_binary]" title="{'Delete all files'|i18n( 'extension/ocmultibinary' )}">
                    <i class="fa fa-trash"></i> {'Delete all files'|i18n( 'extension/ocmultibinary' )}
                </button>
            {/if}
        </th>
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
                <td style="vertical-align:middle">
                    {$file.original_filename|wash( xhtml )}&nbsp;<small class="d-block">{$file.filesize|si( byte )}</small>
                </td>
                <td style="vertical-align:middle">
                    <input type="text" value="{$file.display_name|wash}" placeholder="{'Display name'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_name_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" class="form-control" data-filename="{$file.original_filename|wash( xhtml )}" />
                </td>
                <td style="vertical-align:middle">
                    <input type="text" value="{$file.display_group|wash}" placeholder="{'Display group'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_group_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" class=form-control data-filename="{$file.original_filename|wash( xhtml )}" />
                </td>
                <td>
                    <textarea placeholder="{'Text'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_text_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" class=form-control>{$file.display_text|wash}</textarea>
                </td>
                <td style="vertical-align:middle">
                    <input type="hidden" value="{$file.display_order|wash}" name="{$attribute_base}_sort_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" class="sort" data-filename="{$file.original_filename|wash( xhtml )}" />
                    <i class="fa fa-arrows pull-right"></i>
                </td>

            </tr>
        {/foreach}
    {else}
        <tr>
            <td>
                <p>{'No files uploaded'|i18n( 'extension/ocmultibinary' )}</p>
            </td>
        </tr>
    {/if}
    </tbody>
</table>