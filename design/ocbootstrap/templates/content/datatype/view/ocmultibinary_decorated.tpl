{* DO NOT EDIT THIS FILE! Use an override template instead. *}

{if $attribute.has_content}
  {def $groups = ocmultibinary_available_groups($attribute)}
  {foreach $groups as $group}
    {if $group|ne('')}<h6>{$group|wash()}</h6>{/if}
    <ul class="list-unstyled">
      {foreach ocmultibinary_list_by_group($attribute, $group) as $file}
        <li>
          <a href={concat( 'ocmultibinary/download/', $attribute.contentobject_id, '/', $attribute.id,'/', $attribute.version , '/', $file.filename ,'/file/', $file.original_filename|urlencode )|ezurl}>
            <span title="{$file.original_filename|wash( xhtml )}"><i class="fa fa-download"></i> Scarica il file</span>
            <small>{$file.display_name|wash( xhtml )} (File {$file.mime_type} {$file.filesize|si( byte )})</small>
          </a>
          {if $file.display_text|ne('')}
            <br /><small>{$file.display_text|wash( xhtml )}</small>
          {/if}
        </li>
      {/foreach}
    </ul>
  {/foreach}
{/if}