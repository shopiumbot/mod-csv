<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use shopium\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;

$str = '=IMAGE("https://sun9-22.userapi.com/c855128/v855128088/114004/x7FdunGhaWc.jpg",2)';

//$preg = preg_match('/=(image|IMAGE)(.*)/',$str,$match);
$preg = preg_match('/=(image|IMAGE)(.*)/',$str,$match);

\panix\engine\CMS::dump($preg);
\panix\engine\CMS::dump($match);
/*
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(Yii::getAlias('@runtime').DIRECTORY_SEPARATOR.'google.xlsx');
$worksheet = $spreadsheet->getSheet(1);
//$props = $spreadsheet->getProperties();
$rows = [];

foreach ($worksheet->getRowIterator() as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false); // This loops through all cells,
    $cells = [];
    foreach ($cellIterator as $k => $cell) {
        if($cell->getDataType() == 'f'){

            $cells[] = 'F='.$cell->getValue();
        }else{
            $cells[] = $cell->getValue();
        }

    }
    $rows[] = $cells;
}


\panix\engine\CMS::dump($rows);*/
?>

<div class="card">
    <div class="card-header">
        <h5><?= $this->context->pageName ?></h5>
    </div>
    <?php
    $form = ActiveForm::begin();
    ?>
    <div class="card-body">
        <?= $form->field($model, 'pagenum') ?>
    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
