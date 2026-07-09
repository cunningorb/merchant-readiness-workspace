<?php

namespace App\Services;

use Illuminate\Support\Collection;

class AssessmentQuestionCatalog
{
    public function sections(): array
    {
        return [
            [
                'key' => 'business',
                'label' => 'Business',
                'questions' => [
                    ['key' => 'business.company_name', 'label' => 'Company name', 'type' => 'text', 'required' => true],
                    ['key' => 'business.contact_email', 'label' => 'Work email', 'type' => 'email', 'required' => true],
                    ['key' => 'business.monthly_order_volume', 'label' => 'Monthly order volume', 'type' => 'select', 'required' => true, 'options' => ['Under 1,000', '1,000-10,000', '10,001-50,000', '50,000+']],
                ],
            ],
            [
                'key' => 'catalog',
                'label' => 'Catalog',
                'questions' => [
                    ['key' => 'catalog.sku_count', 'label' => 'SKU count', 'type' => 'select', 'required' => true, 'options' => ['Under 500', '500-5,000', '5,001-25,000', '25,000+']],
                    ['key' => 'catalog.fit_sensitive_categories', 'label' => 'Fit-sensitive categories', 'type' => 'multiselect', 'required' => false, 'options' => ['Apparel', 'Footwear', 'Accessories', 'Home goods', 'Electronics']],
                ],
            ],
            [
                'key' => 'return_policy',
                'label' => 'Return Policy',
                'questions' => [
                    ['key' => 'return_policy.window_days', 'label' => 'Return window', 'type' => 'select', 'required' => true, 'options' => ['14 days or less', '15-30 days', '31-60 days', 'More than 60 days']],
                    ['key' => 'return_policy.policy_clarity', 'label' => 'How clearly is the policy communicated?', 'type' => 'select', 'required' => true, 'options' => ['Not documented', 'Basic FAQ', 'Detailed policy page', 'Contextual policy by product/order']],
                ],
            ],
            [
                'key' => 'exchanges',
                'label' => 'Exchanges',
                'questions' => [
                    ['key' => 'exchanges.offered', 'label' => 'Do you offer exchanges?', 'type' => 'boolean', 'required' => true],
                    ['key' => 'exchanges.incentives', 'label' => 'Exchange incentives', 'type' => 'multiselect', 'required' => false, 'options' => ['Bonus credit', 'Free shipping', 'Instant exchange', 'Size recommendations']],
                ],
            ],
            [
                'key' => 'manual_operations',
                'label' => 'Manual Operations',
                'questions' => [
                    ['key' => 'manual_operations.weekly_hours', 'label' => 'Weekly manual returns hours', 'type' => 'select', 'required' => true, 'options' => ['Under 5', '5-20', '21-50', '50+']],
                    ['key' => 'manual_operations.common_bottlenecks', 'label' => 'Common bottlenecks', 'type' => 'multiselect', 'required' => false, 'options' => ['Approvals', 'Labels', 'Refund timing', 'Inventory updates', 'Customer support handoffs']],
                ],
            ],
            [
                'key' => 'platform',
                'label' => 'Platform',
                'questions' => [
                    ['key' => 'platform.ecommerce_platform', 'label' => 'Commerce platform', 'type' => 'select', 'required' => true, 'options' => ['Shopify', 'WooCommerce', 'BigCommerce', 'Magento', 'Custom/Other']],
                    ['key' => 'platform.return_tools', 'label' => 'Current return tooling', 'type' => 'select', 'required' => true, 'options' => ['Email/spreadsheets', 'Helpdesk workflow', 'Returns app', 'Custom automation']],
                ],
            ],
        ];
    }

    public function questions(): Collection
    {
        return collect($this->sections())
            ->flatMap(fn (array $section) => collect($section['questions'])->map(fn (array $question) => [
                ...$question,
                'section' => $section['key'],
            ]));
    }

    public function question(string $key): ?array
    {
        return $this->questions()->firstWhere('key', $key);
    }

    public function hasQuestion(string $key): bool
    {
        return $this->question($key) !== null;
    }
}
