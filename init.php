<?php

use Bitrix\Main\Loader;
use Bitrix\Iblock\CIBlockElement;

Loader::includeModule('iblock');

AddEventHandler("iblock", "OnAfterIBlockElementAdd", "OnAfterIBlockElementAddHandler");
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", "OnAfterIBlockElementUpdateHandler");
AddEventHandler("iblock", "OnBeforeIBlockElementDelete", "OnBeforeIBlockElementDeleteHandler");
AddEventHandler("iblock", "OnBeforeIBlockElementUpdate", "preventUnchangeableFieldUpdate");

function OnAfterIBlockElementAddHandler(&$arFields)
{
    if ($arFields["IBLOCK_ID"] == "IBLOCK_ID") {
        $vacancyData = [
            'event' => 'add',
            'TITLE' => $arFields['NAME'],
            'CATEGORY' => $arFields['PROPERTY_VALUES']['CATEGORY'], 
            'DUTIES' => $arFields['DETAIL_TEXT'],
        ];

        $response = requestWebhookCurl($vacancyData);

        $responseData = json_decode($response, true);
        if (isset($responseData['ID'])) {
            CIBlockElement::SetPropertyValuesEx($arFields['ID'], $arFields['IBLOCK_ID'], ['UF_MAIN_SITE_ID' => $responseData['ID']]);
        } else {
            logError("Failed to add vacancy on main site: " . $response);
        }
    }
}

function OnAfterIBlockElementUpdateHandler(&$arFields)
{
    if ($arFields["IBLOCK_ID"] == "IBLOCK_ID") {
        $element = CIBlockElement::GetByID($arFields['ID'])->GetNextElement();
        $props = $element->GetProperties();
        $mainSiteId = $props['UF_MAIN_SITE_ID']['VALUE'];

        if ($mainSiteId) {
            $vacancyData = [
                'event' => 'update',
                'ID' => $mainSiteId,
                'TITLE' => $arFields['NAME'],
                'CATEGORY' => $arFields['PROPERTY_VALUES']['CATEGORY'], 
                'DUTIES' => $arFields['DETAIL_TEXT'],
            ];

            $response = requestWebhookCurl($vacancyData);
            $responseData = json_decode($response, true);
            if (!isset($responseData['STATUS']) || $responseData['STATUS'] !== 'SUCCESS') {
                logError("Failed to update vacancy on main site: " . $response);
            }
        }
    }
}

function OnBeforeIBlockElementDeleteHandler($ID)
{
    $res = CIBlockElement::GetByID($ID);
    if ($arFields = $res->GetNext()) {
        if ($arFields["IBLOCK_ID"] == "IBLOCK_ID") { 
            $element = CIBlockElement::GetByID($ID)->GetNextElement();
            $props = $element->GetProperties();
            $mainSiteId = $props['UF_MAIN_SITE_ID']['VALUE'];

            if ($mainSiteId) {
                $vacancyData = [
                    'event' => 'delete',
                    'ID' => $mainSiteId,
                ];

                $response = requestWebhookCurl($vacancyData);
                $responseData = json_decode($response, true);
                if (!isset($responseData['STATUS']) || $responseData['STATUS'] !== 'SUCCESS') {
                    logError("Failed to delete vacancy on main site: " . $response);
                }
            }
        }
    }
}

function preventUnchangeableFieldUpdate(&$arFields)
{
    $iblockId = "IBLOCK_ID"; 
    $propertyCode = 'UF_MAIN_SITE_ID'; 

    if ($arFields["IBLOCK_ID"] == $iblockId) {
        $elementId = $arFields['ID'];
        $element = CIBlockElement::GetByID($elementId)->GetNextElement();
        $props = $element->GetProperties();

        if (isset($props[$propertyCode]['VALUE'])) {
            $oldValue = $props[$propertyCode]['VALUE'];

            // Если значение не пустое и новое значение отличается от старого, блокируем изменение
            if ($oldValue && isset($arFields['PROPERTY_VALUES'][$propertyCode]) && $arFields['PROPERTY_VALUES'][$propertyCode] != $oldValue) {
                global $APPLICATION;
                $APPLICATION->throwException("Поле '$propertyCode' не может быть изменено.");
                return false;
            }
        }
    }
}

function requestWebhookCurl($data)
{
    $webhookUrl = "https://site.com/rest/webhook.php"; 
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function logError($message) {
    // Логирование ошибок для отладки
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/log/webhook_errors.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}
