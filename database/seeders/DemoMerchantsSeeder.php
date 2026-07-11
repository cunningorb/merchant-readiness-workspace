<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Services\CreateAssessmentService;
use App\Services\SaveAssessmentAnswersService;
use App\Services\SubmitAssessmentService;
use Illuminate\Database\Seeder;

class DemoMerchantsSeeder extends Seeder
{
    public function run(
        CreateAssessmentService $createService,
        SaveAssessmentAnswersService $saveService,
        SubmitAssessmentService $submitService,
    ): void {
        if (Merchant::where('is_demo', true)->exists()) {
            return;
        }

        foreach ($this->profiles() as $profile) {
            $assessment = $createService->createAnonymousDraft();

            $assessment->merchant->update([
                'is_demo' => true,
                'contact_name' => $profile['contact_name'],
                'website' => $profile['website'],
            ]);

            $assessment = $saveService->save($assessment, $profile['answers']);
            $submitService->submit($assessment);
        }
    }

    /**
     * @return array<int, array{contact_name: string, website: string, answers: array<int, array{question_key: string, value: mixed}>}>
     */
    private function profiles(): array
    {
        return [
            [
                'contact_name' => 'Priya Anand',
                'website' => 'thistleandbloom.example',
                'answers' => [
                    ['question_key' => 'business.company_name', 'value' => 'Thistle & Bloom Apparel'],
                    ['question_key' => 'business.contact_email', 'value' => 'hello@thistleandbloom.example'],
                    ['question_key' => 'business.monthly_order_volume', 'value' => '1,000-10,000'],
                    ['question_key' => 'catalog.sku_count', 'value' => '500-5,000'],
                    ['question_key' => 'catalog.fit_sensitive_categories', 'value' => ['Apparel', 'Footwear']],
                    ['question_key' => 'return_policy.window_days', 'value' => '14 days or less'],
                    ['question_key' => 'return_policy.policy_clarity', 'value' => 'Not documented'],
                    ['question_key' => 'exchanges.offered', 'value' => false],
                    ['question_key' => 'exchanges.incentives', 'value' => []],
                    ['question_key' => 'manual_operations.weekly_hours', 'value' => '50+'],
                    ['question_key' => 'manual_operations.common_bottlenecks', 'value' => ['Approvals', 'Labels', 'Refund timing', 'Inventory updates', 'Customer support handoffs']],
                    ['question_key' => 'platform.ecommerce_platform', 'value' => 'WooCommerce'],
                    ['question_key' => 'platform.return_tools', 'value' => 'Email/spreadsheets'],
                ],
            ],
            [
                'contact_name' => 'Marcus Webb',
                'website' => 'northlineoutdoor.example',
                'answers' => [
                    ['question_key' => 'business.company_name', 'value' => 'Northline Outdoor Supply'],
                    ['question_key' => 'business.contact_email', 'value' => 'ops@northlineoutdoor.example'],
                    ['question_key' => 'business.monthly_order_volume', 'value' => '10,001-50,000'],
                    ['question_key' => 'catalog.sku_count', 'value' => '5,001-25,000'],
                    ['question_key' => 'catalog.fit_sensitive_categories', 'value' => ['Apparel', 'Footwear', 'Accessories']],
                    ['question_key' => 'return_policy.window_days', 'value' => '31-60 days'],
                    ['question_key' => 'return_policy.policy_clarity', 'value' => 'Detailed policy page'],
                    ['question_key' => 'exchanges.offered', 'value' => true],
                    ['question_key' => 'exchanges.incentives', 'value' => ['Free shipping', 'Instant exchange']],
                    ['question_key' => 'manual_operations.weekly_hours', 'value' => '5-20'],
                    ['question_key' => 'manual_operations.common_bottlenecks', 'value' => ['Refund timing']],
                    ['question_key' => 'platform.ecommerce_platform', 'value' => 'Shopify'],
                    ['question_key' => 'platform.return_tools', 'value' => 'Returns app'],
                ],
            ],
            [
                'contact_name' => 'Elena Cho',
                'website' => 'vantagehomegoods.example',
                'answers' => [
                    ['question_key' => 'business.company_name', 'value' => 'Vantage Home Goods'],
                    ['question_key' => 'business.contact_email', 'value' => 'cx@vantagehomegoods.example'],
                    ['question_key' => 'business.monthly_order_volume', 'value' => '50,000+'],
                    ['question_key' => 'catalog.sku_count', 'value' => '25,000+'],
                    ['question_key' => 'catalog.fit_sensitive_categories', 'value' => ['Home goods']],
                    ['question_key' => 'return_policy.window_days', 'value' => 'More than 60 days'],
                    ['question_key' => 'return_policy.policy_clarity', 'value' => 'Contextual policy by product/order'],
                    ['question_key' => 'exchanges.offered', 'value' => true],
                    ['question_key' => 'exchanges.incentives', 'value' => ['Bonus credit', 'Free shipping', 'Instant exchange', 'Size recommendations']],
                    ['question_key' => 'manual_operations.weekly_hours', 'value' => 'Under 5'],
                    ['question_key' => 'manual_operations.common_bottlenecks', 'value' => []],
                    ['question_key' => 'platform.ecommerce_platform', 'value' => 'Custom/Other'],
                    ['question_key' => 'platform.return_tools', 'value' => 'Custom automation'],
                ],
            ],
        ];
    }
}
