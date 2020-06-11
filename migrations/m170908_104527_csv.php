<?php

namespace shopium\mod\csv\migrations;

/**
 * Generation migrate by PIXELION CMS
 *
 * @author PIXELION CMS development team <dev@pixelion.com.ua>
 * @link http://pixelion.com.ua PIXELION CMS
 *
 * Class m170908_104527_csv
 */

use panix\engine\db\Migration;

class m170908_104527_csv extends Migration
{


    public function up()
    {

        $this->createTable('{{%csv}}', [
            'id' => $this->primaryKey()->unsigned(),
            'name' => $this->string(255)->notNull(),
            'sum' => $this->string(10)->notNull(),
            'start_date' => $this->integer(11)->null(),
            'end_date' => $this->integer(11)->null(),
            'roles' => $this->string(255),
            'switch' => $this->boolean()->defaultValue(1),
            'created_at' => $this->integer(11)->null(),
            'updated_at' => $this->integer(11)->null(),
        ], $this->tableOptions);

    }

    public function down()
    {
        $this->dropTable('{{%csv}}');
    }

}
