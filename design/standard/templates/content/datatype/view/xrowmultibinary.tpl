{* DO NOT EDIT THIS FILE! Use an override template instead. *}

{if $attribute.has_content}
<ul>
{foreach $attribute.content as $file}
  <li><a href={concat( 'xrowmultibinary/download/', $attribute.contentobject_id, '/', $attribute.id,'/', $attribute.version , '/', $file.filename ,'/file/', $file.original_filename|urlencode )|ezurl}>{$file.original_filename|wash( xhtml )}</a>&nbsp;({$file.filesize|si( byte )})</li>
{/foreach}
</ul>
{/if}