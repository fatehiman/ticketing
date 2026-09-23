<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;

/**
 * Only the admin account is always created. Demo data (developers, customers,
 * projects, tickets) is added when SEED_DEMO=true or in the local environment.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_PASSWORD', 'password');

        $admin = User::firstOrCreate(['email' => 'admin@ticketing.local'], [
            'first_name' => 'مدیر', 'last_name' => 'سیستم', 'mobile' => '09120000000',
            'password' => $password, 'role' => Role::Admin->value,
        ]);

        if (! env('SEED_DEMO', app()->isLocal()) || Project::exists()) {
            return;
        }

        $dev1 = User::create(['first_name' => 'علی', 'last_name' => 'رضایی', 'email' => 'dev1@ticketing.local', 'mobile' => '09120000001',
            'password' => $password, 'role' => Role::Developer->value, 'created_by' => $admin->id]);
        $dev2 = User::create(['first_name' => 'Sara', 'last_name' => 'Karimi', 'email' => 'dev2@ticketing.local', 'mobile' => '09120000002',
            'password' => $password, 'role' => Role::Developer->value, 'locale' => 'en', 'calendar' => 'gregorian', 'created_by' => $admin->id]);
        $cust1 = User::create(['first_name' => 'مریم', 'last_name' => 'احمدی', 'email' => 'customer1@ticketing.local', 'mobile' => '09120000011',
            'password' => $password, 'role' => Role::Customer->value, 'created_by' => $dev1->id]);
        $cust2 = User::create(['first_name' => 'رضا', 'last_name' => 'محمدی', 'email' => 'customer2@ticketing.local', 'mobile' => '09120000012',
            'password' => $password, 'role' => Role::Customer->value, 'created_by' => $dev1->id]);
        $cust3 = User::create(['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'customer3@ticketing.local', 'mobile' => '09120000013',
            'password' => $password, 'role' => Role::Customer->value, 'locale' => 'en', 'calendar' => 'gregorian', 'created_by' => $dev2->id]);

        $shop = Project::create([
            'code' => 'SHOP', 'name' => 'فروشگاه آنلاین', 'description' => 'طراحی و توسعه فروشگاه اینترنتی',
            'start_date' => now()->subMonths(3), 'end_date' => now()->addMonths(3), 'budget' => 450000000, 'currency' => 'IRT',
            'phone1' => '02188776655', 'email' => 'info@shop.example', 'website' => 'https://shop.example', 'contact_person' => 'مریم احمدی',
            'created_by' => $admin->id,
        ]);
        $crm = Project::create([
            'code' => 'CRM', 'name' => 'سامانه CRM', 'description' => 'مدیریت ارتباط با مشتریان',
            'start_date' => now()->subMonth(), 'end_date' => now()->addMonths(5), 'budget' => 300000000, 'currency' => 'IRT',
            'created_by' => $admin->id,
        ]);
        $app = Project::create([
            'code' => 'MOBILE', 'name' => 'Mobile App', 'description' => 'iOS and Android app',
            'start_date' => now()->subMonths(2), 'end_date' => now()->addMonths(4), 'budget' => 25000, 'currency' => 'USD',
            'created_by' => $admin->id,
        ]);

        $shop->members()->attach([$dev1->id, $cust1->id, $cust2->id]);
        $crm->members()->attach([$dev1->id, $dev2->id, $cust1->id]);
        $app->members()->attach([$dev2->id, $cust3->id]);

        foreach ([$shop, $crm, $app] as $project) {
            Sprint::create(['project_id' => $project->id, 'number' => 1, 'name' => 'MVP', 'status' => 'closed',
                'start_date' => now()->subWeeks(4), 'end_date' => now()->subWeeks(2)]);
            Sprint::create(['project_id' => $project->id, 'number' => 2, 'status' => 'active',
                'start_date' => now()->subWeeks(2), 'end_date' => now()->addDays(3)]);
            Sprint::create(['project_id' => $project->id, 'number' => 3, 'status' => 'planned',
                'start_date' => now()->addDays(4), 'end_date' => now()->addWeeks(3)]);
        }

        $service = app(TicketService::class);
        $titles = [
            'صفحه پرداخت روی موبایل درست نمایش داده نمی‌شود', 'افزودن فیلتر قیمت به لیست محصولات', 'گزارش فروش ماهانه',
            'خطای ۵۰۰ هنگام ثبت سفارش', 'بهبود سرعت بارگذاری صفحه اصلی', 'امکان ورود با کد یکبار مصرف',
            'Export customers to Excel', 'Push notifications for new orders', 'Dark mode support',
            'تغییر رنگ دکمه‌ها مطابق هویت بصری', 'اتصال به درگاه پرداخت جدید', 'Crash on Android 14 when opening camera',
        ];
        $statuses = TicketStatus::cases();
        $priorities = ['highest', 'high', 'medium', 'medium', 'low', 'lowest'];
        $types = ['task', 'bug', 'feature', 'improvement', 'support'];
        $points = [1, 2, 3, 5, 8, 13];

        foreach (range(1, 36) as $i) {
            $project = [$shop, $crm, $app][$i % 3];
            $members = $project->members()->get();
            $customer = $members->firstWhere('role', Role::Customer);
            $developer = $members->firstWhere('role', Role::Developer);
            $byCustomer = $customer && $i % 4 === 0;
            $status = $byCustomer ? TicketStatus::PendingReview : $statuses[$i % count($statuses)];
            if (! $byCustomer && $status === TicketStatus::PendingReview) {
                $status = TicketStatus::Backlog;
            }
            $sp = $points[$i % count($points)];

            $ticket = $service->create([
                'project_id' => $project->id,
                'sprint_id' => $byCustomer ? null : $project->sprints()->where('number', 1 + $i % 3)->value('id'),
                'type' => $types[$i % count($types)],
                'status' => $status->value,
                'priority' => $priorities[$i % count($priorities)],
                'title' => $titles[$i % count($titles)],
                'content' => '<p>'.$titles[$i % count($titles)].'</p><ul><li>مرحله ۱: بررسی</li><li>مرحله ۲: پیاده‌سازی</li></ul>',
                'assignee_id' => $byCustomer ? null : $developer?->id,
                'story_points' => $byCustomer ? null : $sp,
                'done_story_points' => $status === TicketStatus::Done ? $sp : null,
                'estimated_minutes' => $byCustomer ? null : $sp * 90,
                'logged_minutes' => in_array($status, [TicketStatus::Done, TicketStatus::Testing, TicketStatus::InProgress]) ? $sp * 75 : null,
                'estimated_cost' => $byCustomer ? null : $sp * ($project->currency === 'USD' ? 120 : 2500000),
                'cost' => $status === TicketStatus::Done ? $sp * ($project->currency === 'USD' ? 110 : 2300000) : null,
                'due_date' => now()->addDays(($i % 20) - 6)->toDateString(),
            ], $byCustomer ? $customer : ($developer ?? $admin));

            $ticket->timestamps = false;
            $ticket->forceFill(['created_at' => now()->subDays(40 - $i), 'updated_at' => now()->subDays(max(0, 20 - $i))])->saveQuietly();
        }

        // Customer payments (shown on the transactions page next to the costs of done tickets).
        foreach ([
            [$cust1, $shop, 5000000, 30, 'پیش‌پرداخت فاز اول'],
            [$cust1, $shop, 3500000, 12, 'پرداخت قسط دوم'],
            [$cust1, $crm, 4000000, 8, 'پیش‌پرداخت CRM'],
            [$cust1, null, 1000000, 3, 'پرداخت علی‌الحساب'],
            [$cust2, $shop, 2000000, 5, null],
            [$cust3, $app, 800, 20, 'Advance payment'],
        ] as [$customer, $project, $amount, $daysAgo, $description]) {
            Payment::create([
                'customer_id' => $customer->id, 'project_id' => $project?->id, 'amount' => $amount,
                'paid_on' => now()->subDays($daysAgo)->toDateString(), 'description' => $description,
                'created_by' => $project?->developers()->value('users.id') ?? $dev1->id,
            ]);
        }
    }
}
