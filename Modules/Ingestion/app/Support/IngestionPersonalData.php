<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support;

use App\Contracts\PersonalDataStore;
use Illuminate\Support\Facades\DB;

final class IngestionPersonalData implements PersonalDataStore
{
    private const COLUMNS = ['sales_records' => 'customer_phone', 'payment_records' => 'payer_phone'];

    private const JSON = [
        'quarantined_rows' => ['column' => 'raw', 'date' => 'business_date'],
        'mock_source_rows' => ['column' => 'payload', 'date' => 'record_date'],
    ];

    private const JSON_KEYS = ['customer_phone', 'payer_phone'];

    public function anonymiseBefore(string $date, string $replacement): array
    {
        return DB::transaction(function () use ($date, $replacement): array {
            $counts = [];
            foreach (self::COLUMNS as $table => $column) {
                $counts[$table] = DB::table($table)->where('business_date', '<', $date)->where($column, '<>', $replacement)->whereNotNull($column)->update([$column => $replacement]);
            }
            foreach (self::JSON as $table => $spec) {
                $counts[$table] = 0;
                foreach (self::JSON_KEYS as $key) {
                    $counts[$table] += DB::table($table)->where($spec['date'], '<', $date)
                        ->whereRaw("jsonb_exists({$spec['column']}, ?)", [$key])->whereRaw("{$spec['column']}->>? <> ?", [$key, $replacement])
                        ->update([$spec['column'] => DB::raw("{$spec['column']} || jsonb_build_object('{$key}', ".DB::getPdo()->quote($replacement).')')]);
                }
            }
            $counts['upload_staging'] = DB::table('upload_staging')->where('business_date', '<', $date)->whereNotNull('rows')->update(['rows' => null]);

            return $counts;
        });
    }

    public function anonymiseSubject(array $phoneVariants, string $replacement): array
    {
        return DB::transaction(function () use ($phoneVariants, $replacement): array {
            $counts = [];
            foreach (self::COLUMNS as $table => $column) {
                $counts[$table] = DB::table($table)->whereIn($column, $phoneVariants)->update([$column => $replacement]);
            }
            foreach (self::JSON as $table => $spec) {
                $counts[$table] = 0;
                foreach (self::JSON_KEYS as $key) {
                    $counts[$table] += DB::table($table)->whereIn(DB::raw("{$spec['column']}->>'{$key}'"), $phoneVariants)
                        ->update([$spec['column'] => DB::raw("{$spec['column']} || jsonb_build_object('{$key}', ".DB::getPdo()->quote($replacement).')')]);
                }
            }
            $counts['upload_staging'] = 0;
            foreach ($phoneVariants as $variant) {
                $counts['upload_staging'] += DB::table('upload_staging')->whereNotNull('rows')->whereRaw('rows::text like ?', ['%"'.$variant.'"%'])->update(['rows' => null]);
            }

            return $counts;
        });
    }
}
