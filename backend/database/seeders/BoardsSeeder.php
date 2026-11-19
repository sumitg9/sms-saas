<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BoardsSeeder extends Seeder
{
    public function run(): void
    {
        $boards = [
            [
                'code' => 'CBSE',
                'name' => 'Central Board of Secondary Education',
                'description' => 'National-level board of education in India for public and private schools, controlled and managed by the Government of India.',
            ],
            [
                'code' => 'ICSE',
                'name' => 'Indian Certificate of Secondary Education (CISCE)',
                'description' => 'Private national level board of school education in India conducting the ICSE and ISC examinations.',
            ],
            [
                'code' => 'STATE_BOARD',
                'name' => 'State Secondary Education Board',
                'description' => 'Individual state-level examination boards across Indian states (e.g. Maharashtra, Karnataka, Tamil Nadu).',
            ],
            [
                'code' => 'CAMBRIDGE',
                'name' => 'Cambridge Assessment International Education (CAIE)',
                'description' => 'International qualification provider offering IGCSE and A Levels.',
            ],
            [
                'code' => 'IB',
                'name' => 'International Baccalaureate',
                'description' => 'Non-profit educational foundation offering the IB Primary Years, Middle Years, and Diploma Programmes.',
            ],
        ];

        foreach ($boards as $board) {
            $existing = Board::whereNull('tenant_id')->where('code', $board['code'])->first();
            if ($existing) {
                $existing->update([
                    'name' => $board['name'],
                    'description' => $board['description'],
                    'is_active' => true,
                ]);
            } else {
                Board::create([
                    'tenant_id' => null,
                    'code' => $board['code'],
                    'name' => $board['name'],
                    'description' => $board['description'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
