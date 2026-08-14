<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use App\Database\Seeds\Ci4msDefaultsSeeder;
use App\Database\Seeds\Ci4msReferenceDataSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\MigrationManager\Libraries\SeederScanner;

/**
 * `SeederScanner::discover()` testleri.
 *
 * DB gerekmez. `discover()`'ın gerçekten glob ettiği yol (`app/Database/Seeds/`)
 * ROOTPATH'e göre sabit olduğundan, fixture `tests/_support` altına DEĞİL,
 * doğrudan o gerçek dizine yazılır (Görev 19'un build-lead probe'unun aynı
 * deseni, bkz. context.md). Her fixture kendi test metodunun İÇİNDE
 * oluşturulup tearDown()'da temizlenir; "fixture yok" senaryosu ayrı, temiz
 * bir test metodudur.
 *
 * @internal
 */
final class SeederScannerTest extends CIUnitTestCase
{
    private ?string $fixturePath  = null;
    private ?string $fixtureFqcn  = null;

    protected function tearDown(): void
    {
        if ($this->fixturePath !== null && (is_file($this->fixturePath) || is_link($this->fixturePath))) {
            unlink($this->fixturePath);
        }

        $this->fixturePath = null;
        $this->fixtureFqcn = null;

        parent::tearDown();
    }

    /**
     * `Seeder` alt sınıfı olan VE `WebRunnableSeeder` implement eden bir
     * fixture diskte varken discover() onu bulur; label/repeatable doğru
     * döner.
     */
    public function testDiscoverFindsAWebRunnableFixtureWhenPresent(): void
    {
        $this->createFixture();

        $discovered = (new SeederScanner())->discover();

        $match = null;
        foreach ($discovered as $entry) {
            if ($entry['class'] === $this->fixtureFqcn) {
                $match = $entry;
                break;
            }
        }

        $this->assertNotNull($match, 'Fixture seeder implementing WebRunnableSeeder was not discovered.');
        $this->assertSame('mmScannerFixtureLabel', $match['label']);
        $this->assertTrue($match['repeatable']);
    }

    /**
     * Fixture YOKKEN (bugünkü production baseline, Görev 12 sonrası):
     * `discover()` `Ci4msDefaultsSeeder`'ı İÇERMEZ (interface implement
     * etmediği için, hesap oluşturma artık hiçbir web-runnable seeder'da
     * yok) ama `Ci4msReferenceDataSeeder`'ı İÇERİR -- bu sınıf
     * `WebRunnableSeeder` implement ediyor (bkz. app/Database/Seeds/
     * Ci4msReferenceDataSeeder.php). Önceki assertion (`discover()===[]`)
     * bu seeder eklenmeden ÖNCEKİ baseline'ı doğruluyordu; `Ci4msReferenceDataSeeder`
     * eklenmesiyle o assertion artık YANLIŞ hale geldiği için güncellendi.
     */
    public function testDiscoverReflectsRegisteredWebRunnableSeeders(): void
    {
        $discovered = (new SeederScanner())->discover();
        $classes    = array_column($discovered, 'class');

        $this->assertNotContains(Ci4msDefaultsSeeder::class, $classes, 'Ci4msDefaultsSeeder does not implement WebRunnableSeeder and must never be discovered.');
        $this->assertContains(Ci4msReferenceDataSeeder::class, $classes, 'Ci4msReferenceDataSeeder implements WebRunnableSeeder and must be discovered.');

        $match = null;
        foreach ($discovered as $entry) {
            if ($entry['class'] === Ci4msReferenceDataSeeder::class) {
                $match = $entry;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertSame('seederReferenceData', $match['label']);
        $this->assertTrue($match['repeatable']);
    }

    /**
     * Gerçek `app/Database/Seeds/` dizinine benzersiz isimli bir fixture PHP
     * dosyası yazar; `$this->fixturePath`/`$this->fixtureFqcn` tearDown()'da
     * kullanılmak üzere kaydedilir.
     */
    private function createFixture(): void
    {
        $className = 'MmScannerFixture_' . bin2hex(random_bytes(4));

        $this->fixturePath = ROOTPATH . 'app/Database/Seeds/' . $className . '.php';
        $this->fixtureFqcn = 'App\\Database\\Seeds\\' . $className;

        $contents = <<<PHP
            <?php

            namespace App\Database\Seeds;

            use CodeIgniter\Database\Seeder;
            use Modules\MigrationManager\Contracts\WebRunnableSeeder;

            class {$className} extends Seeder implements WebRunnableSeeder
            {
                public static function seederLabel(): string
                {
                    return 'mmScannerFixtureLabel';
                }

                public static function isRepeatable(): bool
                {
                    return true;
                }

                public function run(): void
                {
                }
            }

            PHP;

        file_put_contents($this->fixturePath, $contents);
    }
}
