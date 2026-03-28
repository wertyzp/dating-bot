<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Services;

use App\Chat\Services\MenuImageService;
use Tests\TestCase;
use Werty\Http\Clients\TelegramBot\Client;
use Werty\Http\Clients\TelegramBot\Exceptions\HttpException;

class ImageServiceTest extends TestCase
{

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client(config('telegram.bot_token'));
    }

    public function testGetDefaultCategoryImageFileId(): void
    {
        $sut = new MenuImageService($this->client);
        try {
            $result = $sut->getDefaultImagePath();
            echo $result;die();
        } catch (HttpException $e) {
            $this->fail(var_export($e->getRequest(), true) .PHP_EOL. var_export($e->getResponse(), true));
        }
        $this->assertNotEmpty($result);
    }

    public function testGetDefaultProductImageFileId(): void
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }
}
