{default attribute_base=ContentObjectAttribute}
    <div id="uploader_{$attribute_base}_data_multibinaryfilename_{$attribute.id}">

        {def $file_count = 0}

        {if $attribute.has_content}
            <table class="list" cellpadding="0" cellspacing="0">
                <tr>
                    <th colspan="4">
                        {'Attached files:'|i18n( 'extension/ocmultibinary' )}
                        <button class="button" type="submit"
                                name="CustomActionButton[{$attribute.id}_delete_binary]" title="{'Delete all files'|i18n('extension/ocmultibinary')}">
                            {'Delete all files'|i18n('extension/ocmultibinary')}
                        </button>
                    </th>
                    <th class="tight">
                        {'Sort by'|i18n( 'classlists/list' )} <small>({'Ascending'|i18n( 'design/admin/node/view/full' )})</small>
                    </th>
                </tr>
                {foreach $attribute.content as $key => $file}
                    <tr>
                        <td>
                            <button class="ocmultibutton btn btn-link btn-xs" type="submit"
                                    name="CustomActionButton[{$attribute.id}_delete_multibinary][{$file.filename}]"
                                    title="{'Remove this file'|i18n('extension/ocmultibinary')}"><img src="{'trash.png'|ezimage(no)}"/></button>
                            {$file.original_filename|wash( xhtml )}&nbsp;({$file.filesize|si( byte )})
                        </td>
                        <td>
                            <input type="text" value="{$file.display_name|wash}" placeholder="{'Display name'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_name_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" class="form-control" data-filename="{$file.original_filename|wash( xhtml )}" />
                        </td>
                        <td>
                            <input type="text" value="{$file.display_group|wash}" placeholder="{'Display group'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_group_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" class=form-control data-filename="{$file.original_filename|wash( xhtml )}" />
                        </td>
                        <td>
                            <textarea placeholder="{'Text'|i18n( 'extension/ocmultibinary' )}" name="{$attribute_base}_display_text_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" class=form-control>{$file.display_text|wash}</textarea>
                        </td>
                        <td>
                            <input type="number" value="{$file.display_order|wash}" name="{$attribute_base}_sort_{$attribute.id}[{$file.original_filename|wash( xhtml )}]" />
                        </td>
                    </tr>
                {/foreach}
                <tr>
                    <td colspan="3"></td>
                    <td>
                        <button class="btn btn-default btn-xs pull-right" type="submit"
                                name="CustomActionButton[{$attribute.id}_sort_binary]" title="{"Sort"|i18n("design/standard/shop")}">
                            {"Sort"|i18n("design/standard/shop")}
                        </button>
                    </td>
                </tr>
            </table>
        {else}
            <p>{'No files uploaded'|i18n('extension/ocmultibinary')}</p>
        {/if}

        {if $attribute.has_content}
            {set $file_count = $attribute.content|count()}
        {/if}
        {if or($file_count|lt( $attribute.contentclass_attribute.data_int2 ), $attribute.contentclass_attribute.data_int2|eq(0) )}
            <div class="block">
                <label class="ocmultilabel"
                       for="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}">{'New file for upload'|i18n( 'design/standard/content/datatype' )}
                    :</label>
                <input type="hidden" name="MAX_FILE_SIZE" value="{$attribute.contentclass_attribute.data_int1}000000"/>
                <input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}"
                       class="box ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}"
                       name="{$attribute_base}_data_multibinaryfilename_{$attribute.id}" type="file"/>
                <input class="ocmultibutton btn btn-default btn-sm" type="submit"
                       name="CustomActionButton[{$attribute.id}_upload_multibinary]" value="{'Add file'|i18n('extension/ocmultibinary')}"
                       title="{'Add file'|i18n('extension/ocmultibinary')}"/>
            </div>
        {/if}


    </div>
{/default}