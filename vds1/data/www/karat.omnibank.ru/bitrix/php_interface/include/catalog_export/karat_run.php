<?php

//<title>Карат</title>
// Выведем в файл данных название выбраного инфоблока
$strName = "";

// Переменная $IBLOCK_ID должна быть установлена
// мастером экспорта или из профиля
// Переменная $SETUP_FILE_NAME должна быть установлена 
// мастером экспорта или из профиля
//$IBLOCK_ID = IntVal($IBLOCK_ID);
$SETUP_FILE_NAME = "/bitrix/catalog_export/karat.xml";
$IBLOCK_ID = 2;
// Модули каталога и инфоблоков уже подключены


$export = new \Bitrix\Main\XmlWriter(array(
    'file' => $SETUP_FILE_NAME,
    'create_file' => true,
    'charset' => SITE_CHARSET,
    'lowercase' => true //приводить ли все теги к нижнему регистру (для педантов)
        ));

//открываем файл
$export->openFile();
//обрамляем массив тегом

$export->writeBeginTag('yml_catalog date="' . date('Y-m-d H:i') . '"');
$export->writeBeginTag('shop');
$export->writeBeginTag('products');


$params = ['METAL_ALLOY_COLOR', 'BRAND_REF', 'GEM', 'GEM_GROUP', 'NUMBER_STONES', 'PLATING', 'GENDER', 'EARRINGS_LOCK', 'COMPLECT', 'TM_GROUP_NAME', 'VID_PU', 'TK_GROUP'];
$select_main = ['ID', 'IBLOCK_ID', 'SECTION' => 'IBLOCK_SECTION.NAME', 'ARTICLE' => 'ARTNUMBER', 'NAME', 'DESCRIPTION' => 'DETAIL_TEXT', 'PICTURE' => 'DETAIL_PICTURE'];
$select = array_merge($select_main, $params);

$dbElement = \Bitrix\Iblock\Elements\ElementCatalogTable::getList([
            'select' => $select,
            'filter' => [
                '=IBLOCK_ID' => $IBLOCK_ID,
            ],
            'order' => ['ID'],
        ]);

while ($dbElementItem = $dbElement->fetch()) {
    // $export->writeItem($dbElementItem, 'product3');
    $export->writeBeginTag('product');
    $export->writeFullTag('ARTICLE', $dbElementItem['ARTICLEVALUE']);
    $export->writeFullTag('CATEGORY', $dbElementItem['SECTION']);
    $export->writeFullTag('NAME', $dbElementItem['NAME']);
    $export->writeFullTag('DESCRIPTION', $dbElementItem['DESCRIPTION']);
    $export->writeFullTag('PICTURE', 'http://' . $_SERVER['HTTP_HOST'] . CFile::GetPath($dbElementItem['PICTURE']));

    foreach ($params as $arParam) {

        $dbProperty = \Bitrix\Iblock\PropertyTable::getList([
                    'filter' => [
                        '=CODE' => $arParam
                    ],
                    'order' => ['ID'],
        ]);
        while ($dbPropertyItem = $dbProperty->fetch()) {
            $param_name = $dbPropertyItem['NAME'];
            if ($dbPropertyItem['PROPERTY_TYPE'] == 'L') {
                $rsEnum = \Bitrix\Iblock\PropertyEnumerationTable::getList(array(
                            'filter' => array('ID' => $dbElementItem['IBLOCK_ELEMENTS_ELEMENT_CATALOG_' . $arParam . '_VALUE']),
                ));

                while ($arEnum = $rsEnum->fetch()) {
                    $dbElementItem['IBLOCK_ELEMENTS_ELEMENT_CATALOG_' . $arParam . '_VALUE'] = $arEnum['VALUE'];
                    $export->writeParamTag('param name="' . $param_name . '" code="' . mb_strtolower($arParam) . '"', 'param', $dbElementItem['IBLOCK_ELEMENTS_ELEMENT_CATALOG_' . $arParam . '_VALUE']);
                }
            } else {
                $export->writeParamTag('param name="' . $param_name . '" code="' . mb_strtolower($arParam) . '"', 'param', $dbElementItem['IBLOCK_ELEMENTS_ELEMENT_CATALOG_' . $arParam . '_VALUE']);
            }
        }
    }

    $bdSKU = CCatalogSKU::getOffersList($dbElementItem['ID'], 0, array('ACTIVE' => 'Y'), array('ID', 'IBLOCK_ID', 'WEIGHT', 'PURCHASING_PRICE', 'QUANTITY', 'REG_NUMBER', 'SIZE', 'VAT', 'AMOUNT'), array("CODE" => array('GEM', 'SIZE', 'WEIGHT', 'WEIGHT_GEM', 'WEIGHT_METAL', 'REG_NUMBER')));
    $export->writeBeginTag('offers');

    foreach ($bdSKU[$dbElementItem['ID']] as $arSKU) {

        $rsStoreProduct = \Bitrix\Catalog\StoreProductTable::getList(array(
                    'filter' => array('=PRODUCT_ID' => $arSKU['ID'], 'STORE.ACTIVE' => 'Y'),
                    'select' => array('AMOUNT', 'STORE_ID', 'STORE_TITLE' => 'STORE.TITLE'),
        ));

        while ($arStoreProduct = $rsStoreProduct->fetch()) {
            $arSKU['QUANTITY'] = $arStoreProduct['AMOUNT'];
            $arSKU['STORE'] = $arStoreProduct['STORE_TITLE'];
        }

        $dbBarCode = \Bitrix\Catalog\StoreBarcodeTable::getList(array(
                    'filter' => array('PRODUCT_ID' => $arSKU['ID'])
        ));

        while ($arBarCode = $dbBarCode->Fetch()) {
            $offer['BARCODE'] = $arBarCode['BARCODE'];
        }


        $rsPrice = \Bitrix\Catalog\PriceTable::getList(array(
                    'filter' => array('PRODUCT_ID' => $arSKU['ID'])
        ));
        while ($arPrice = $rsPrice->fetch()) {
            $offer['PRICE'] = $arPrice['PRICE'];
        }
        $offer['PRICE_PURCHASE'] = $arSKU['PURCHASING_PRICE'];
        $offer['VAT'] = $arSKU['VAT'];
        $offer['GEM'] = $arSKU['PROPERTIES']['GEM']['VALUE'];
        $offer['SIZE'] = $arSKU['PROPERTIES']['SIZE']['VALUE'];
        $offer['WEIGHT'] = $arSKU['PROPERTIES']['WEIGHT']['VALUE'];
        $offer['WEIGHT_GEM'] = $arSKU['PROPERTIES']['WEIGHT_GEM']['VALUE'];
        $offer['WEIGHT_METAL'] = $arSKU['PROPERTIES']['WEIGHT_METAL']['VALUE'];
        $offer['REG_NUMBER'] = $arSKU['PROPERTIES']['REG_NUMBER']['VALUE'];

        $export->writeBeginTag('offer quantity="' . $arSKU['QUANTITY'] . '" stock="' . $arSKU['STORE'] . '"');
        $export->writeItem($offer);
        $export->writeEndTag('offer');
    }
    $export->writeEndTag('offers');
    $export->writeEndTag('product');
}
$export->writeEndTag('products');
$export->writeEndTag('shop');
$export->writeEndTag('yml_catalog');
?>