<table class="list" cellpadding="0" cellspacing="0">
    <tr>
        <th>
            {'Attached files:'|i18n( 'ocmultibinary' )}
            {if $attribute.has_content}
                <button class="btn btn-danger btn-xs pull-right" type="submit"
                        name="CustomActionButton[{$attribute.id}_delete_binary]" title="{'Delete all files'|i18n( 'ocmultibinary' )}">
                    <i class="fa fa-trash"></i> {'Delete all files'|i18n( 'ocmultibinary' )}
                </button>
            {/if}
        </th>
    </tr>
    {if $attribute.has_content}
        {foreach $attribute.content as $file}
            <tr>
                <td>
                    <button class="ocmultibutton btn btn-danger btn-xs" type="submit"
                            name="CustomActionButton[{$attribute.id}_delete_multibinary][{$file.filename}]"
                            title="{'Remove this file'|i18n( 'ocmultibinary' )}">
                        <i class="fa fa-trash"></i>
                    </button>
                    {$file.original_filename|wash( xhtml )}&nbsp;({$file.filesize|si( byte )})
                </td>
            </tr>
        {/foreach}
    {else}
        <tr>
            <td>
                <p>{'No files uploaded'|i18n( 'ocmultibinary' )}</p>
            </td>
        </tr>
    {/if}
</table>