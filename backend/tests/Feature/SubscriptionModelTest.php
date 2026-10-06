<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_is_active_through_its_end_date(): void
    {
        $sub = Subscription::factory()->endingIn(0)->create();

        $this->assertTrue($sub->isCurrentlyActive());
        $this->assertSame(1, Subscription::currentlyActive()->count());
    }

    public function test_subscription_past_end_date_is_not_active_even_if_status_not_updated(): void
    {
        $sub = Subscription::factory()->endingIn(-1)->create(['status' => SubscriptionStatus::Active]);

        $this->assertFalse($sub->isCurrentlyActive());
        $this->assertSame(0, Subscription::currentlyActive()->count());
    }

    public function test_expired_status_is_not_active(): void
    {
        $sub = Subscription::factory()->expired()->endingIn(5)->create();

        $this->assertFalse($sub->isCurrentlyActive());
    }
}
