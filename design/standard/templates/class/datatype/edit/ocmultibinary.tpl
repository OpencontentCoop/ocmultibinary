{* DO NOT EDIT THIS FILE! Use an override template instead. *}
<div class="block">
    <label for="ContentClass_ocmultibinary_max_filesize_{$class_attribute.id}">
        {'Max file size'|i18n( 'design/standard/class/datatype' )}:
    </label>
    <input type="text" id="ContentClass_ocmultibinary_max_filesize_{$class_attribute.id}"
           name="ContentClass_ocmultibinary_max_filesize_{$class_attribute.id}" value="{$class_attribute.data_int1}"
           size="5" maxlength="5"/>&nbsp;<span class="normal">MB</span>
</div>
<div class="block">
    <label for="ContentClass_ocmultibinary_max_number_of_files_{$class_attribute.id}">
        {'Max number of files'|i18n( 'design/standard/class/datatype' )}:
    </label>
    <input type="text" id="ContentClass_ocmultibinary_max_number_of_files_{$class_attribute.id}"
           name="ContentClass_ocmultibinary_max_number_of_files_{$class_attribute.id}"
           value="{$class_attribute.data_int2}" size="5" maxlength="5"/>
</div>
<div class="block">
    <label for="ContentClass_ocmultibinary_allow_decoration_{$class_attribute.id}">
        {'Enable name customisation'|i18n( 'design/standard/class/datatype' )}:
    </label>
    <input type="checkbox" id="ContentClass_ocmultibinary_allow_decoration_{$class_attribute.id}"
           name="ContentClass_ocmultibinary_allow_decoration_{$class_attribute.id}"
           {if $class_attribute.data_int3|eq(1)}checked="checked"{/if} value="1"/>
</div>