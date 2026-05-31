<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseReportStatus;
use App\Enums\ExpenseTripType;
use App\Models\CommutePattern;
use App\Models\CommuterPass;
use App\Models\EmploymentTerm;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\ScheduleLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateMonthlyExpensesByPatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_pattern_page_renders(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->get(route('expenses.pattern', ['new' => 1]))
            ->assertOk()
            ->assertViewIs('expenses.pattern')
            ->assertSee('Commuting Pattern')
            ->assertSee('window.COMMUTE_PATTERN_BOOTSTRAP', false);
    }

    public function test_it_generates_pattern_rows_for_valid_weekdays_and_blank_rows_otherwise(): void
    {
        $user = User::factory()->general()->create([
            'family_name' => 'Pattern',
            'first_name' => 'Teacher',
        ]);

        EmploymentTerm::create([
            'user_id' => $user->id,
            'start_date' => '2026-05-01',
            'end_date' => null,
            'type_name' => 'Employee',
            'type_code' => 'employee',
        ]);

        ScheduleLine::create([
            'user_id' => $user->id,
            'dow' => 1,
            'school_name' => 'School',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'effective_start' => '2026-05-01',
            'effective_end' => '2026-05-31',
            'total_minutes' => 480,
        ]);

        ScheduleLine::create([
            'user_id' => $user->id,
            'dow' => 3,
            'school_name' => 'School',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'effective_start' => '2026-05-01',
            'effective_end' => '2026-05-31',
            'total_minutes' => 480,
        ]);

        CommuterPass::create([
            'user_id' => $user->id,
            'date_from' => '2026-05-06',
            'date_to' => '2026-05-06',
            'station_from' => 'Home',
            'station_to' => 'School',
            'cost' => 12000,
        ]);

        $pattern = CommutePattern::create([
            'user_id' => $user->id,
            'submitted_at' => now('Asia/Tokyo'),
            'closest_station' => 'Home',
            'train_line' => 'Local',
            'valid_from' => '2026-05-01',
            'valid_to' => '2026-05-31',
            'reason' => null,
        ]);

        $pattern->legs()->create([
            'dow' => 'Mon',
            'seq' => 100,
            'station_from' => 'Home',
            'station_to' => 'School',
            'cost' => 500,
            'trip_type' => ExpenseTripType::ROUND_TRIP->value,
            'note' => 'main',
        ]);

        $pattern->legs()->create([
            'dow' => 'Mon',
            'seq' => 200,
            'station_from' => 'School',
            'station_to' => 'Office',
            'cost' => 300,
            'trip_type' => ExpenseTripType::ONE_WAY->value,
            'note' => 'extra',
        ]);

        $pattern->legs()->create([
            'dow' => 'Wed',
            'seq' => 100,
            'station_from' => 'Home',
            'station_to' => 'School',
            'cost' => 700,
            'trip_type' => ExpenseTripType::ROUND_TRIP->value,
            'note' => 'pass-covered',
        ]);

        $this->artisan('expenses:generate-monthly-by-pattern', [
            'year' => 2026,
            'month' => 5,
        ])->assertSuccessful();

        $report = ExpenseReport::query()
            ->where('user_id', $user->id)
            ->where('year', 2026)
            ->where('month', 5)
            ->firstOrFail();

        $this->assertSame(ExpenseReportStatus::DRAFT, $report->status);

        $mondayRows = Expense::query()
            ->where('expense_report_id', $report->id)
            ->whereDate('expense_date', '2026-05-04')
            ->orderBy('seq')
            ->get();

        $this->assertCount(2, $mondayRows);
        $this->assertSame(['Home', 'School', 500, ExpenseTripType::ROUND_TRIP->value], [
            $mondayRows[0]->station_from,
            $mondayRows[0]->station_to,
            $mondayRows[0]->cost,
            $mondayRows[0]->trip_type->value,
        ]);
        $this->assertSame(['School', 'Office', 300, ExpenseTripType::ONE_WAY->value], [
            $mondayRows[1]->station_from,
            $mondayRows[1]->station_to,
            $mondayRows[1]->cost,
            $mondayRows[1]->trip_type->value,
        ]);

        $blankTuesday = Expense::query()
            ->where('expense_report_id', $report->id)
            ->whereDate('expense_date', '2026-05-05')
            ->firstOrFail();

        $this->assertNull($blankTuesday->station_from);
        $this->assertNull($blankTuesday->station_to);
        $this->assertSame(0, $blankTuesday->cost);
        $this->assertSame(ExpenseTripType::ROUND_TRIP, $blankTuesday->trip_type);
        $this->assertSame(ExpenseCategory::REGULAR, $blankTuesday->category);

        $blankPassDay = Expense::query()
            ->where('expense_report_id', $report->id)
            ->whereDate('expense_date', '2026-05-06')
            ->firstOrFail();

        $this->assertNull($blankPassDay->station_from);
        $this->assertNull($blankPassDay->station_to);
        $this->assertSame(0, $blankPassDay->cost);
    }

    public function test_pattern_api_creates_updates_syncs_and_deletes_pattern(): void
    {
        $user = User::factory()->general()->create();

        $createResponse = $this->actingAs($user)->putJson(route('api.commute_patterns.batch'), [
            'user_id' => $user->id,
            'closest_station' => 'Home',
            'train_line' => 'Local',
            'valid_from' => '2026-04-01',
            'valid_to' => '2027-03-31',
            'reason' => 'initial',
            'rows' => [
                [
                    'dow' => 'Sun',
                    'seq' => 100,
                    'station_from' => 'Home',
                    'station_to' => 'School',
                    'cost' => 400,
                    'trip_type' => ExpenseTripType::ROUND_TRIP->value,
                    'note' => null,
                ],
                [
                    'dow' => 'Mon',
                    'seq' => 100,
                    'station_from' => 'Home',
                    'station_to' => 'Office',
                    'cost' => 500,
                    'trip_type' => ExpenseTripType::ONE_WAY->value,
                    'note' => 'temporary',
                ],
            ],
        ]);

        $createResponse->assertOk()->assertJson(['ok' => true]);

        $pattern = CommutePattern::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertCount(2, $pattern->legs);

        $sunLeg = $pattern->legs()->where('dow', 'Sun')->firstOrFail();

        $user->assignRole('admin');

        $this->actingAs($user)->putJson(route('api.commute_patterns.batch'), [
            'pattern_id' => $pattern->id,
            'user_id' => $user->id,
            'closest_station' => 'Updated Home',
            'train_line' => null,
            'valid_from' => '2026-04-01',
            'valid_to' => '2027-03-31',
            'reason' => 'updated',
            'rows' => [
                [
                    'id' => $sunLeg->id,
                    'dow' => 'Sun',
                    'seq' => 100,
                    'station_from' => 'Updated Home',
                    'station_to' => 'School',
                    'cost' => 450,
                    'trip_type' => ExpenseTripType::ROUND_TRIP->value,
                    'note' => 'kept',
                ],
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $pattern->refresh();
        $this->assertSame('Updated Home', $pattern->closest_station);
        $this->assertCount(1, $pattern->legs);
        $this->assertSame(450, $pattern->legs()->firstOrFail()->cost);

        $this->actingAs($user)
            ->deleteJson(route('api.commute_patterns.destroy', $pattern))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('commute_patterns', ['id' => $pattern->id]);
        $this->assertDatabaseMissing('commute_pattern_legs', ['commute_pattern_id' => $pattern->id]);
    }
}
