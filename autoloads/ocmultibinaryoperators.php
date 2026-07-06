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
                        $groups = [];
                        $files = $attribute->content();
                        foreach ($files as $file) {
                            // Un file senza decorazione salvata ha display_group non
                            // inizializzato (null): normalizzarlo a stringa evita che
                            // finisca fuori dal bucket "senza nome" ('') per un confronto
                            // stretto più avanti.
                            $groups[] = (string)$file->attribute('display_group');
                        }
                        $groups = array_unique($groups);
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
                        if ((string)$file->attribute('display_group') === (string)$group) {
                            $fileList[] = $file;
                        }
                    }
                    // display_order non è garantito: un file senza decorazione salvata
                    // ha valore null. Usarlo come chiave d'array (come prima) fa
                    // collassare più file sulla stessa chiave (null diventa '') e
                    // solo l'ultimo processato resta visibile. Ordinare con usort e
                    // mandare in coda gli ordini mancanti evita che un file scompaia.
                    usort($fileList, function ($a, $b) {
                        $orderA = $a->attribute('display_order');
                        $orderB = $b->attribute('display_order');
                        $orderA = $orderA === null ? PHP_INT_MAX : (int)$orderA;
                        $orderB = $orderB === null ? PHP_INT_MAX : (int)$orderB;
                        if ($orderA === $orderB) {
                            return 0;
                        }
                        return $orderA < $orderB ? -1 : 1;
                    });
                    $operatorValue = $fileList;
                }
                break;
        }
    }
}