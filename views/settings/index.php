<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use shopium\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;



use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . Yii::getAlias('@app') . '/secret.json');
/*  SEND TO GOOGLE SHEETS */
$client = new Google_Client;
try{
    $client->useApplicationDefaultCredentials();
    $client->setApplicationName("Something to do with my representatives");
    $client->setScopes(['https://spreadsheets.google.com/feeds']); //'https://www.googleapis.com/auth/drive',
    if ($client->isAccessTokenExpired()) {
        $client->refreshTokenWithAssertion();
    }

    $accessToken = $client->fetchAccessTokenWithAssertion()["access_token"];
    /*  ServiceRequestFactory::setInstance(
        new DefaultServiceRequest($accessToken)
    );
    // Get our spreadsheet
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




    $range = 'Лист1';
    $values = [
        [
            'PANIX',
            '2425-245-224545',
            'Orlov',
            'Berlin',
            '35',
            date('Y-m-d H:i:s')
        ],
        [
            'Andrew',
            '2425-245-224545',
            'Orlov',
            'Berlin',
            '35',
            date('Y-m-d H:i:s')
        ],

    ];
    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
    ]);
    $params = [
        'valueInputOption' => 'USER_ENTERED'
    ];

    $get = $service->spreadsheets_values->get($spreadsheetId, $range,[]);

    \panix\engine\CMS::dump($get->getValues());

    //$insert = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);



    $result = $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
    printf("%d cells updated.", $result->getUpdatedCells());
}catch(\yii\base\Exception $e){
    echo $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile();
}


$str = '=IMAGE("https://sun9-22.userapi.com/c855128/v855128088/114004/x7FdunGhaWc.jpg",2)';

// @((https?://)?([-\\w]+\\.[-\\w\\.]+)+\\w(:\\d+)?(/([-\\w/_\\.]*(\\?\\S+)?)?)*)@
$preg = preg_match('/(IMAGE).*(https?:\/\/?[-\w]+\.[-\w\.]+\w(:\d+)?[-\w\/_\.]*(\?\S+)?)/iu',$str,$match);

\panix\engine\CMS::dump($preg);
\panix\engine\CMS::dump($match);

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
        <?= $form->field($model, 'indent_row') ?>
        <?= $form->field($model, 'indent_column') ?>
        <?=
        $form->field($model, 'ignore_columns')
            ->widget(\panix\ext\taginput\TagInput::class)
            ->hint('Введите буквы и нажмите Enter');
        ?>
    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
