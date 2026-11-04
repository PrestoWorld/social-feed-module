<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Tests;

use PHPUnit\Framework\TestCase;
use PrestoWorld\Modules\SocialFeed\Services\SlugComposer;

/**
 * SlugComposer là logic thuần (không HTTP, không DB) nên test độc lập.
 *
 * Bất biến phải giữ:
 *   - slug <= SlugComposer::MAX_LENGTH (60) ký tự ASCII a-z0-9-
 *   - luôn chứa shop id ngắn
 *   - luôn chứa hash 6 ký tự của post -> hai post cùng nội dung vẫn khác slug
 */
class SlugComposerTest extends TestCase
{
    private SlugComposer $composer;

    protected function setUp(): void
    {
        $this->composer = new SlugComposer();
    }

    public function testComposeKeepsLengthWithinSixtyCharacters(): void
    {
        $slug = $this->composer->compose(
            'dai-duong-den-nhung-cau-chuyen-ve-tram-cam-cua-do-an-gi-va-nhieu-hon-nua',
            'shop-1',
            'seed-1',
        );

        self::assertLessThanOrEqual(SlugComposer::MAX_LENGTH, strlen($slug));
        self::assertSame($slug, strtolower($slug));
        self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
    }

    public function testComposeContainsShopIdAndPostHash(): void
    {
        $seed = 'post-abc';
        $slug = $this->composer->compose('tam-ly-hoc-nguoi-viet', 'shop-1', $seed);

        $suffix = '-shop-1-' . $this->composer->hash6($seed);

        self::assertStringEndsWith($suffix, $slug);
        self::assertStringContainsString('-shop-1-', $slug);
    }

    public function testComposeIsStableForTheSameInput(): void
    {
        $first = $this->composer->compose('nha-sach-bookhub', 'shop-1', 'seed-x');
        $second = $this->composer->compose('nha-sach-bookhub', 'shop-1', 'seed-x');

        self::assertSame($first, $second);
    }

    public function testComposeDiffersWhenPostSeedDiffers(): void
    {
        $a = $this->composer->compose('nha-sach-bookhub', 'shop-1', 'seed-a');
        $b = $this->composer->compose('nha-sach-bookhub', 'shop-1', 'seed-b');

        self::assertNotSame($a, $b);
    }

    public function testComposeHandlesVietnameseAccents(): void
    {
        $slug = $this->composer->compose('Đại Dương Đen – Trầm cảm', 'shop-1', 'seed-vi');

        self::assertLessThanOrEqual(SlugComposer::MAX_LENGTH, strlen($slug));
        self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
        self::assertStringStartsWith('dai-duong-den', $slug);
    }

    public function testComposeStillProducesValidSlugWhenAiReturnsEmpty(): void
    {
        $slug = $this->composer->compose('', 'shop-1', 'seed-empty');

        self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
        self::assertStringStartsWith('shop-1-', $slug);
        self::assertLessThanOrEqual(SlugComposer::MAX_LENGTH, strlen($slug));
    }

    public function testComposeTruncatesAiSlugWithoutBreakingTokens(): void
    {
        // 200 ký tự "token" dài không có gạch -> cắt cứng nhưng vẫn đúng budget.
        $long = str_repeat('a', 200);
        $slug = $this->composer->compose($long, 'shop-1', 'seed-long');

        self::assertLessThanOrEqual(SlugComposer::MAX_LENGTH, strlen($slug));
        self::assertStringEndsWith('-shop-1-' . $this->composer->hash6('seed-long'), $slug);
    }

    public function testDefaultSlugIsThePostUuid(): void
    {
        $uuid = '3f1f2f3a-9d4e-4c5b-8a7d-000000000001';

        self::assertSame($uuid, $this->composer->defaultSlug($uuid));
    }

    public function testShopIdIsShortenedAndAscii(): void
    {
        self::assertSame('main', $this->composer->shopId(''));
        self::assertSame('shop-1', $this->composer->shopId('shop-1'));
        self::assertLessThanOrEqual(SlugComposer::SHOP_ID_MAX, strlen($this->composer->shopId('cua-hang-sach-cua-shop-rat-dai')));
        self::assertSame('cua-hang', $this->composer->shopId('Cửa Hàng Sách Của Shop Rất Dài'));
    }

    public function testHash6IsSixStableHexChars(): void
    {
        $hash = $this->composer->hash6('seed-1');

        self::assertSame(6, strlen($hash));
        self::assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $hash);
        self::assertSame($hash, $this->composer->hash6('seed-1'));
    }

    public function testSlugifyStripsMarkupAndCollapsesSeparators(): void
    {
        self::assertSame('sach-moi', $this->composer->slugify('<p>Sách  Mới!!!</p>'));
        self::assertSame('', $this->composer->slugify('   '));
        self::assertSame('', $this->composer->slugify('!!!'));
    }
}
