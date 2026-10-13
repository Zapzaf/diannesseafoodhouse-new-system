<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Productions created from a delivery now start on that delivery's
     * Delivery Date — bring the existing ones in line. A finished
     * production is skipped when its delivery date would land after its
     * finish date, so no order ends up finishing before it started.
     */
    public function up(): void
    {
        $rows = DB::table('production_orders')
            ->join('production_inputs', 'production_inputs.production_order_id', '=', 'production_orders.id')
            ->join('delivery_items', 'delivery_items.id', '=', 'production_inputs.delivery_item_id')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->whereNotNull('deliveries.delivery_date')
            ->select('production_orders.id', 'production_orders.finished_at', DB::raw('MIN(deliveries.delivery_date) as delivery_date'))
            ->groupBy('production_orders.id', 'production_orders.finished_at')
            ->get();

        foreach ($rows as $row) {
            $deliveryDate = substr((string) $row->delivery_date, 0, 10);

            if ($row->finished_at && $deliveryDate > substr((string) $row->finished_at, 0, 10)) {
                continue;
            }

            DB::table('production_orders')->where('id', $row->id)->update(['production_date' => $deliveryDate]);
        }
    }

    public function down(): void
    {
        // Production dates are user-editable data now; nothing to undo.
    }
};
