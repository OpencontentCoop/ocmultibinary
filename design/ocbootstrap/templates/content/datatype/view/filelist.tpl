<table class="list" cellpadding="0" cellspacing="0">
    <tr>
        <th>
            File allegati:
            {if $attribute.has_content}
                <button class="btn btn-danger btn-xs pull-right" type="submit"
                        name="CustomActionButton[{$attribute.id}_delete_binary]" title="Rimuovi tutti i file">
                    <i class="fa fa-trash"></i> Elimina tutti i file
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
                            title="Rimuovi questo file">
                        <i class="fa fa-trash"></i>
                    </button>
                    {$file.original_filename|wash( xhtml )}&nbsp;({$file.filesize|si( byte )})
                </td>
            </tr>
        {/foreach}
    {else}
        <tr>
            <td>
                <p>Nessun file caricato.</p>
            </td>
        </tr>
    {/if}
</table>