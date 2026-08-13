<?php

namespace Database\Seeders;

use App\Models\ChangelogUpdate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChangelogUpdateSeeder extends Seeder
{
    /**
     * @var array<string, string> update type => accent color (hex) used to
     * generate that update's placeholder thumbnail.
     */
    private const TYPE_COLORS = [
        'new_feature' => '#f07c59',
        'improvement' => '#0ea5e9',
        'bug_fix' => '#f59e0b',
        'security' => '#ef4444',
    ];

    /**
     * @var array<string, string> update type => single glyph shown on the
     * generated placeholder thumbnail.
     */
    private const TYPE_GLYPHS = [
        'new_feature' => '✨',
        'improvement' => '↗',
        'bug_fix' => '🐛',
        'security' => '🛡',
    ];

    public function run(): void
    {
        $updates = [
            [
                'title' => 'Multiple Coupon Codes per Campaign',
                'type' => 'new_feature',
                'released_at' => now()->subDays(1)->toDateString(),
                'description' => "One discount campaign can now carry several coupon codes instead of just one. Give the same promotion a different code per channel or partner — they all share the same discount type and value, but each code can have its own usage limit.\n\nA new \"Unified Usage Limit\" switch lets you choose between one shared limit for the whole campaign, or a separate limit per code — whichever is easier to reason about for that promotion.",
            ],
            [
                'title' => 'Coupon Redemption History',
                'type' => 'new_feature',
                'released_at' => now()->subDays(1)->toDateString(),
                'description' => "A new cross-campaign Redemption History page under Discounts & Coupons shows every promo discount ever applied — which code was used, the campaign, the guest/order it belongs to, the discount amount, and when. Filter by campaign, source, or date range instead of having to open each campaign one at a time.",
            ],
            [
                'title' => 'Admins Can Delete Draft Cash Advances',
                'type' => 'new_feature',
                'released_at' => now()->subDays(2)->toDateString(),
                'description' => "Cash advances entered by mistake can now be deleted by an admin, right from the Check Voucher page or the Advances report — as long as the advance hasn't been paid out or liquidated yet. Advances that already moved money still go through Void, so the audit trail is never silently erased.",
            ],
            [
                'title' => 'Branch Setting to Disable Ingredient Tracking',
                'type' => 'new_feature',
                'released_at' => now()->subDays(3)->toDateString(),
                'description' => "Branches that don't need inventory-linked recipes can now turn on \"Disable Ingredients\" in Settings → Branch Settings. New menu items for that branch default to \"No Ingredients\" automatically, so staff no longer have to remember to flip that switch on every item.",
            ],
            [
                'title' => 'Corrected VAT Calculations in Purchase & Petty Cash Vouchers',
                'type' => 'bug_fix',
                'released_at' => now()->subDays(4)->toDateString(),
                'description' => "The Grand Total / Sub-Total preview on Purchase Voucher and Petty Cash Voucher forms was silently leaving VAT out of the total shown on screen, even though the saved amount was always correct. The on-screen total, the disbursement summary report, and petty cash replenishment totals now all agree with what actually gets recorded.",
            ],
            [
                'title' => 'Smarter Chart of Accounts Duplicate Detection',
                'type' => 'improvement',
                'released_at' => now()->subDays(5)->toDateString(),
                'description' => "Account names are now compared after normalizing case, spacing, and singular/plural wording, so \"Meal Expense\", \"MEAL EXPENSE\", and \"Meals Expenses\" are all recognized as the same account instead of quietly becoming three near-duplicate rows. Editing an account's name is now supported too.",
            ],
            [
                'title' => 'Refreshed Menu Order & Voucher Screens',
                'type' => 'improvement',
                'released_at' => now()->subDays(6)->toDateString(),
                'description' => "Cleaned up spacing and layout across the Menu Order detail page, and the Purchase/Petty Cash Voucher line-item tables — clearer totals, right-aligned amount columns, and a more readable breakdown for each line item's computed VAT.",
            ],
            [
                'title' => 'Hardened Redirect Handling After Menu Edits',
                'type' => 'security',
                'released_at' => now()->subDays(7)->toDateString(),
                'description' => "The Menu Management \"return to previous page\" behavior after saving or deleting an item now validates that the destination is actually a page within this app before redirecting there, closing off a potential open-redirect if that value were ever tampered with.",
            ],
        ];

        foreach ($updates as $data) {
            $existing = ChangelogUpdate::where('title', $data['title'])->first();

            if ($existing) {
                continue;
            }

            ChangelogUpdate::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'type' => $data['type'],
                'released_at' => $data['released_at'],
                'is_published' => true,
                'image' => $this->makePlaceholderImage($data['title'], $data['type']),
            ]);
        }
    }

    /**
     * Generates a simple branded SVG thumbnail for a seeded update — this
     * environment has neither GD nor Imagick available to rasterize a real
     * image, and an SVG is a perfectly valid, lightweight <img> source that
     * exercises the exact same storage/display path a real upload would.
     */
    private function makePlaceholderImage(string $title, string $type): string
    {
        $color = self::TYPE_COLORS[$type] ?? '#64748b';
        $glyph = self::TYPE_GLYPHS[$type] ?? '•';
        $label = ChangelogUpdate::TYPES[$type]['label'] ?? ucfirst($type);

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
            <defs>
                <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="{$color}" stop-opacity="0.92"/>
                    <stop offset="100%" stop-color="{$color}" stop-opacity="0.65"/>
                </linearGradient>
            </defs>
            <rect width="800" height="450" fill="url(#bg)"/>
            <circle cx="680" cy="90" r="140" fill="#ffffff" opacity="0.08"/>
            <circle cx="60" cy="400" r="110" fill="#ffffff" opacity="0.08"/>
            <text x="400" y="215" font-size="120" text-anchor="middle" dominant-baseline="middle">{$glyph}</text>
            <text x="400" y="330" font-family="Verdana, sans-serif" font-size="30" font-weight="bold" fill="#ffffff" text-anchor="middle">{$label}</text>
            <text x="400" y="368" font-family="Verdana, sans-serif" font-size="16" fill="#ffffff" opacity="0.85" text-anchor="middle">Dianne's Seafood House System</text>
        </svg>
        SVG;

        $path = 'changelog/'.Str::slug($title).'-'.Str::random(6).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
