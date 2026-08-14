<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\ExampleSeeder;
use Tests\Support\Models\ExampleModel;

/**
 * @internal
 */
final class ExampleDatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // Kendi fixture namespace'i ile sınırlı kalır (CIUnitTestCase varsayılanıyla
    // aynı değer, açıkça yazıldı). $refresh=false ZORUNLU: DatabaseTestTrait::
    // regressDatabase() -> MigrationRunner::regress() burada $this->namespace'i
    // setNamespace() ile ne verilirse verilsin dahili olarak null'a zorluyor
    // (vendor/codeigniter4/framework/system/Database/MigrationRunner.php:289-291),
    // bu da paylaşımlı ci4ms_test şemasındaki TÜM namespace'lerin migration
    // geçmişini batch 0'a kadar geri alıp tabloları düşürüyor.
    protected $namespace = 'Tests\Support';
    protected $refresh   = false;
    protected $seed      = ExampleSeeder::class;

    /**
     * factories tablosunu her testten sonra boşaltır.
     *
     * $refresh=false ile tablo testler arasında düşürülmüyor; ExampleSeeder
     * ise idempotent değil (koşulsuz insert), seedOnce de kapalı olduğu için
     * her setUp() 3 satır daha ekler. Temizlenmezse testModelFindAll()'ın
     * assertCount(3, ...) beklentisi ikinci test veya sonraki bir koşumda
     * birikmiş satırlar yüzünden kırılır.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->db->table('factories')->emptyTable();

        parent::tearDown();
    }

    public function testModelFindAll(): void
    {
        $model = new ExampleModel();

        // Get every row created by ExampleSeeder
        $objects = $model->findAll();

        // Make sure the count is as expected
        $this->assertCount(3, $objects);
    }

    public function testSoftDeleteLeavesRow(): void
    {
        $model = new ExampleModel();
        $this->setPrivateProperty($model, 'useSoftDeletes', true);
        $this->setPrivateProperty($model, 'tempUseSoftDeletes', true);

        /** @var stdClass $object */
        $object = $model->first();
        $model->delete($object->id);

        // The model should no longer find it
        $this->assertNull($model->find($object->id));

        // ... but it should still be in the database
        $result = $model->builder()->where('id', $object->id)->get()->getResult();

        $this->assertCount(1, $result);
    }
}
