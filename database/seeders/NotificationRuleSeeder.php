<?php

namespace Database\Seeders;

use App\Models\NotificationRule;
use Illuminate\Database\Seeder;

class NotificationRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // offset_days negativo = antes del vencimiento
        // offset_days positivo = después del vencimiento
        $rules = [
            [
                'event'             => 'rental_due',
                'channel'           => 'email',
                'offset_days'       => -6,     // 6 días antes de que venza la renta
                'repeat_every_days' => 2,
                'max_repeats'       => 3,
                'template_key'      => 'rental_due_reminder',
            ],
            [
                'event'             => 'rental_overdue',
                'channel'           => 'email',
                'offset_days'       => 1,      // al día siguiente del vencimiento
                'repeat_every_days' => 3,
                'max_repeats'       => 5,
                'template_key'      => 'rental_overdue_notice',
            ],
            [
                'event'             => 'invoice_sent',
                'channel'           => 'email',
                'offset_days'       => 0,      // en el momento
                'repeat_every_days' => null,
                'max_repeats'       => null,
                'template_key'      => 'invoice_sent',
            ],
            [
                'event'             => 'certificate_expiring',
                'channel'           => 'email',
                'offset_days'       => -30,    // 30 días antes de que venza
                'repeat_every_days' => 7,
                'max_repeats'       => 4,
                'template_key'      => 'certificate_expiring',
            ],
            [
                'event'             => 'release_deadline',
                'channel'           => 'email',
                'offset_days'       => -3,     // 3 días antes de que se venza el plazo de retiro
                'repeat_every_days' => 1,
                'max_repeats'       => 3,
                'template_key'      => 'release_deadline_warning',
            ],
            [
                'event'             => 'storage_charge',
                'channel'           => 'email',
                'offset_days'       => 0,
                'repeat_every_days' => null,
                'max_repeats'       => null,
                'template_key'      => 'storage_charge_notice',
            ],
        ];

        foreach ($rules as $rule) {
            NotificationRule::updateOrCreate(
                ['company_id' => null, 'event' => $rule['event']],
                array_merge($rule, ['is_active' => true]),
            );
        }
    }
}
