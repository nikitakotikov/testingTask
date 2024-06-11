<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\CIBlockElement;

Loader::includeModule('iblock');

$data = $_POST;

if (!isset($data['event']) || empty($data['event'])) {
    echo json_encode(['ERROR' => 'Invalid event type.']);
    exit();
}

$newElement = new CIBlockElement;

$arLoadProductArray = [
    "NAME" => $data['TITLE'],
    "PROPERTY_VALUES" => [
        'CATEGORY' => $data['CATEGORY'],
    ],
    "DETAIL_TEXT" => $data['DUTIES'],
];


switch ($data['event']) {
    case 'add':

        $arLoadProductArray['IBLOCK_ID'] = "IBLOCK_ID";
        $arLoadProductArray['ACTIVE'] = "Y";

        unset($data['event']);
        
        if ($ID = $newElement->Add($arLoadProductArray)) {
            echo json_encode(['ID' => $ID]);
        } else {
            echo json_encode(['ERROR' => $newElement->LAST_ERROR]);
        }
        break;

    case 'update':

        unset($data['event']);
        
        if ($element->Update($data['ID'], $arLoadProductArray)) {
            echo json_encode(['STATUS' => 'SUCCESS']);
        } else {
            echo json_encode(['ERROR' => $element->LAST_ERROR]);
        }
        break;

    case 'delete':

        if ($element->Delete($data['ID'])) {
            echo json_encode(['STATUS' => 'SUCCESS']);
        } else {
            echo json_encode(['ERROR' => 'Failed to delete element.']);
        }
        break;

}
