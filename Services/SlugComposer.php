<?php

declare(strict_types=1);

namespace PrestoWorld\Modules\SocialFeed\Services;

/**
 * Ghép slug URL cho feed post từ slug do AI sinh + shop id + hash của post.
 *
 * Cấu trúc: {ai-slug}-{shopId}-{hash6}, luôn <= 60 ký tự ASCII.
 *
 * - ai-slug : phần "ý nghĩa" do Groq sinh, đã transliterate tiếng Việt -> ASCII.
 * - shopId : shop id rút gọn (<= 8 ký tự) để slug luôn biết post thuộc cửa hàng nào.
 * - hash6  : 6 ký tự đầu md5 của seed post — đảm bảo hai post cùng nội dung
 *            ở hai shop (hoặc một shop) vẫn ra slug khác nhau.
 */
final class SlugComposer
{
    /** Chiều dài slug tối đa — khớp cột `slug` VARCHAR(60). */
    public const MAX_LENGTH = 60;

    /** Số ký tự hash suffix. */
    public const HASH_LENGTH = 6;

    /** Shop id tối đa (phần giữa). */
    public const SHOP_ID_MAX = 8;

    /**
     * Bảng ánh xạ dấu tiếng Việt -> ASCII, không cần ext-intl.
     * @var array<string, string>
     */
    private const VI_MAP = [
        'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
    ];

    /**
     * Ghép slug cuối cùng. $aiSlug có thể rỗng/invalid — phần meaning sẽ bị
     * bỏ, chỉ còn {shopId}-{hash6}, nhưng slug vẫn hợp lệ và duy nhất.
     */
    public function compose(string $aiSlug, string $businessId, string $seed): string
    {
        $hash = $this->hash6($seed);
        $shopId = $this->shopId($businessId);
        $suffix = '-' . $shopId . '-' . $hash;

        $meaning = $this->slugify($aiSlug);
        $budget = self::MAX_LENGTH - strlen($suffix);
        $meaning = $this->truncate($meaning, $budget);

        if ($meaning === '') {
            return ltrim($suffix, '-');
        }

        return $meaning . $suffix;
    }

    /**
     * Slug mặc định khi post mới được tạo (chưa AI sinh): chính là UUID của post.
     */
    public function defaultSlug(string $postId): string
    {
        return substr($this->slugify($postId), 0, self::MAX_LENGTH);
    }

    /**
     * Transliterate + slugify văn bản bất kỳ (kể cả tiếng Việt có dấu)
     * sang ASCII a-z0-9-, gộp mọi ký tự liên tiếp thành một dấu gạch.
     */
    public function slugify(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $lower = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        // Bỏ markup còn sót trước khi slugify.
        $plain = preg_replace('#<[^>]*>#u', ' ', $lower) ?? $lower;
        $plain = strtr($plain, self::VI_MAP);

        $slug = preg_replace('/[^a-z0-9]+/', '-', $plain) ?? '';
        $slug = trim($slug, '-');

        return $this->truncate($slug, self::MAX_LENGTH);
    }

    /**
     * Shop id rút gọn, luôn ASCII. business_id rỗng (feed toàn cục) -> 'main'.
     */
    public function shopId(string $businessId): string
    {
        $id = $this->slugify($businessId);

        if ($id === '') {
            return 'main';
        }

        // Cắt cụt 8 ký tự có thể rơi vào giữa gạch -> trim để tránh slug "--".
        return rtrim(substr($id, 0, self::SHOP_ID_MAX), '-') ?: 'main';
    }

    /**
     * 6 ký tự đầu md5($seed) — ổn định, không phụ thuộc nội dung slug.
     */
    public function hash6(string $seed): string
    {
        return substr(md5($seed), 0, self::HASH_LENGTH);
    }

    /**
     * Cắt slug theo ranh giới dấu gạch, không bao giờ cắt giữa token.
     */
    private function truncate(string $slug, int $max): string
    {
        if ($max <= 0 || $slug === '') {
            return '';
        }

        if (strlen($slug) <= $max) {
            return $slug;
        }

        $cut = substr($slug, 0, $max);
        $lastDash = strrpos($cut, '-');

        // Không cắt được theo token (chuỗi dài không có gạch) -> cắt cứng.
        if ($lastDash === false || $lastDash < (int) ($max * 0.5)) {
            return rtrim($cut, '-');
        }

        return substr($cut, 0, $lastDash);
    }
}
