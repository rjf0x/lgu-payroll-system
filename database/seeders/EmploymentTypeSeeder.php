<?php

namespace Database\Seeders;

use App\Models\EmploymentType;
use Illuminate\Database\Seeder;

class EmploymentTypeSeeder extends Seeder
{
    /**
     * Seed the employment classifications supported by the system.
     */
    public function run(): void
    {
        $types = [
            EmploymentType::PERMANENT => 'Permanent appointment with salary grade and step.',
            EmploymentType::COTERMINOUS => 'Coterminous appointment tied to the appointing authority.',
            EmploymentType::ELECTIVE => 'Elective position filled through election.',
        ];

        foreach ($types as $name => $description) {
            EmploymentType::updateOrCreate(
                ['employment_type_name' => $name],
                ['description' => $description, 'status' => 'ACTIVE'],
            );
        }
    }
}
