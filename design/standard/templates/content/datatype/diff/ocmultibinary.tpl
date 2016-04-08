{* DO NOT EDIT THIS FILE! Use an override template instead. *}
<div class="block">
    {foreach $diff.changes as $change}
        {if eq( $change.status, 0 )}
            {$change.unchanged|wash}
        {elseif eq( $change.status, 1 )}
            <del>{$change.removed|wash}</del>
        {elseif eq( $change.status, 2 )}
            <ins>{$change.added|wash}</ins>
        {/if}
    {/foreach}
</div>
