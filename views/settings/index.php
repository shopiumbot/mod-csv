<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use shopium\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;


use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

/*  SEND TO GOOGLE SHEETS */
$client = new Google_Client([
    'credentials' => Yii::getAlias('@app') . '/secret.json'
]);
try {

    $client->useApplicationDefaultCredentials();
    $client->setApplicationName("Something to do with my representatives");
    $client->setScopes(['https://spreadsheets.google.com/feeds', 'https://www.googleapis.com/auth/spreadsheets']); //'https://www.googleapis.com/auth/drive',
    if ($client->isAccessTokenExpired()) {
        $client->refreshTokenWithAssertion();
    }

    $accessToken = $client->fetchAccessTokenWithAssertion()["access_token"];
    /*  ServiceRequestFactory::setInstance(
        new DefaultServiceRequest($accessToken)
    );
    // Get our spreadsheet
    //composer require asimlqt/php-google-spreadsheet-client
    $spreadsheet = (new Google\Spreadsheet\SpreadsheetService)
        ->getSpreadsheetFeed()
        ->getByTitle('MyTable');

    // Get the first worksheet (tab)
    $worksheets = $spreadsheet->getWorksheetFeed()->getEntries();
    $worksheet = $worksheets[0];


    $listFeed = $worksheet->getListFeed();
    $listFeed->insert([
        'name' => "'". 'Igor',
        'phone' => "'". '2425-245-224545',
        'surname' => "'". 'Orlov',
        'city' => "'". 'Berlin',
        'age' => "'". '35',
        'date' => date_create('now')->format('Y-m-d H:i:s')
    ]);*/


    $service = new Google_Service_Sheets($client);

// Prints the names and majors of students in a sample spreadsheet:
// https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
    $spreadsheetId = '1AElm_JFUNoG4ifXPEvWAgebPRdcZdco3X411-hmrvfk';
    // $spreadsheetId = '1trzaot9J3td_Q5kAorJm3SQdW5NgCP1IRg2N98c3a9c';


    $test = $service->spreadsheets->get($spreadsheetId);
    $sheet = $test->getSheets();

    $listName = $sheet[0]->getProperties()->getTitle();

    //\panix\engine\CMS::dump($sheet[0]->getProperties());die;
//echo $test->getSpreadsheetUrl();


    $range = $listName . '';

    $values = [
        [
            'Наименование',
            'Фото',
            'РАЗМЕРНАЯ СЕТКА',
            'Berlin',
            'Описание',
            date('Y-m-d H:i:s')
        ],
        [
            'Andrew',
            '2425fadsf3',
            'Orlov',
            'Berlin',
            '35',
            date('Y-m-d H:i:s')
        ],

    ];
    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values,
    ]);
    $params = [
        'valueInputOption' => 'USER_ENTERED'
    ];

   // $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

   // $get = $service->spreadsheets_values->get($spreadsheetId, $range, []);

    //\panix\engine\CMS::dump($get->getValues());


    // printf("%f cells updated.", $result->getUpdates());
//\panix\engine\CMS::dump($result->getUpdates());
    /*$result = $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
    printf("%d cells updated.", $result->getUpdatedCells());*/
} catch (\yii\base\Exception $e) {
    echo $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile();
}
$str = '=IMAGE("https://sneakerstudio.com.ua/rus_pm_%D0%96%D0%B5%D0%BD%D1%81%D0%BA%D0%B8%D0%B5-%D0%BA%D1%80%D0%BE%D1%81%D1%81%D0%BE%D0%B2%D0%BA%D0%B8-Fila-Disruptor-Low-1010302-71A-19803_2.jpg",2)';
//$str = '=IMAGE("https://sun9-22.userapi.com/c855128/v855128088/114004/x7FdunGhaWc.jpg",2)';
$str = '=IMAGE("https://site.com/get/folder=mydir&image=file.jpg",2)';
//preg_match('/(IMAGE).*(https?:\/\/?[-\w]+\.[-\w\.]+\w(:\d+)?[-\w\/_\.]*(\?\S+)?)/iu', $str, $match);
preg_match('/(IMAGE).*[\'"](https?:\/\/?.*)[\'"]/iu', $str, $match);



// @((https?://)?([-\\w]+\\.[-\\w\\.]+)+\\w(:\\d+)?(/([-\\w/_\\.]*(\\?\\S+)?)?)*)@
//$preg = preg_match('/(IMAGE).*(https?:\/\/?[-\w]+\.[-\w\.]+\w(:\d+)?[-\w\/_\.]*(\?\S+)?)/iu',$str,$match);

//\panix\engine\CMS::dump($preg);
\panix\engine\CMS::dump($match);

?>

<div class="card">
    <div class="card-header">
        <h5><?= $this->context->pageName ?></h5>
    </div>
    <?php
    $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]);
    ?>
    <div class="card-body">
        <?= $form->field($model, 'pagenum') ?>
        <?= $form->field($model, 'indent_row') ?>
        <?= $form->field($model, 'indent_column') ?>
        <?=
        $form->field($model, 'ignore_columns')
            ->widget(\panix\ext\taginput\TagInput::class)
            ->hint('Введите буквы и нажмите Enter');
        ?>
        <?php if (YII_DEBUG) { ?>
            <div class="text-center mb-4">
                <h4>Google sheets</h4>
            </div>
            <?= $form->field($model, 'google_sheet_id') ?>
            <?= $form->field($model, 'google_token')->fileInput() ?>
        <?php } ?>
    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
