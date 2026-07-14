# Milestone 3: Scoring Engine, Recommendation Engine, Rule System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Compute a readiness score and generate rule-based recommendations when a merchant submits their assessment, and expose both through a new `POST /api/assessments/{assessment}/submit` endpoint, per the approved spec at `docs/planning/archive/superpowers/specs/2026-07-09-milestone-3-scoring-recommendations-design.md`.

**Architecture:** Two independent, container-wired registries of small classes — one `QuestionScorer` per scored question, one `RecommendationRule` per recommendation trigger — each aggregated by a thin service (`ReadinessScoringService`, `RecommendationEngine`). `SubmitAssessmentService` orchestrates completeness validation, scoring, recommendation generation, and persistence, then a new controller action and route expose it. A minimal, unstyled wizard button lets a human verify recommendation quality end-to-end (the Milestone 3 STOP gate); the polished dashboard is Milestone 4.

**Tech Stack:** Laravel 11 (PHP 8.2), Eloquent, PHPUnit (class-based, `Tests\TestCase`), Vue 3 + Inertia (wizard only), SQLite in-memory for tests.

## Global Constraints

- Only these questions feed the score: `return_policy.window_days`, `return_policy.policy_clarity`, `manual_operations.weekly_hours`, `manual_operations.common_bottlenecks`, `exchanges.offered`, `exchanges.incentives`, `platform.return_tools`. All `business.*`/`catalog.*` questions and `platform.ecommerce_platform` are excluded from scoring entirely.
- Section weights: Return Policy 30, Manual Operations 30, Exchanges 20, Platform 20 (sum 100).
- Capability tiers (applied to overall score and each section score): 0-39 Foundational, 40-64 Developing, 65-84 Established, 85-100 Advanced.
- Each scored question gets its own `QuestionScorer` class file (no shared switch/match). Each recommendation trigger gets its own `RecommendationRule` class file.
- Recommendations trigger on specific raw answers, not on opaque score thresholds, so each stays independently explainable.
- Already-submitted assessments reject resubmission with `409`. Incomplete assessments (any required question unanswered) reject submission with `422` using the same `errors`-object JSON shape the existing answers endpoint already produces.
- No dashboard/chart styling in this milestone — the wizard's post-submit view is plain and unstyled by design.
- Follow existing project conventions: PHPUnit class-based tests under `Tests\TestCase`, factories for model setup, PHP 8.2 syntax (readonly properties/classes allowed).

---

## Task 1: Scoring and recommendation contracts and value objects

**Files:**
- Create: `app/Contracts/QuestionScorer.php`
- Create: `app/Contracts/AssessmentScorer.php`
- Create: `app/Contracts/RecommendationRule.php`
- Create: `app/Services/Scoring/ScoreBreakdown.php`
- Create: `app/Services/Recommendations/RecommendationDraft.php`
- Test: `tests/Unit/Services/Scoring/ScoreBreakdownTest.php`

**Interfaces:**
- Produces: `App\Contracts\QuestionScorer` (`questionKey(): string`, `section(): string`, `score(mixed $value): int`) — implemented by every scorer class in Tasks 2-8.
- Produces: `App\Contracts\AssessmentScorer` (`score(App\Models\Assessment $assessment): App\Services\Scoring\ScoreBreakdown`) — implemented by `ReadinessScoringService` in Task 9.
- Produces: `App\Contracts\RecommendationRule` (`applies(App\Models\Assessment $assessment, App\Services\Scoring\ScoreBreakdown $scores): bool`, `draft(App\Models\Assessment $assessment, App\Services\Scoring\ScoreBreakdown $scores): App\Services\Recommendations\RecommendationDraft`) — implemented by every rule class in Tasks 10-17.
- Produces: `App\Services\Scoring\ScoreBreakdown` — constructor `(int $overallScore, string $overallTier, array $sections)` where `$sections` is `array<string, array{score: int, tier: string}>`; methods `rankedSections(): array` (same shape, sorted ascending by score) and `toArray(): array`.
- Produces: `App\Services\Recommendations\RecommendationDraft` — constructor `(string $title, string $description, string $category, string $priority, string $expectedImpact)`, all readonly public properties of the same names (camelCase).

- [ ] **Step 1: Write the failing test for `ScoreBreakdown::rankedSections()`**

```php
<?php

namespace Tests\Unit\Services\Scoring;

use App\Services\Scoring\ScoreBreakdown;
use Tests\TestCase;

class ScoreBreakdownTest extends TestCase
{
    public function test_ranked_sections_are_sorted_ascending_by_score(): void
    {
        $breakdown = new ScoreBreakdown(
            overallScore: 62,
            overallTier: 'Developing',
            sections: [
                'return_policy' => ['score' => 80, 'tier' => 'Established'],
                'manual_operations' => ['score' => 30, 'tier' => 'Foundational'],
                'exchanges' => ['score' => 100, 'tier' => 'Advanced'],
                'platform' => ['score' => 50, 'tier' => 'Developing'],
            ],
        );

        $this->assertSame(
            ['manual_operations', 'platform', 'return_policy', 'exchanges'],
            array_keys($breakdown->rankedSections())
        );
    }

    public function test_to_array_includes_overall_and_sections(): void
    {
        $breakdown = new ScoreBreakdown(
            overallScore: 75,
            overallTier: 'Established',
            sections: ['return_policy' => ['score' => 75, 'tier' => 'Established']],
        );

        $this->assertSame(75, $breakdown->toArray()['overall_score']);
        $this->assertSame('Established', $breakdown->toArray()['overall_tier']);
        $this->assertArrayHasKey('return_policy', $breakdown->toArray()['sections']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/ScoreBreakdownTest.php`
Expected: FAIL with `Class "App\Services\Scoring\ScoreBreakdown" not found`

- [ ] **Step 3: Create the contracts**

`app/Contracts/QuestionScorer.php`:
```php
<?php

namespace App\Contracts;

interface QuestionScorer
{
    public function questionKey(): string;

    public function section(): string;

    public function score(mixed $value): int;
}
```

`app/Contracts/AssessmentScorer.php`:
```php
<?php

namespace App\Contracts;

use App\Models\Assessment;
use App\Services\Scoring\ScoreBreakdown;

interface AssessmentScorer
{
    public function score(Assessment $assessment): ScoreBreakdown;
}
```

`app/Contracts/RecommendationRule.php`:
```php
<?php

namespace App\Contracts;

use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

interface RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool;

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft;
}
```

- [ ] **Step 4: Create the value objects**

`app/Services/Scoring/ScoreBreakdown.php`:
```php
<?php

namespace App\Services\Scoring;

final class ScoreBreakdown
{
    /**
     * @param array<string, array{score: int, tier: string}> $sections
     */
    public function __construct(
        public readonly int $overallScore,
        public readonly string $overallTier,
        public readonly array $sections,
    ) {
    }

    /**
     * @return array<string, array{score: int, tier: string}>
     */
    public function rankedSections(): array
    {
        $sections = $this->sections;
        uasort($sections, fn (array $a, array $b) => $a['score'] <=> $b['score']);

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'overall_tier' => $this->overallTier,
            'sections' => $this->sections,
            'ranked_sections' => $this->rankedSections(),
        ];
    }
}
```

`app/Services/Recommendations/RecommendationDraft.php`:
```php
<?php

namespace App\Services\Recommendations;

final class RecommendationDraft
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $expectedImpact,
    ) {
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/ScoreBreakdownTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Contracts app/Services/Scoring/ScoreBreakdown.php app/Services/Recommendations/RecommendationDraft.php tests/Unit/Services/Scoring/ScoreBreakdownTest.php
git commit -m "Add scoring/recommendation contracts and value objects"
```

---

## Task 2: ReturnWindowScorer

**Files:**
- Create: `app/Services/Scoring/Questions/ReturnWindowScorer.php`
- Test: `tests/Unit/Services/Scoring/Questions/ReturnWindowScorerTest.php`

**Interfaces:**
- Consumes: `App\Contracts\QuestionScorer` (Task 1).
- Produces: `App\Services\Scoring\Questions\ReturnWindowScorer` implementing `QuestionScorer`, `questionKey() === 'return_policy.window_days'`, `section() === 'return_policy'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ReturnWindowScorer;
use Tests\TestCase;

class ReturnWindowScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ReturnWindowScorer();

        $this->assertSame('return_policy.window_days', $scorer->questionKey());
        $this->assertSame('return_policy', $scorer->section());
    }

    public function test_scores_by_window_length(): void
    {
        $scorer = new ReturnWindowScorer();

        $this->assertSame(0, $scorer->score('14 days or less'));
        $this->assertSame(33, $scorer->score('15-30 days'));
        $this->assertSame(67, $scorer->score('31-60 days'));
        $this->assertSame(100, $scorer->score('More than 60 days'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ReturnWindowScorerTest.php`
Expected: FAIL with `Class "App\Services\Scoring\Questions\ReturnWindowScorer" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ReturnWindowScorer implements QuestionScorer
{
    private const POINTS = [
        '14 days or less' => 0,
        '15-30 days' => 33,
        '31-60 days' => 67,
        'More than 60 days' => 100,
    ];

    public function questionKey(): string
    {
        return 'return_policy.window_days';
    }

    public function section(): string
    {
        return 'return_policy';
    }

    public function score(mixed $value): int
    {
        return self::POINTS[$value] ?? 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ReturnWindowScorerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Scoring/Questions/ReturnWindowScorer.php tests/Unit/Services/Scoring/Questions/ReturnWindowScorerTest.php
git commit -m "Add ReturnWindowScorer"
```

---

## Task 3: PolicyClarityScorer

**Files:**
- Create: `app/Services/Scoring/Questions/PolicyClarityScorer.php`
- Test: `tests/Unit/Services/Scoring/Questions/PolicyClarityScorerTest.php`

**Interfaces:**
- Consumes: `App\Contracts\QuestionScorer` (Task 1).
- Produces: `App\Services\Scoring\Questions\PolicyClarityScorer` implementing `QuestionScorer`, `questionKey() === 'return_policy.policy_clarity'`, `section() === 'return_policy'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\PolicyClarityScorer;
use Tests\TestCase;

class PolicyClarityScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new PolicyClarityScorer();

        $this->assertSame('return_policy.policy_clarity', $scorer->questionKey());
        $this->assertSame('return_policy', $scorer->section());
    }

    public function test_scores_by_clarity_level(): void
    {
        $scorer = new PolicyClarityScorer();

        $this->assertSame(0, $scorer->score('Not documented'));
        $this->assertSame(33, $scorer->score('Basic FAQ'));
        $this->assertSame(67, $scorer->score('Detailed policy page'));
        $this->assertSame(100, $scorer->score('Contextual policy by product/order'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/PolicyClarityScorerTest.php`
Expected: FAIL with `Class "App\Services\Scoring\Questions\PolicyClarityScorer" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class PolicyClarityScorer implements QuestionScorer
{
    private const POINTS = [
        'Not documented' => 0,
        'Basic FAQ' => 33,
        'Detailed policy page' => 67,
        'Contextual policy by product/order' => 100,
    ];

    public function questionKey(): string
    {
        return 'return_policy.policy_clarity';
    }

    public function section(): string
    {
        return 'return_policy';
    }

    public function score(mixed $value): int
    {
        return self::POINTS[$value] ?? 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/PolicyClarityScorerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Scoring/Questions/PolicyClarityScorer.php tests/Unit/Services/Scoring/Questions/PolicyClarityScorerTest.php
git commit -m "Add PolicyClarityScorer"
```

---

## Task 4: WeeklyHoursScorer

**Files:**
- Create: `app/Services/Scoring/Questions/WeeklyHoursScorer.php`
- Test: `tests/Unit/Services/Scoring/Questions/WeeklyHoursScorerTest.php`

**Interfaces:**
- Consumes: `App\Contracts\QuestionScorer` (Task 1).
- Produces: `App\Services\Scoring\Questions\WeeklyHoursScorer` implementing `QuestionScorer`, `questionKey() === 'manual_operations.weekly_hours'`, `section() === 'manual_operations'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\WeeklyHoursScorer;
use Tests\TestCase;

class WeeklyHoursScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new WeeklyHoursScorer();

        $this->assertSame('manual_operations.weekly_hours', $scorer->questionKey());
        $this->assertSame('manual_operations', $scorer->section());
    }

    public function test_fewer_hours_score_higher(): void
    {
        $scorer = new WeeklyHoursScorer();

        $this->assertSame(0, $scorer->score('50+'));
        $this->assertSame(33, $scorer->score('21-50'));
        $this->assertSame(67, $scorer->score('5-20'));
        $this->assertSame(100, $scorer->score('Under 5'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/WeeklyHoursScorerTest.php`
Expected: FAIL with `Class "App\Services\Scoring\Questions\WeeklyHoursScorer" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class WeeklyHoursScorer implements QuestionScorer
{
    private const POINTS = [
        '50+' => 0,
        '21-50' => 33,
        '5-20' => 67,
        'Under 5' => 100,
    ];

    public function questionKey(): string
    {
        return 'manual_operations.weekly_hours';
    }

    public function section(): string
    {
        return 'manual_operations';
    }

    public function score(mixed $value): int
    {
        return self::POINTS[$value] ?? 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/WeeklyHoursScorerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Scoring/Questions/WeeklyHoursScorer.php tests/Unit/Services/Scoring/Questions/WeeklyHoursScorerTest.php
git commit -m "Add WeeklyHoursScorer"
```

---

## Task 5: CommonBottlenecksScorer

**Files:**
- Create: `app/Services/Scoring/Questions/CommonBottlenecksScorer.php`
- Test: `tests/Unit/Services/Scoring/Questions/CommonBottlenecksScorerTest.php`

**Interfaces:**
- Consumes: `App\Contracts\QuestionScorer` (Task 1).
- Produces: `App\Services\Scoring\Questions\CommonBottlenecksScorer` implementing `QuestionScorer`, `questionKey() === 'manual_operations.common_bottlenecks'`, `section() === 'manual_operations'`. `score()` accepts an array (multiselect) or non-array (treated as 0 selected).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\CommonBottlenecksScorer;
use Tests\TestCase;

class CommonBottlenecksScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new CommonBottlenecksScorer();

        $this->assertSame('manual_operations.common_bottlenecks', $scorer->questionKey());
        $this->assertSame('manual_operations', $scorer->section());
    }

    public function test_fewer_selected_bottlenecks_score_higher(): void
    {
        $scorer = new CommonBottlenecksScorer();

        $this->assertSame(100, $scorer->score([]));
        $this->assertSame(80, $scorer->score(['Approvals']));
        $this->assertSame(60, $scorer->score(['Approvals', 'Labels']));
        $this->assertSame(0, $scorer->score(['Approvals', 'Labels', 'Refund timing', 'Inventory updates', 'Customer support handoffs']));
    }

    public function test_non_array_value_scores_as_no_bottlenecks(): void
    {
        $scorer = new CommonBottlenecksScorer();

        $this->assertSame(100, $scorer->score(null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/CommonBottlenecksScorerTest.php`
Expected: FAIL with `Class "App\Services\Scoring\Questions\CommonBottlenecksScorer" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class CommonBottlenecksScorer implements QuestionScorer
{
    private const TOTAL_OPTIONS = 5;

    public function questionKey(): string
    {
        return 'manual_operations.common_bottlenecks';
    }

    public function section(): string
    {
        return 'manual_operations';
    }

    public function score(mixed $value): int
    {
        $selected = is_array($value) ? count($value) : 0;

        return (int) round(100 - ($selected / self::TOTAL_OPTIONS) * 100);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/CommonBottlenecksScorerTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Scoring/Questions/CommonBottlenecksScorer.php tests/Unit/Services/Scoring/Questions/CommonBottlenecksScorerTest.php
git commit -m "Add CommonBottlenecksScorer"
```

---

## Task 6: ExchangesOfferedScorer

**Files:**
- Create: `app/Services/Scoring/Questions/ExchangesOfferedScorer.php`
- Test: `tests/Unit/Services/Scoring/Questions/ExchangesOfferedScorerTest.php`

**Interfaces:**
- Consumes: `App\Contracts\QuestionScorer` (Task 1).
- Produces: `App\Services\Scoring\Questions\ExchangesOfferedScorer` implementing `QuestionScorer`, `questionKey() === 'exchanges.offered'`, `section() === 'exchanges'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ExchangesOfferedScorer;
use Tests\TestCase;

class ExchangesOfferedScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ExchangesOfferedScorer();

        $this->assertSame('exchanges.offered', $scorer->questionKey());
        $this->assertSame('exchanges', $scorer->section());
    }

    public function test_scores_boolean_answer(): void
    {
        $scorer = new ExchangesOfferedScorer();

        $this->assertSame(100, $scorer->score(true));
        $this->assertSame(0, $scorer->score(false));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ExchangesOfferedScorerTest.php`
Expected: FAIL with `Class "App\Services\Scoring\Questions\ExchangesOfferedScorer" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ExchangesOfferedScorer implements QuestionScorer
{
    public function questionKey(): string
    {
        return 'exchanges.offered';
    }

    public function section(): string
    {
        return 'exchanges';
    }

    public function score(mixed $value): int
    {
        return $value === true ? 100 : 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ExchangesOfferedScorerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Scoring/Questions/ExchangesOfferedScorer.php tests/Unit/Services/Scoring/Questions/ExchangesOfferedScorerTest.php
git commit -m "Add ExchangesOfferedScorer"
```

---

## Task 7: ExchangeIncentivesScorer

**Files:**
- Create: `app/Services/Scoring/Questions/ExchangeIncentivesScorer.php`
- Test: `tests/Unit/Services/Scoring/Questions/ExchangeIncentivesScorerTest.php`

**Interfaces:**
- Consumes: `App\Contracts\QuestionScorer` (Task 1).
- Produces: `App\Services\Scoring\Questions\ExchangeIncentivesScorer` implementing `QuestionScorer`, `questionKey() === 'exchanges.incentives'`, `section() === 'exchanges'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ExchangeIncentivesScorer;
use Tests\TestCase;

class ExchangeIncentivesScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ExchangeIncentivesScorer();

        $this->assertSame('exchanges.incentives', $scorer->questionKey());
        $this->assertSame('exchanges', $scorer->section());
    }

    public function test_more_selected_incentives_score_higher(): void
    {
        $scorer = new ExchangeIncentivesScorer();

        $this->assertSame(0, $scorer->score([]));
        $this->assertSame(25, $scorer->score(['Bonus credit']));
        $this->assertSame(50, $scorer->score(['Bonus credit', 'Free shipping']));
        $this->assertSame(100, $scorer->score(['Bonus credit', 'Free shipping', 'Instant exchange', 'Size recommendations']));
    }

    public function test_non_array_value_scores_as_no_incentives(): void
    {
        $scorer = new ExchangeIncentivesScorer();

        $this->assertSame(0, $scorer->score(null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ExchangeIncentivesScorerTest.php`
Expected: FAIL with `Class "App\Services\Scoring\Questions\ExchangeIncentivesScorer" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ExchangeIncentivesScorer implements QuestionScorer
{
    private const TOTAL_OPTIONS = 4;

    public function questionKey(): string
    {
        return 'exchanges.incentives';
    }

    public function section(): string
    {
        return 'exchanges';
    }

    public function score(mixed $value): int
    {
        $selected = is_array($value) ? count($value) : 0;

        return (int) round(($selected / self::TOTAL_OPTIONS) * 100);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ExchangeIncentivesScorerTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Scoring/Questions/ExchangeIncentivesScorer.php tests/Unit/Services/Scoring/Questions/ExchangeIncentivesScorerTest.php
git commit -m "Add ExchangeIncentivesScorer"
```

---

## Task 8: ReturnToolsScorer

**Files:**
- Create: `app/Services/Scoring/Questions/ReturnToolsScorer.php`
- Test: `tests/Unit/Services/Scoring/Questions/ReturnToolsScorerTest.php`

**Interfaces:**
- Consumes: `App\Contracts\QuestionScorer` (Task 1).
- Produces: `App\Services\Scoring\Questions\ReturnToolsScorer` implementing `QuestionScorer`, `questionKey() === 'platform.return_tools'`, `section() === 'platform'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Scoring\Questions;

use App\Services\Scoring\Questions\ReturnToolsScorer;
use Tests\TestCase;

class ReturnToolsScorerTest extends TestCase
{
    public function test_identifies_its_question_and_section(): void
    {
        $scorer = new ReturnToolsScorer();

        $this->assertSame('platform.return_tools', $scorer->questionKey());
        $this->assertSame('platform', $scorer->section());
    }

    public function test_scores_by_tooling_maturity(): void
    {
        $scorer = new ReturnToolsScorer();

        $this->assertSame(0, $scorer->score('Email/spreadsheets'));
        $this->assertSame(33, $scorer->score('Helpdesk workflow'));
        $this->assertSame(67, $scorer->score('Returns app'));
        $this->assertSame(100, $scorer->score('Custom automation'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ReturnToolsScorerTest.php`
Expected: FAIL with `Class "App\Services\Scoring\Questions\ReturnToolsScorer" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Scoring\Questions;

use App\Contracts\QuestionScorer;

class ReturnToolsScorer implements QuestionScorer
{
    private const POINTS = [
        'Email/spreadsheets' => 0,
        'Helpdesk workflow' => 33,
        'Returns app' => 67,
        'Custom automation' => 100,
    ];

    public function questionKey(): string
    {
        return 'platform.return_tools';
    }

    public function section(): string
    {
        return 'platform';
    }

    public function score(mixed $value): int
    {
        return self::POINTS[$value] ?? 0;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Scoring/Questions/ReturnToolsScorerTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Scoring/Questions/ReturnToolsScorer.php tests/Unit/Services/Scoring/Questions/ReturnToolsScorerTest.php
git commit -m "Add ReturnToolsScorer"
```

---

## Task 9: Assessment::answerValue() helper, scoring config, and ReadinessScoringService

**Files:**
- Modify: `app/Models/Assessment.php`
- Create: `config/scoring.php`
- Create: `app/Services/ReadinessScoringService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Models/AssessmentTest.php` (add a test method)
- Test: `tests/Unit/Services/ReadinessScoringServiceTest.php`

**Interfaces:**
- Consumes: `App\Contracts\AssessmentScorer`, `App\Contracts\QuestionScorer` (Task 1); all 7 scorer classes (Tasks 2-8); `App\Services\Scoring\ScoreBreakdown` (Task 1).
- Produces: `Assessment::answerValue(string $questionKey): mixed` — returns the stored answer value for a question key, or `null` if unanswered.
- Produces: `App\Services\ReadinessScoringService implements AssessmentScorer`, constructor `(array $questionScorers)` (array of `QuestionScorer`). Bound in the container as `AssessmentScorer::class` so `app(AssessmentScorer::class)` resolves it with all 7 scorers injected from `config('scoring.scorers')`.

- [ ] **Step 1: Read the current Assessment model to confirm the exact insertion point**

Run: `sed -n '1,40p' app/Models/Assessment.php`
Expected: see the `answers(): HasMany` relation method (already exists) to add the new method next to it.

- [ ] **Step 2: Write the failing test for `Assessment::answerValue()`**

Add this method to `tests/Unit/Models/AssessmentTest.php` (inside the existing `AssessmentTest` class — read the file first to place it correctly among the existing test methods):

```php
    public function test_answer_value_returns_stored_value_for_question_key(): void
    {
        $assessment = Assessment::factory()->create();
        \App\Models\AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '31-60 days',
        ]);

        $this->assertSame('31-60 days', $assessment->answerValue('return_policy.window_days'));
    }

    public function test_answer_value_returns_null_when_question_unanswered(): void
    {
        $assessment = Assessment::factory()->create();

        $this->assertNull($assessment->answerValue('return_policy.window_days'));
    }
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/AssessmentTest.php --filter=answer_value`
Expected: FAIL with `Call to undefined method App\Models\Assessment::answerValue()`

- [ ] **Step 4: Add `answerValue()` to the Assessment model**

In `app/Models/Assessment.php`, add this method directly below the existing `answers(): HasMany` method:

```php
    public function answerValue(string $questionKey): mixed
    {
        return $this->answers->firstWhere('question_key', $questionKey)?->value;
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/AssessmentTest.php --filter=answer_value`
Expected: PASS (2 tests)

- [ ] **Step 6: Write the failing test for `ReadinessScoringService`**

```php
<?php

namespace Tests\Unit\Services;

use App\Contracts\AssessmentScorer;
use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private function answer(Assessment $assessment, string $questionKey, string $section, mixed $value): void
    {
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => $questionKey,
            'section' => $section,
            'value' => $value,
        ]);
    }

    public function test_computes_section_scores_weighted_overall_score_and_tiers(): void
    {
        $assessment = Assessment::factory()->create();

        $this->answer($assessment, 'return_policy.window_days', 'return_policy', 'More than 60 days'); // 100
        $this->answer($assessment, 'return_policy.policy_clarity', 'return_policy', 'Detailed policy page'); // 67
        // return_policy section average = (100 + 67) / 2 = 83.5 -> round to 84 (Established)

        $this->answer($assessment, 'manual_operations.weekly_hours', 'manual_operations', '50+'); // 0
        $this->answer($assessment, 'manual_operations.common_bottlenecks', 'manual_operations', []); // 100
        // manual_operations section average = (0 + 100) / 2 = 50 (Developing)

        $this->answer($assessment, 'exchanges.offered', 'exchanges', false); // 0
        $this->answer($assessment, 'exchanges.incentives', 'exchanges', []); // 0
        // exchanges section average = 0 (Foundational)

        $this->answer($assessment, 'platform.return_tools', 'platform', 'Custom automation'); // 100
        // platform section average = 100 (Advanced)

        $scores = app(AssessmentScorer::class)->score($assessment->fresh(['answers']));

        $this->assertSame(84, $scores->sections['return_policy']['score']);
        $this->assertSame('Established', $scores->sections['return_policy']['tier']);

        $this->assertSame(50, $scores->sections['manual_operations']['score']);
        $this->assertSame('Developing', $scores->sections['manual_operations']['tier']);

        $this->assertSame(0, $scores->sections['exchanges']['score']);
        $this->assertSame('Foundational', $scores->sections['exchanges']['tier']);

        $this->assertSame(100, $scores->sections['platform']['score']);
        $this->assertSame('Advanced', $scores->sections['platform']['tier']);

        // overall = (84*30 + 50*30 + 0*20 + 100*20) / 100 = (2520 + 1500 + 0 + 2000) / 100 = 60.4 -> 60
        $this->assertSame(60, $scores->overallScore);
        $this->assertSame('Developing', $scores->overallTier);
    }
}
```

- [ ] **Step 7: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/ReadinessScoringServiceTest.php`
Expected: FAIL — `Target [App\Contracts\AssessmentScorer] is not instantiable` (or class-not-found for `ReadinessScoringService`/`config/scoring.php` missing)

- [ ] **Step 8: Create the scoring config**

`config/scoring.php`:
```php
<?php

return [
    'scorers' => [
        \App\Services\Scoring\Questions\ReturnWindowScorer::class,
        \App\Services\Scoring\Questions\PolicyClarityScorer::class,
        \App\Services\Scoring\Questions\WeeklyHoursScorer::class,
        \App\Services\Scoring\Questions\CommonBottlenecksScorer::class,
        \App\Services\Scoring\Questions\ExchangesOfferedScorer::class,
        \App\Services\Scoring\Questions\ExchangeIncentivesScorer::class,
        \App\Services\Scoring\Questions\ReturnToolsScorer::class,
    ],

    'section_weights' => [
        'return_policy' => 30,
        'manual_operations' => 30,
        'exchanges' => 20,
        'platform' => 20,
    ],

    'tiers' => [
        39 => 'Foundational',
        64 => 'Developing',
        84 => 'Established',
        100 => 'Advanced',
    ],
];
```

- [ ] **Step 9: Create `ReadinessScoringService`**

`app/Services/ReadinessScoringService.php`:
```php
<?php

namespace App\Services;

use App\Contracts\AssessmentScorer;
use App\Contracts\QuestionScorer;
use App\Models\Assessment;
use App\Services\Scoring\ScoreBreakdown;

class ReadinessScoringService implements AssessmentScorer
{
    /**
     * @param QuestionScorer[] $questionScorers
     */
    public function __construct(private readonly array $questionScorers)
    {
    }

    public function score(Assessment $assessment): ScoreBreakdown
    {
        $assessment->loadMissing('answers');

        $pointsBySection = [];

        foreach ($this->questionScorers as $scorer) {
            $value = $assessment->answerValue($scorer->questionKey());
            $pointsBySection[$scorer->section()][] = $scorer->score($value);
        }

        $sections = [];
        $weightedTotal = 0;
        $weightTotal = 0;

        foreach (config('scoring.section_weights') as $section => $weight) {
            $points = $pointsBySection[$section] ?? [];
            $sectionScore = $points === [] ? 0 : (int) round(array_sum($points) / count($points));

            $sections[$section] = [
                'score' => $sectionScore,
                'tier' => $this->tierFor($sectionScore),
            ];

            $weightedTotal += $sectionScore * $weight;
            $weightTotal += $weight;
        }

        $overallScore = $weightTotal === 0 ? 0 : (int) round($weightedTotal / $weightTotal);

        return new ScoreBreakdown($overallScore, $this->tierFor($overallScore), $sections);
    }

    private function tierFor(int $score): string
    {
        foreach (config('scoring.tiers') as $threshold => $label) {
            if ($score <= $threshold) {
                return $label;
            }
        }

        return 'Advanced';
    }
}
```

- [ ] **Step 10: Bind `AssessmentScorer` in the service container**

In `app/Providers/AppServiceProvider.php`, replace the empty `register()` method body:

```php
    public function register(): void
    {
        $this->app->singleton(\App\Services\ReadinessScoringService::class, function ($app) {
            $scorers = array_map(
                fn (string $class) => $app->make($class),
                config('scoring.scorers'),
            );

            return new \App\Services\ReadinessScoringService($scorers);
        });

        $this->app->bind(\App\Contracts\AssessmentScorer::class, \App\Services\ReadinessScoringService::class);
    }
```

- [ ] **Step 11: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/ReadinessScoringServiceTest.php`
Expected: PASS (1 test)

- [ ] **Step 12: Run the full unit suite so far**

Run: `php artisan test --testsuite=Unit`
Expected: PASS (all tests, including Tasks 1-9)

- [ ] **Step 13: Commit**

```bash
git add app/Models/Assessment.php config/scoring.php app/Services/ReadinessScoringService.php app/Providers/AppServiceProvider.php tests/Unit/Models/AssessmentTest.php tests/Unit/Services/ReadinessScoringServiceTest.php
git commit -m "Add ReadinessScoringService aggregating registered QuestionScorers"
```

---

## Task 10: ShortReturnWindowRule

**Files:**
- Create: `app/Services/Recommendations/Rules/ShortReturnWindowRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/ShortReturnWindowRuleTest.php`

**Interfaces:**
- Consumes: `App\Contracts\RecommendationRule`, `App\Services\Recommendations\RecommendationDraft`, `App\Services\Scoring\ScoreBreakdown` (Task 1); `Assessment::answerValue()` (Task 9).
- Produces: `App\Services\Recommendations\Rules\ShortReturnWindowRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\ShortReturnWindowRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortReturnWindowRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    public function test_applies_when_window_is_14_days_or_less(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '14 days or less',
        ]);

        $rule = new ShortReturnWindowRule();

        $this->assertTrue($rule->applies($assessment->fresh(['answers']), $this->emptyScores()));
    }

    public function test_does_not_apply_when_window_is_longer(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => '31-60 days',
        ]);

        $rule = new ShortReturnWindowRule();

        $this->assertFalse($rule->applies($assessment->fresh(['answers']), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new ShortReturnWindowRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('return_policy', $draft->category);
        $this->assertSame('high', $draft->priority);
        $this->assertNotSame('', trim($draft->title));
        $this->assertNotSame('', trim($draft->description));
        $this->assertNotSame('', trim($draft->expectedImpact));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/ShortReturnWindowRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\ShortReturnWindowRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class ShortReturnWindowRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('return_policy.window_days') === '14 days or less';
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Extend your return window',
            description: 'A 14-day-or-less return window is shorter than what most merchants offer. Extending it typically increases buyer confidence and checkout conversion without meaningfully increasing return volume.',
            category: 'return_policy',
            priority: 'high',
            expectedImpact: 'Higher checkout conversion and fewer pre-purchase questions about returns.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/ShortReturnWindowRuleTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/ShortReturnWindowRule.php tests/Unit/Services/Recommendations/Rules/ShortReturnWindowRuleTest.php
git commit -m "Add ShortReturnWindowRule"
```

---

## Task 11: UndocumentedPolicyRule

**Files:**
- Create: `app/Services/Recommendations/Rules/UndocumentedPolicyRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/UndocumentedPolicyRuleTest.php`

**Interfaces:**
- Consumes: same as Task 10.
- Produces: `App\Services\Recommendations\Rules\UndocumentedPolicyRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\UndocumentedPolicyRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UndocumentedPolicyRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withClarity(string $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.policy_clarity',
            'section' => 'return_policy',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_policy_is_not_documented(): void
    {
        $rule = new UndocumentedPolicyRule();

        $this->assertTrue($rule->applies($this->withClarity('Not documented'), $this->emptyScores()));
    }

    public function test_applies_when_policy_is_only_a_basic_faq(): void
    {
        $rule = new UndocumentedPolicyRule();

        $this->assertTrue($rule->applies($this->withClarity('Basic FAQ'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_policy_is_detailed(): void
    {
        $rule = new UndocumentedPolicyRule();

        $this->assertFalse($rule->applies($this->withClarity('Detailed policy page'), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new UndocumentedPolicyRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('return_policy', $draft->category);
        $this->assertSame('medium', $draft->priority);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/UndocumentedPolicyRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\UndocumentedPolicyRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class UndocumentedPolicyRule implements RecommendationRule
{
    private const WEAK_CLARITY = ['Not documented', 'Basic FAQ'];

    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return in_array($assessment->answerValue('return_policy.policy_clarity'), self::WEAK_CLARITY, true);
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Publish a clearer return policy page',
            description: 'Your return policy is not documented in detail. A dedicated, easy-to-find policy page reduces support tickets and pre-purchase hesitation.',
            category: 'return_policy',
            priority: 'medium',
            expectedImpact: 'Fewer "what is your return policy" support tickets and clearer expectations at checkout.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/UndocumentedPolicyRuleTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/UndocumentedPolicyRule.php tests/Unit/Services/Recommendations/Rules/UndocumentedPolicyRuleTest.php
git commit -m "Add UndocumentedPolicyRule"
```

---

## Task 12: NoExchangesOfferedRule

**Files:**
- Create: `app/Services/Recommendations/Rules/NoExchangesOfferedRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/NoExchangesOfferedRuleTest.php`

**Interfaces:**
- Consumes: same as Task 10.
- Produces: `App\Services\Recommendations\Rules\NoExchangesOfferedRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\NoExchangesOfferedRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoExchangesOfferedRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withOffered(bool $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.offered',
            'section' => 'exchanges',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_exchanges_not_offered(): void
    {
        $rule = new NoExchangesOfferedRule();

        $this->assertTrue($rule->applies($this->withOffered(false), $this->emptyScores()));
    }

    public function test_does_not_apply_when_exchanges_offered(): void
    {
        $rule = new NoExchangesOfferedRule();

        $this->assertFalse($rule->applies($this->withOffered(true), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new NoExchangesOfferedRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('exchanges', $draft->category);
        $this->assertSame('high', $draft->priority);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/NoExchangesOfferedRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\NoExchangesOfferedRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class NoExchangesOfferedRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('exchanges.offered') === false;
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Offer exchanges, not just refunds',
            description: 'You do not currently offer exchanges. Exchanges retain revenue that would otherwise be lost to a refund, and customers who just need a different size or color often prefer them.',
            category: 'exchanges',
            priority: 'high',
            expectedImpact: 'Retain revenue currently lost to refund-only returns.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/NoExchangesOfferedRuleTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/NoExchangesOfferedRule.php tests/Unit/Services/Recommendations/Rules/NoExchangesOfferedRuleTest.php
git commit -m "Add NoExchangesOfferedRule"
```

---

## Task 13: NoExchangeIncentivesRule

**Files:**
- Create: `app/Services/Recommendations/Rules/NoExchangeIncentivesRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/NoExchangeIncentivesRuleTest.php`

**Interfaces:**
- Consumes: same as Task 10.
- Produces: `App\Services\Recommendations\Rules\NoExchangeIncentivesRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\NoExchangeIncentivesRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoExchangeIncentivesRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withAnswers(bool $offered, array $incentives): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.offered',
            'section' => 'exchanges',
            'value' => $offered,
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.incentives',
            'section' => 'exchanges',
            'value' => $incentives,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_offered_but_no_incentives(): void
    {
        $rule = new NoExchangeIncentivesRule();

        $this->assertTrue($rule->applies($this->withAnswers(true, []), $this->emptyScores()));
    }

    public function test_does_not_apply_when_incentives_present(): void
    {
        $rule = new NoExchangeIncentivesRule();

        $this->assertFalse($rule->applies($this->withAnswers(true, ['Free shipping']), $this->emptyScores()));
    }

    public function test_does_not_apply_when_exchanges_not_offered(): void
    {
        $rule = new NoExchangeIncentivesRule();

        $this->assertFalse($rule->applies($this->withAnswers(false, []), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new NoExchangeIncentivesRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('exchanges', $draft->category);
        $this->assertSame('low', $draft->priority);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/NoExchangeIncentivesRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\NoExchangeIncentivesRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class NoExchangeIncentivesRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        $incentives = $assessment->answerValue('exchanges.incentives');

        return $assessment->answerValue('exchanges.offered') === true
            && (is_array($incentives) ? $incentives === [] : true);
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Add incentives to encourage exchanges over refunds',
            description: 'You offer exchanges but no incentives, like free shipping or bonus credit, to nudge customers toward an exchange instead of a refund.',
            category: 'exchanges',
            priority: 'low',
            expectedImpact: 'A higher share of returns convert to exchanges instead of refunds.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/NoExchangeIncentivesRuleTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/NoExchangeIncentivesRule.php tests/Unit/Services/Recommendations/Rules/NoExchangeIncentivesRuleTest.php
git commit -m "Add NoExchangeIncentivesRule"
```

---

## Task 14: HighManualHoursRule

**Files:**
- Create: `app/Services/Recommendations/Rules/HighManualHoursRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/HighManualHoursRuleTest.php`

**Interfaces:**
- Consumes: same as Task 10.
- Produces: `App\Services\Recommendations\Rules\HighManualHoursRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\HighManualHoursRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighManualHoursRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withHours(string $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.weekly_hours',
            'section' => 'manual_operations',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_hours_are_21_to_50(): void
    {
        $rule = new HighManualHoursRule();

        $this->assertTrue($rule->applies($this->withHours('21-50'), $this->emptyScores()));
    }

    public function test_applies_when_hours_are_50_plus(): void
    {
        $rule = new HighManualHoursRule();

        $this->assertTrue($rule->applies($this->withHours('50+'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_hours_are_low(): void
    {
        $rule = new HighManualHoursRule();

        $this->assertFalse($rule->applies($this->withHours('Under 5'), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new HighManualHoursRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('manual_operations', $draft->category);
        $this->assertSame('high', $draft->priority);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/HighManualHoursRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\HighManualHoursRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class HighManualHoursRule implements RecommendationRule
{
    private const HIGH_HOURS = ['21-50', '50+'];

    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return in_array($assessment->answerValue('manual_operations.weekly_hours'), self::HIGH_HOURS, true);
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Reduce manual returns processing time',
            description: 'Your team spends significant weekly hours manually processing returns. Automating approvals and label generation typically frees up meaningful staff time.',
            category: 'manual_operations',
            priority: 'high',
            expectedImpact: 'Fewer staff-hours spent per week on manual returns processing.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/HighManualHoursRuleTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/HighManualHoursRule.php tests/Unit/Services/Recommendations/Rules/HighManualHoursRuleTest.php
git commit -m "Add HighManualHoursRule"
```

---

## Task 15: ReturnBottlenecksRule

**Files:**
- Create: `app/Services/Recommendations/Rules/ReturnBottlenecksRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/ReturnBottlenecksRuleTest.php`

**Interfaces:**
- Consumes: same as Task 10.
- Produces: `App\Services\Recommendations\Rules\ReturnBottlenecksRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\ReturnBottlenecksRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnBottlenecksRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withBottlenecks(array $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.common_bottlenecks',
            'section' => 'manual_operations',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_two_or_more_bottlenecks_selected(): void
    {
        $rule = new ReturnBottlenecksRule();

        $this->assertTrue($rule->applies($this->withBottlenecks(['Approvals', 'Labels']), $this->emptyScores()));
    }

    public function test_does_not_apply_when_fewer_than_two_selected(): void
    {
        $rule = new ReturnBottlenecksRule();

        $this->assertFalse($rule->applies($this->withBottlenecks(['Approvals']), $this->emptyScores()));
        $this->assertFalse($rule->applies($this->withBottlenecks([]), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new ReturnBottlenecksRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('manual_operations', $draft->category);
        $this->assertSame('medium', $draft->priority);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/ReturnBottlenecksRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\ReturnBottlenecksRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class ReturnBottlenecksRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        $bottlenecks = $assessment->answerValue('manual_operations.common_bottlenecks');

        return is_array($bottlenecks) && count($bottlenecks) >= 2;
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Address your top manual-operations bottlenecks',
            description: 'You reported multiple recurring bottlenecks in returns processing. Tackling the most frequent ones first, like approvals or label generation, usually gives the fastest relief.',
            category: 'manual_operations',
            priority: 'medium',
            expectedImpact: 'Reduced processing delays across the bottlenecks you flagged.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/ReturnBottlenecksRuleTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/ReturnBottlenecksRule.php tests/Unit/Services/Recommendations/Rules/ReturnBottlenecksRuleTest.php
git commit -m "Add ReturnBottlenecksRule"
```

---

## Task 16: ManualReturnToolingRule

**Files:**
- Create: `app/Services/Recommendations/Rules/ManualReturnToolingRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/ManualReturnToolingRuleTest.php`

**Interfaces:**
- Consumes: same as Task 10.
- Produces: `App\Services\Recommendations\Rules\ManualReturnToolingRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\ManualReturnToolingRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualReturnToolingRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withTools(string $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.return_tools',
            'section' => 'platform',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_tooling_is_email_or_spreadsheets(): void
    {
        $rule = new ManualReturnToolingRule();

        $this->assertTrue($rule->applies($this->withTools('Email/spreadsheets'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_tooling_is_more_mature(): void
    {
        $rule = new ManualReturnToolingRule();

        $this->assertFalse($rule->applies($this->withTools('Returns app'), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new ManualReturnToolingRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('platform', $draft->category);
        $this->assertSame('high', $draft->priority);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/ManualReturnToolingRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\ManualReturnToolingRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class ManualReturnToolingRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('platform.return_tools') === 'Email/spreadsheets';
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Move off email and spreadsheets for returns',
            description: 'Managing returns through email and spreadsheets does not scale and is error-prone. A dedicated returns app or automation gives you tracking, reporting, and fewer manual mistakes.',
            category: 'platform',
            priority: 'high',
            expectedImpact: 'Fewer manual errors and real visibility into return volume and reasons.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/ManualReturnToolingRuleTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/ManualReturnToolingRule.php tests/Unit/Services/Recommendations/Rules/ManualReturnToolingRuleTest.php
git commit -m "Add ManualReturnToolingRule"
```

---

## Task 17: BasicReturnToolingRule

**Files:**
- Create: `app/Services/Recommendations/Rules/BasicReturnToolingRule.php`
- Test: `tests/Unit/Services/Recommendations/Rules/BasicReturnToolingRuleTest.php`

**Interfaces:**
- Consumes: same as Task 10.
- Produces: `App\Services\Recommendations\Rules\BasicReturnToolingRule` implementing `RecommendationRule`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Recommendations\Rules;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\Recommendations\Rules\BasicReturnToolingRule;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasicReturnToolingRuleTest extends TestCase
{
    use RefreshDatabase;

    private function emptyScores(): ScoreBreakdown
    {
        return new ScoreBreakdown(0, 'Foundational', []);
    }

    private function withTools(string $value): Assessment
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.return_tools',
            'section' => 'platform',
            'value' => $value,
        ]);

        return $assessment->fresh(['answers']);
    }

    public function test_applies_when_tooling_is_helpdesk_workflow(): void
    {
        $rule = new BasicReturnToolingRule();

        $this->assertTrue($rule->applies($this->withTools('Helpdesk workflow'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_tooling_is_email_or_spreadsheets(): void
    {
        $rule = new BasicReturnToolingRule();

        $this->assertFalse($rule->applies($this->withTools('Email/spreadsheets'), $this->emptyScores()));
    }

    public function test_does_not_apply_when_tooling_is_more_mature(): void
    {
        $rule = new BasicReturnToolingRule();

        $this->assertFalse($rule->applies($this->withTools('Returns app'), $this->emptyScores()));
    }

    public function test_draft_has_expected_category_and_priority(): void
    {
        $assessment = Assessment::factory()->create();
        $rule = new BasicReturnToolingRule();

        $draft = $rule->draft($assessment, $this->emptyScores());

        $this->assertSame('platform', $draft->category);
        $this->assertSame('medium', $draft->priority);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/BasicReturnToolingRuleTest.php`
Expected: FAIL with `Class "App\Services\Recommendations\Rules\BasicReturnToolingRule" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\Recommendations\Rules;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;

class BasicReturnToolingRule implements RecommendationRule
{
    public function applies(Assessment $assessment, ScoreBreakdown $scores): bool
    {
        return $assessment->answerValue('platform.return_tools') === 'Helpdesk workflow';
    }

    public function draft(Assessment $assessment, ScoreBreakdown $scores): RecommendationDraft
    {
        return new RecommendationDraft(
            title: 'Upgrade to a dedicated returns tool',
            description: 'A general helpdesk workflow works but lacks returns-specific automation, like label generation and return-reason analytics, that a dedicated returns app provides.',
            category: 'platform',
            priority: 'medium',
            expectedImpact: 'Faster return processing and better visibility into return reasons.',
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/Recommendations/Rules/BasicReturnToolingRuleTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recommendations/Rules/BasicReturnToolingRule.php tests/Unit/Services/Recommendations/Rules/BasicReturnToolingRuleTest.php
git commit -m "Add BasicReturnToolingRule"
```

---

## Task 18: Recommendations config and RecommendationEngine

**Files:**
- Create: `config/recommendations.php`
- Create: `app/Services/RecommendationEngine.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Services/RecommendationEngineTest.php`

**Interfaces:**
- Consumes: `App\Contracts\RecommendationRule` (Task 1); all 8 rule classes (Tasks 10-17); `App\Services\Scoring\ScoreBreakdown` (Task 1).
- Produces: `App\Services\RecommendationEngine`, constructor `(array $rules)` (array of `RecommendationRule`), method `generate(Assessment $assessment, ScoreBreakdown $scores): \Illuminate\Support\Collection` returning a `Collection<int, RecommendationDraft>` sorted high-priority-first. Bound as a container singleton resolving all 8 rules from `config('recommendations.rules')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\RecommendationEngine;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_only_recommendations_whose_rules_apply_sorted_by_priority(): void
    {
        $assessment = Assessment::factory()->create();

        // Triggers ManualReturnToolingRule (high) and UndocumentedPolicyRule (medium).
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.return_tools',
            'section' => 'platform',
            'value' => 'Email/spreadsheets',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.policy_clarity',
            'section' => 'return_policy',
            'value' => 'Basic FAQ',
        ]);
        // Does not trigger ShortReturnWindowRule.
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => 'More than 60 days',
        ]);

        $scores = new ScoreBreakdown(0, 'Foundational', []);
        $drafts = app(RecommendationEngine::class)->generate($assessment->fresh(['answers']), $scores);

        $this->assertCount(2, $drafts);
        $this->assertSame('high', $drafts->first()->priority);
        $this->assertSame('platform', $drafts->first()->category);
        $this->assertSame('medium', $drafts->last()->priority);
        $this->assertSame('return_policy', $drafts->last()->category);
    }

    public function test_generates_no_recommendations_when_no_rule_applies(): void
    {
        $assessment = Assessment::factory()->create();
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.window_days',
            'section' => 'return_policy',
            'value' => 'More than 60 days',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'return_policy.policy_clarity',
            'section' => 'return_policy',
            'value' => 'Contextual policy by product/order',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.offered',
            'section' => 'exchanges',
            'value' => true,
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'exchanges.incentives',
            'section' => 'exchanges',
            'value' => ['Free shipping'],
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.weekly_hours',
            'section' => 'manual_operations',
            'value' => 'Under 5',
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'manual_operations.common_bottlenecks',
            'section' => 'manual_operations',
            'value' => [],
        ]);
        AssessmentAnswer::factory()->for($assessment)->create([
            'question_key' => 'platform.return_tools',
            'section' => 'platform',
            'value' => 'Custom automation',
        ]);

        $scores = new ScoreBreakdown(100, 'Advanced', []);
        $drafts = app(RecommendationEngine::class)->generate($assessment->fresh(['answers']), $scores);

        $this->assertCount(0, $drafts);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/RecommendationEngineTest.php`
Expected: FAIL — `Target [App\Services\RecommendationEngine] is not instantiable` (or class-not-found)

- [ ] **Step 3: Create the recommendations config**

`config/recommendations.php`:
```php
<?php

return [
    'rules' => [
        \App\Services\Recommendations\Rules\ShortReturnWindowRule::class,
        \App\Services\Recommendations\Rules\UndocumentedPolicyRule::class,
        \App\Services\Recommendations\Rules\NoExchangesOfferedRule::class,
        \App\Services\Recommendations\Rules\NoExchangeIncentivesRule::class,
        \App\Services\Recommendations\Rules\HighManualHoursRule::class,
        \App\Services\Recommendations\Rules\ReturnBottlenecksRule::class,
        \App\Services\Recommendations\Rules\ManualReturnToolingRule::class,
        \App\Services\Recommendations\Rules\BasicReturnToolingRule::class,
    ],
];
```

- [ ] **Step 4: Create `RecommendationEngine`**

`app/Services/RecommendationEngine.php`:
```php
<?php

namespace App\Services;

use App\Contracts\RecommendationRule;
use App\Models\Assessment;
use App\Services\Recommendations\RecommendationDraft;
use App\Services\Scoring\ScoreBreakdown;
use Illuminate\Support\Collection;

class RecommendationEngine
{
    private const PRIORITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    /**
     * @param RecommendationRule[] $rules
     */
    public function __construct(private readonly array $rules)
    {
    }

    /**
     * @return Collection<int, RecommendationDraft>
     */
    public function generate(Assessment $assessment, ScoreBreakdown $scores): Collection
    {
        return collect($this->rules)
            ->filter(fn (RecommendationRule $rule) => $rule->applies($assessment, $scores))
            ->map(fn (RecommendationRule $rule) => $rule->draft($assessment, $scores))
            ->sortBy(fn (RecommendationDraft $draft) => self::PRIORITY_ORDER[$draft->priority] ?? 99)
            ->values();
    }
}
```

- [ ] **Step 5: Bind `RecommendationEngine` in the service container**

In `app/Providers/AppServiceProvider.php`, add to the `register()` method (below the `AssessmentScorer` binding added in Task 9):

```php
        $this->app->singleton(\App\Services\RecommendationEngine::class, function ($app) {
            $rules = array_map(
                fn (string $class) => $app->make($class),
                config('recommendations.rules'),
            );

            return new \App\Services\RecommendationEngine($rules);
        });
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/RecommendationEngineTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Run the full unit suite so far**

Run: `php artisan test --testsuite=Unit`
Expected: PASS (all tests, including Tasks 1-18)

- [ ] **Step 8: Commit**

```bash
git add config/recommendations.php app/Services/RecommendationEngine.php app/Providers/AppServiceProvider.php tests/Unit/Services/RecommendationEngineTest.php
git commit -m "Add RecommendationEngine aggregating registered RecommendationRules"
```

---

## Task 19: Migration and Assessment model score fields

**Files:**
- Create: `database/migrations/2026_07_09_040000_add_scoring_fields_to_assessments_table.php`
- Modify: `app/Models/Assessment.php`
- Test: `tests/Unit/Models/AssessmentTest.php` (add a test method)

**Interfaces:**
- Produces: `assessments.overall_score` (nullable unsigned tinyint), `assessments.overall_tier` (nullable string), `assessments.section_scores` (nullable json, cast to `array`) — consumed by `SubmitAssessmentService` in Task 20.

- [ ] **Step 1: Write the failing test**

Add this method to `tests/Unit/Models/AssessmentTest.php` (read the file first to place it among the existing test methods):

```php
    public function test_score_fields_are_nullable_and_section_scores_casts_to_array(): void
    {
        $assessment = Assessment::factory()->create();

        $this->assertNull($assessment->overall_score);
        $this->assertNull($assessment->overall_tier);
        $this->assertNull($assessment->section_scores);

        $assessment->forceFill([
            'overall_score' => 72,
            'overall_tier' => 'Established',
            'section_scores' => ['return_policy' => ['score' => 80, 'tier' => 'Established']],
        ])->save();

        $fresh = $assessment->fresh();
        $this->assertSame(72, $fresh->overall_score);
        $this->assertSame('Established', $fresh->overall_tier);
        $this->assertIsArray($fresh->section_scores);
        $this->assertSame(80, $fresh->section_scores['return_policy']['score']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/AssessmentTest.php --filter=score_fields`
Expected: FAIL — `SQLSTATE[HY000]: no such column: overall_score` (or similar)

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_09_040000_add_scoring_fields_to_assessments_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedTinyInteger('overall_score')->nullable()->after('status');
            $table->string('overall_tier')->nullable()->after('overall_score');
            $table->json('section_scores')->nullable()->after('overall_tier');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['overall_score', 'overall_tier', 'section_scores']);
        });
    }
};
```

- [ ] **Step 4: Update the Assessment model**

In `app/Models/Assessment.php`, update the `$fillable` array and `casts()` method:

```php
    protected $fillable = [
        'merchant_id',
        'status',
        'started_at',
        'submitted_at',
        'overall_score',
        'overall_tier',
        'section_scores',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'section_scores' => 'array',
        ];
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/AssessmentTest.php --filter=score_fields`
Expected: PASS (1 test)

- [ ] **Step 6: Run the full test suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS (all existing and new tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_09_040000_add_scoring_fields_to_assessments_table.php app/Models/Assessment.php tests/Unit/Models/AssessmentTest.php
git commit -m "Add overall_score, overall_tier, and section_scores to assessments"
```

---

## Task 20: SubmitAssessmentService

**Files:**
- Create: `app/Services/SubmitAssessmentService.php`
- Test: `tests/Unit/Services/SubmitAssessmentServiceTest.php`

**Interfaces:**
- Consumes: `App\Contracts\AssessmentScorer` (Task 9 binding), `App\Services\RecommendationEngine` (Task 18), `App\Services\AssessmentQuestionCatalog` (existing), `Assessment::answerValue()` (Task 9), `assessments.overall_score/overall_tier/section_scores` (Task 19).
- Produces: `App\Services\SubmitAssessmentService::submit(Assessment $assessment): Assessment` — throws `\Illuminate\Validation\ValidationException` if a required question is unanswered; aborts with HTTP 409 if already submitted; otherwise persists score fields, status, submitted_at, and `Recommendation` rows, and returns the refreshed assessment with `answers` and `recommendations` loaded. Consumed by the controller in Task 21.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Services\SubmitAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SubmitAssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function completeAssessment(): Assessment
    {
        $assessment = Assessment::factory()->create();

        $answers = [
            ['question_key' => 'business.company_name', 'section' => 'business', 'value' => 'Northwind Supply'],
            ['question_key' => 'business.contact_email', 'section' => 'business', 'value' => 'ops@example.com'],
            ['question_key' => 'business.monthly_order_volume', 'section' => 'business', 'value' => '1,000-10,000'],
            ['question_key' => 'catalog.sku_count', 'section' => 'catalog', 'value' => '500-5,000'],
            ['question_key' => 'catalog.fit_sensitive_categories', 'section' => 'catalog', 'value' => []],
            ['question_key' => 'return_policy.window_days', 'section' => 'return_policy', 'value' => 'More than 60 days'], // 100
            ['question_key' => 'return_policy.policy_clarity', 'section' => 'return_policy', 'value' => 'Contextual policy by product/order'], // 100 -> return_policy avg 100
            ['question_key' => 'exchanges.offered', 'section' => 'exchanges', 'value' => true], // 100
            ['question_key' => 'exchanges.incentives', 'section' => 'exchanges', 'value' => ['Bonus credit', 'Free shipping', 'Instant exchange', 'Size recommendations']], // 100 -> exchanges avg 100
            ['question_key' => 'manual_operations.weekly_hours', 'section' => 'manual_operations', 'value' => 'Under 5'], // 100
            ['question_key' => 'manual_operations.common_bottlenecks', 'section' => 'manual_operations', 'value' => []], // 100 -> manual_operations avg 100
            ['question_key' => 'platform.ecommerce_platform', 'section' => 'platform', 'value' => 'Shopify'],
            ['question_key' => 'platform.return_tools', 'section' => 'platform', 'value' => 'Custom automation'], // 100 -> platform avg 100
        ];
        // overall = (100*30 + 100*30 + 100*20 + 100*20) / 100 = 100 (Advanced); no rule triggers -> 0 recommendations

        foreach ($answers as $answer) {
            AssessmentAnswer::factory()->for($assessment)->create($answer);
        }

        return $assessment->fresh(['answers']);
    }

    public function test_submits_a_complete_assessment_and_persists_score_and_recommendations(): void
    {
        $assessment = $this->completeAssessment();

        $result = app(SubmitAssessmentService::class)->submit($assessment);

        $this->assertSame('submitted', $result->status);
        $this->assertNotNull($result->submitted_at);
        $this->assertSame(100, $result->overall_score);
        $this->assertSame('Advanced', $result->overall_tier);
        $this->assertIsArray($result->section_scores);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'submitted']);
        $this->assertCount(0, $result->recommendations);
    }

    public function test_rejects_submission_when_a_required_question_is_unanswered(): void
    {
        $assessment = Assessment::factory()->create();

        $this->expectException(ValidationException::class);

        app(SubmitAssessmentService::class)->submit($assessment);
    }

    public function test_rejects_resubmission_of_an_already_submitted_assessment(): void
    {
        $assessment = $this->completeAssessment();
        app(SubmitAssessmentService::class)->submit($assessment);

        try {
            app(SubmitAssessmentService::class)->submit($assessment->fresh(['answers']));
            $this->fail('Expected an HttpException to be thrown.');
        } catch (HttpException $e) {
            // HttpException::getCode() is not the HTTP status - that's getStatusCode().
            $this->assertSame(409, $e->getStatusCode());
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Services/SubmitAssessmentServiceTest.php`
Expected: FAIL with `Class "App\Services\SubmitAssessmentService" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services;

use App\Contracts\AssessmentScorer;
use App\Models\Assessment;
use Illuminate\Validation\ValidationException;

class SubmitAssessmentService
{
    public function __construct(
        private readonly AssessmentQuestionCatalog $catalog,
        private readonly AssessmentScorer $scorer,
        private readonly RecommendationEngine $recommendations,
    ) {
    }

    public function submit(Assessment $assessment): Assessment
    {
        if ($assessment->status === 'submitted') {
            abort(409, 'This assessment has already been submitted.');
        }

        $assessment->loadMissing('answers');

        $missing = $this->catalog->questions()
            ->filter(fn (array $question) => $question['required'] ?? false)
            ->reject(fn (array $question) => $this->isAnswered($assessment, $question['key']))
            ->pluck('key');

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(
                $missing->mapWithKeys(fn (string $key) => [$key => ['This question is required.']])->all()
            );
        }

        $scores = $this->scorer->score($assessment);

        $this->recommendations->generate($assessment, $scores)->each(
            fn ($draft) => $assessment->recommendations()->create([
                'title' => $draft->title,
                'description' => $draft->description,
                'category' => $draft->category,
                'priority' => $draft->priority,
                'expected_impact' => $draft->expectedImpact,
            ])
        );

        $assessment->forceFill([
            'overall_score' => $scores->overallScore,
            'overall_tier' => $scores->overallTier,
            'section_scores' => $scores->sections,
            'status' => 'submitted',
            'submitted_at' => now(),
        ])->save();

        return $assessment->fresh(['answers', 'recommendations']);
    }

    private function isAnswered(Assessment $assessment, string $questionKey): bool
    {
        $value = $assessment->answerValue($questionKey);

        return $value !== null && $value !== '' && $value !== [];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Services/SubmitAssessmentServiceTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SubmitAssessmentService.php tests/Unit/Services/SubmitAssessmentServiceTest.php
git commit -m "Add SubmitAssessmentService"
```

---

## Task 21: Submit endpoint and feature tests

**Files:**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/AssessmentController.php`
- Test: `tests/Feature/AssessmentSubmissionTest.php`

**Interfaces:**
- Consumes: `App\Services\SubmitAssessmentService::submit()` (Task 20).
- Produces: `POST /api/assessments/{assessment}/submit` returning `200` with `{assessment: {id, status, overall_score, overall_tier, section_scores}, recommendations: [{title, description, category, priority, expected_impact}, ...]}` on success; `422` with an `errors` object when incomplete; `409` when already submitted.

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function completeAssessment(): Assessment
    {
        $assessment = Assessment::factory()->create();

        $answers = [
            ['question_key' => 'business.company_name', 'section' => 'business', 'value' => 'Northwind Supply'],
            ['question_key' => 'business.contact_email', 'section' => 'business', 'value' => 'ops@example.com'],
            ['question_key' => 'business.monthly_order_volume', 'section' => 'business', 'value' => '1,000-10,000'],
            ['question_key' => 'catalog.sku_count', 'section' => 'catalog', 'value' => '500-5,000'],
            ['question_key' => 'catalog.fit_sensitive_categories', 'section' => 'catalog', 'value' => []],
            ['question_key' => 'return_policy.window_days', 'section' => 'return_policy', 'value' => '14 days or less'], // 0
            ['question_key' => 'return_policy.policy_clarity', 'section' => 'return_policy', 'value' => 'Not documented'], // 0 -> return_policy avg 0
            ['question_key' => 'exchanges.offered', 'section' => 'exchanges', 'value' => false], // 0
            ['question_key' => 'exchanges.incentives', 'section' => 'exchanges', 'value' => []], // 0 -> exchanges avg 0
            ['question_key' => 'manual_operations.weekly_hours', 'section' => 'manual_operations', 'value' => '50+'], // 0
            ['question_key' => 'manual_operations.common_bottlenecks', 'section' => 'manual_operations', 'value' => ['Approvals', 'Labels']], // 100-(2/5)*100=60 -> manual_operations avg 30
            ['question_key' => 'platform.ecommerce_platform', 'section' => 'platform', 'value' => 'Shopify'],
            ['question_key' => 'platform.return_tools', 'section' => 'platform', 'value' => 'Email/spreadsheets'], // 0 -> platform avg 0
        ];
        // overall = (0*30 + 30*30 + 0*20 + 0*20) / 100 = 9 (Foundational)
        // triggered rules: ShortReturnWindow, UndocumentedPolicy, NoExchangesOffered, HighManualHours, ReturnBottlenecks, ManualReturnTooling = 6

        foreach ($answers as $answer) {
            AssessmentAnswer::factory()->for($assessment)->create($answer);
        }

        return $assessment;
    }

    public function test_submitting_a_complete_assessment_returns_score_and_recommendations(): void
    {
        $assessment = $this->completeAssessment();

        $response = $this->postJson("/api/assessments/{$assessment->id}/submit");

        $response->assertOk()
            ->assertJsonPath('assessment.status', 'submitted')
            ->assertJsonPath('assessment.overall_score', 9)
            ->assertJsonPath('assessment.overall_tier', 'Foundational');

        $response->assertJsonCount(6, 'recommendations');
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'submitted']);
        $this->assertDatabaseCount('recommendations', 6);
    }

    public function test_submitting_an_incomplete_assessment_is_rejected(): void
    {
        $assessment = Assessment::factory()->create();

        $response = $this->postJson("/api/assessments/{$assessment->id}/submit");

        $response->assertUnprocessable();
        $response->assertJsonStructure(['errors']);
        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'status' => 'draft']);
    }

    public function test_submitting_an_already_submitted_assessment_is_rejected(): void
    {
        $assessment = $this->completeAssessment();
        $this->postJson("/api/assessments/{$assessment->id}/submit")->assertOk();

        $response = $this->postJson("/api/assessments/{$assessment->id}/submit");

        $response->assertStatus(409);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AssessmentSubmissionTest.php`
Expected: FAIL with a 404 (`Route [...] not found` or similar) since the route does not exist yet

- [ ] **Step 3: Add the route**

In `routes/api.php`, add below the existing `answers` route:

```php
Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit']);
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/AssessmentController.php`, add the `submit` method (and the two new imports) to the existing class:

```php
use App\Services\SubmitAssessmentService;
```

```php
    public function submit(Assessment $assessment, SubmitAssessmentService $service): JsonResponse
    {
        $assessment = $service->submit($assessment);

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'overall_score' => $assessment->overall_score,
                'overall_tier' => $assessment->overall_tier,
                'section_scores' => $assessment->section_scores,
            ],
            'recommendations' => $assessment->recommendations->map(fn ($recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'category' => $recommendation->category,
                'priority' => $recommendation->priority,
                'expected_impact' => $recommendation->expected_impact,
            ]),
        ]);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/AssessmentSubmissionTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Run the full test suite**

Run: `php artisan test`
Expected: PASS (all tests)

- [ ] **Step 7: Commit**

```bash
git add routes/api.php app/Http/Controllers/AssessmentController.php tests/Feature/AssessmentSubmissionTest.php
git commit -m "Add POST /api/assessments/{assessment}/submit endpoint"
```

---

## Task 22: Minimal wizard submit UI

**Files:**
- Modify: `resources/js/Pages/Assessment/Wizard.vue`

**Interfaces:**
- Consumes: `POST /api/assessments/{assessment}/submit` (Task 21) response shape `{assessment: {status, overall_score, overall_tier, section_scores}, recommendations: [...]}`.

- [ ] **Step 1: Add submission state and a `submitAssessment()` function**

In `resources/js/Pages/Assessment/Wizard.vue`, inside `<script setup>`, add below the existing `errors` ref:

```js
const submitResult = ref(null);
const submitError = ref(null);
```

Add this function below `saveSection()`:

```js
async function submitAssessment() {
    submitError.value = null;

    try {
        const response = await axios.post(`/api/assessments/${assessmentId.value}/submit`);
        submitResult.value = response.data;
    } catch (error) {
        if (error.response?.status === 409) {
            submitError.value = 'This assessment has already been submitted.';
        } else {
            errors.value = error.response?.data?.errors ?? {};
            submitError.value = 'Check the highlighted answers before submitting.';
        }
    }
}
```

- [ ] **Step 2: Add the "Submit assessment" button and result block to the template**

In the template, replace the closing of the `<form>` block (the `</form>` line and the content immediately after it, up to the closing `</section>`) with:

```html
            </form>

            <div v-if="isLastSection" class="mt-6 flex justify-end">
                <button
                    type="button"
                    class="rounded-xl border border-blue-300/40 bg-blue-500/10 px-5 py-3 text-sm font-semibold text-blue-100 transition hover:bg-blue-500/20"
                    @click="submitAssessment"
                >
                    Submit assessment
                </button>
            </div>

            <p v-if="submitError" class="mt-3 text-right text-sm text-red-300">{{ submitError }}</p>

            <div v-if="submitResult" class="mt-8 rounded-3xl border border-white/10 bg-white/5 p-6">
                <h2 class="text-xl font-semibold">Assessment submitted</h2>
                <p class="mt-2 text-slate-200">
                    Overall score: {{ submitResult.assessment.overall_score }}/100 ({{ submitResult.assessment.overall_tier }})
                </p>
                <ul class="mt-4 space-y-1 text-sm text-slate-300">
                    <li v-for="(section, key) in submitResult.assessment.section_scores" :key="key">
                        {{ key }}: {{ section.score }}/100 ({{ section.tier }})
                    </li>
                </ul>
                <div class="mt-6 space-y-4">
                    <div v-for="(recommendation, index) in submitResult.recommendations" :key="index" class="rounded-2xl border border-white/10 p-4">
                        <p class="text-xs uppercase tracking-wide text-blue-200">{{ recommendation.category }} - {{ recommendation.priority }}</p>
                        <h3 class="mt-1 font-semibold">{{ recommendation.title }}</h3>
                        <p class="mt-1 text-sm text-slate-300">{{ recommendation.description }}</p>
                        <p class="mt-2 text-sm text-slate-400">Expected impact: {{ recommendation.expected_impact }}</p>
                    </div>
                </div>
            </div>
        </section>
```

- [ ] **Step 3: Manually verify in the browser**

Herd already serves the app at `http://merchant-readiness-workspace.test`. Steps:
1. Navigate to `http://merchant-readiness-workspace.test/assessment`.
2. Fill in and save every section through to Platform (last section), using answers that trigger a mix of weak and strong signals (e.g. a 14-day return window, "Not documented" policy, exchanges not offered, 50+ weekly hours, 2+ bottlenecks, "Email/spreadsheets" tooling) to exercise multiple recommendation rules.
3. Click "Submit assessment" and confirm the plain result block appears with a low overall score, "Foundational" tier, and multiple recommendation cards with sensible titles/descriptions.
4. Click "Submit assessment" again and confirm `submitError` shows the "already been submitted" message (409).
5. Take a screenshot of the result block and review the recommendation copy for quality — this is the Milestone 3 STOP gate ("review recommendation quality").

Expected: submission succeeds once, shows a score/tier/recommendation breakdown consistent with the weak answers given, and a second submit attempt is rejected with a visible message.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Assessment/Wizard.vue
git commit -m "Add minimal submit UI to the assessment wizard"
```

---

## Final verification

- [ ] Run `php artisan test` and confirm the entire suite (Milestone 1, 2, and 3 tests) passes.
- [ ] Manually verify Task 22's browser steps if not already done.
- [ ] Confirm CI is green on the branch before merging, per the "Deployment must be proven before feature development continues" guardrail in CLAUDE.md.
