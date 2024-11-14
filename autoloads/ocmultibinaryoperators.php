<?php

class OCMultiBinaryOperators
{
    function operatorList()
    {
        return array(
            'ocmultibinary_available_groups',
            'ocmultibinary_list_by_group',
        );
    }

    function namedParameterPerOperator()
    {
        return true;
    }

    function namedParameterList()
    {
        return array(
            'ocmultibinary_available_groups' => array(
                'attribute' => array('type' => 'object', 'required' => true, 'default' => false),
            ),
            'ocmultibinary_list_by_group' => array(
                'attribute' => array('type' => 'object', 'required' => true, 'default' => false),
                'group' => array('type' => 'string', 'required' => true, 'default' => false),
            ),
        );
    }

    function modify(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters)
    {
        switch ($operatorName) {
            case 'ocmultibinary_available_groups':
                {
                    $operatorValue = [];
                    $attribute = $namedParameters['attribute'];
                    if ($attribute instanceof eZContentObjectAttribute) {
                        $decorations = OCMultiBinaryType::parseDecorations($attribute);
                        $groups = array_unique(array_column($decorations, 'display_group'));
                        sort($groups);
                        if ($groups[0] === ''){
                            $noName = array_shift($groups);
                            $groups[] = $noName;
                        }
                        $operatorValue = $groups;
                    }
                }
                break;
            case 'ocmultibinary_list_by_group':
                {
                    $attribute = $namedParameters['attribute'];
                    $group = $namedParameters['group'];
                    $fileList = [];
                    foreach ($attribute->content() as $file) {
                        if ($file->attribute('display_group') == $group) {
                            $fileList[$file->attribute('display_order')] = $file;
                        }
                    }
                    ksort($fileList);
                    $operatorValue = $fileList;
                }
                break;
        }
    }
}