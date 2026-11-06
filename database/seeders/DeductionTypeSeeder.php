<?php

namespace Database\Seeders;

use App\Models\DeductionType;
use Illuminate\Database\Seeder;

class DeductionTypeSeeder extends Seeder
{
    /**
     * Seed the deduction types drawn from the client payroll register.
     *
     * Statutory contributions and withholding tax are computed by the payroll engine;
     * the remaining entries are entered manually by the Payroll Officer.
     */
    public function run(): void
    {
        $deductions = [
            // Computed statutory deductions.
            ['name' => 'Withholding Tax', 'category' => 'TAX', 'type' => 'COMPUTED', 'statutory' => true, 'pre_tax' => false],
            ['name' => 'GSIS Contribution', 'category' => 'CONTRIBUTION', 'type' => 'COMPUTED', 'statutory' => true, 'pre_tax' => true],
            ['name' => 'PhilHealth Contribution', 'category' => 'CONTRIBUTION', 'type' => 'COMPUTED', 'statutory' => true, 'pre_tax' => true],
            ['name' => 'Pag-IBIG Contribution', 'category' => 'CONTRIBUTION', 'type' => 'COMPUTED', 'statutory' => true, 'pre_tax' => true],

            // GSIS loans and other deductions (manual).
            ['name' => 'EMER.', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'CONSO.', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'POLICY', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'GFAL', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'MPL', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'COMPUTER', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'MPL LITE', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'GSL', 'category' => 'GSIS_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],

            // Pag-IBIG related deductions (manual).
            ['name' => 'CALAMITY', 'category' => 'PAGIBIG_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'MPL', 'category' => 'PAGIBIG_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'HOUSING', 'category' => 'PAGIBIG_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'MP2', 'category' => 'PAGIBIG_LOAN', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],

            // GABE deductions (manual).
            ['name' => 'SALAMAT MABUHAY', 'category' => 'GABE', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'FINES', 'category' => 'GABE', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],

            // Other PLI / dues deductions (manual).
            ['name' => 'MONTHLY DUES', 'category' => 'PLI_DUES', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'CITY SAVINGS', 'category' => 'PLI_DUES', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'FCB', 'category' => 'PLI_DUES', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'Cebu CFI', 'category' => 'PLI_DUES', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
            ['name' => 'BDO', 'category' => 'PLI_DUES', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],

            // Separate LBP deduction applied after NTHP Before LBP.
            ['name' => 'LBP Loan Deduction', 'category' => 'LBP', 'type' => 'MANUAL', 'statutory' => false, 'pre_tax' => false],
        ];

        foreach ($deductions as $deduction) {
            DeductionType::updateOrCreate(
                ['deduction_name' => $deduction['name'], 'deduction_category' => $deduction['category']],
                [
                    'computation_type' => $deduction['type'],
                    'is_statutory' => $deduction['statutory'],
                    'is_pre_tax' => $deduction['pre_tax'],
                    'status' => 'ACTIVE',
                ],
            );
        }
    }
}
