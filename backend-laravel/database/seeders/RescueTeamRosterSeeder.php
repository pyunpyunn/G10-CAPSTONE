<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RescueTeamRosterSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';

    private array $teamDefaults = [
        'SAR' => [
            'team_name' => 'Search & Rescue',
            'team_type' => 'SAR',
            'skills' => 'search and rescue, rope rescue, water rescue, stretcher handling',
            'equipment' => 'PPE, handheld radio, rescue rope, stretcher, rescue vest',
        ],
        'MED' => [
            'team_name' => 'Medical / First Aid',
            'team_type' => 'Medical',
            'skills' => 'basic life support, first aid, triage, patient transport',
            'equipment' => 'PPE, first aid kit, trauma kit, BP apparatus, stretcher',
        ],
        'EVC' => [
            'team_name' => 'Evacuation',
            'team_type' => 'Evacuation',
            'skills' => 'evacuation assistance, crowd guidance, vulnerable sector support',
            'equipment' => 'PPE, handheld radio, megaphone, flashlight, evacuation checklist',
        ],
        'LOG' => [
            'team_name' => 'Relief & Transport',
            'team_type' => 'Relief / Transport',
            'skills' => 'relief distribution, vehicle coordination, inventory handling',
            'equipment' => 'PPE, handheld radio, delivery checklist, vehicle access, inventory sheets',
        ],
        'COM' => [
            'team_name' => 'Communication',
            'team_type' => 'Communication',
            'skills' => 'radio operation, message relay, incident logging',
            'equipment' => 'PPE, handheld radio, charging kit, incident logbook, power bank',
        ],
        'FIR' => [
            'team_name' => 'Fire Brigade',
            'team_type' => 'Fire Brigade',
            'skills' => 'fire suppression support, hose handling, scene safety',
            'equipment' => 'PPE, fire extinguisher, hose support kit, helmet, gloves',
        ],
        'DANA' => [
            'team_name' => 'DANA',
            'team_type' => 'Damage Assessment',
            'skills' => 'damage assessment, needs analysis, report writing',
            'equipment' => 'PPE, handheld radio, assessment forms, measuring tape, camera phone',
        ],
        'SEC' => [
            'team_name' => 'Security',
            'team_type' => 'Security',
            'skills' => 'site security, crowd control, perimeter safety',
            'equipment' => 'PPE, handheld radio, flashlight, whistle, checkpoint logbook',
        ],
    ];

    private array $roster = [
        'SAR' => [
            ['Miguel', 'A.', 'Reyes', 'M', 'O+', '09170001001', 'Rosa Reyes', '09180001001'],
            ['Vince', 'A.', 'Pacillan', 'M', 'A+', '09170001002', 'Marlon Pacillan', '09180001002'],
            ['Carlo', 'M.', 'Dizon', 'M', 'B+', '09170001003', 'Lorna Dizon', '09180001003'],
            ['Rafael', 'T.', 'Mendoza', 'M', 'AB+', '09170001004', 'Elena Mendoza', '09180001004'],
            ['Adrian', 'L.', 'Flores', 'M', 'O-', '09170001005', 'Nestor Flores', '09180001005'],
            ['Jerome', 'P.', 'Salazar', 'M', 'A-', '09170001006', 'Marites Salazar', '09180001006'],
        ],
        'MED' => [
            ['Vinzon', 'D.', 'Arellano', 'M', 'B+', '09170002001', 'Lilia Arellano', '09180002001'],
            ['Lea', 'M.', 'Santos', 'F', 'O+', '09170002002', 'Noel Santos', '09180002002'],
            ['Mark', 'J.', 'Bautista', 'M', 'A+', '09170002003', 'Cora Bautista', '09180002003'],
            ['Andrea', 'C.', 'Lim', 'F', 'AB+', '09170002004', 'Ramon Lim', '09180002004'],
            ['Noel', 'R.', 'Villanueva', 'M', 'O-', '09170002005', 'Teresita Villanueva', '09180002005'],
            ['Patricia', 'G.', 'Ramos', 'F', 'A-', '09170002006', 'Edgar Ramos', '09180002006'],
        ],
        'EVC' => [
            ['Hannah', 'P.', 'Garcia', 'F', 'O+', '09170003001', 'Mario Garcia', '09180003001'],
            ['Eric', 'N.', 'Lopez', 'M', 'B+', '09170003002', 'Cecilia Lopez', '09180003002'],
            ['Samuel', 'B.', 'Navarro', 'M', 'A+', '09170003003', 'Mila Navarro', '09180003003'],
            ['Kiara', 'F.', 'Torres', 'F', 'AB+', '09170003004', 'Arnold Torres', '09180003004'],
            ['Patrick', 'C.', 'Gonzales', 'M', 'O-', '09170003005', 'Susan Gonzales', '09180003005'],
            ['Liza', 'T.', 'Mercado', 'F', 'A-', '09170003006', 'Rodolfo Mercado', '09180003006'],
        ],
        'LOG' => [
            ['Dennis', 'M.', 'Uy', 'M', 'O+', '09170004001', 'Grace Uy', '09180004001'],
            ['Marco', 'V.', 'Tan', 'M', 'B+', '09170004002', 'Aida Tan', '09180004002'],
            ['Abigail', 'S.', 'Cruz', 'F', 'A+', '09170004003', 'Rene Cruz', '09180004003'],
            ['Harold', 'P.', 'Ong', 'M', 'AB+', '09170004004', 'Janet Ong', '09180004004'],
            ['Janine', 'R.', 'Rivera', 'F', 'O-', '09170004005', 'Victor Rivera', '09180004005'],
            ['Timothy', 'C.', 'Yu', 'M', 'A-', '09170004006', 'Carmen Yu', '09180004006'],
        ],
        'COM' => [
            ['Ryan', 'D.', 'Aquino', 'M', 'O+', '09170005001', 'Elvie Aquino', '09180005001'],
            ['Mae', 'L.', 'Castillo', 'F', 'B+', '09170005002', 'Dante Castillo', '09180005002'],
            ['Gabriel', 'T.', 'Serrano', 'M', 'A+', '09170005003', 'Perla Serrano', '09180005003'],
            ['Sofia', 'M.', 'Delos Reyes', 'F', 'AB+', '09170005004', 'Rudy Delos Reyes', '09180005004'],
            ['Nathan', 'C.', 'Villamor', 'M', 'O-', '09170005005', 'Lourdes Villamor', '09180005005'],
            ['Alyssa', 'P.', 'Domingo', 'F', 'A-', '09170005006', 'Ben Domingo', '09180005006'],
        ],
        'FIR' => [
            ['Francis', 'L.', 'Aguilar', 'M', 'O+', '09170006001', 'Leticia Aguilar', '09180006001'],
            ['Orlando', 'P.', 'Villacorta', 'M', 'B+', '09170006002', 'Myrna Villacorta', '09180006002'],
            ['Martin', 'J.', 'Enriquez', 'M', 'A+', '09170006003', 'Estrella Enriquez', '09180006003'],
            ['Katrina', 'S.', 'Fuentes', 'F', 'AB+', '09170006004', 'Arturo Fuentes', '09180006004'],
            ['Joel', 'B.', 'Magbanua', 'M', 'O-', '09170006005', 'Luz Magbanua', '09180006005'],
            ['Bianca', 'R.', 'Manalo', 'F', 'A-', '09170006006', 'Fernando Manalo', '09180006006'],
        ],
        'DANA' => [
            ['Grace', 'N.', 'Velasco', 'F', 'O+', '09170007001', 'Ernesto Velasco', '09180007001'],
            ['Ramon', 'C.', 'Padilla', 'M', 'B+', '09170007002', 'Norma Padilla', '09180007002'],
            ['Nicole', 'F.', 'Abad', 'F', 'A+', '09170007003', 'Hector Abad', '09180007003'],
            ['Anton', 'M.', 'Cabahug', 'M', 'AB+', '09170007004', 'Delia Cabahug', '09180007004'],
            ['Elaine', 'T.', 'Yap', 'F', 'O-', '09170007005', 'Tony Yap', '09180007005'],
            ['Julius', 'R.', 'Ledesma', 'M', 'A-', '09170007006', 'Remedios Ledesma', '09180007006'],
        ],
        'SEC' => [
            ['Victor', 'P.', 'Cruz', 'M', 'O+', '09170008001', 'Fe Cruz', '09180008001'],
            ['Marina', 'L.', 'Gomez', 'F', 'B+', '09170008002', 'Oscar Gomez', '09180008002'],
            ['Allan', 'D.', 'Villarin', 'M', 'A+', '09170008003', 'Evelyn Villarin', '09180008003'],
            ['Kristine', 'M.', 'Palma', 'F', 'AB+', '09170008004', 'Rey Palma', '09180008004'],
            ['Roberto', 'S.', 'Chan', 'M', 'O-', '09170008005', 'Linda Chan', '09180008005'],
            ['Camille', 'R.', 'Soriano', 'F', 'A-', '09170008006', 'Manny Soriano', '09180008006'],
        ],
    ];

    public function run(): void
    {
        $rescuerRoleId = DB::table('roles')->where('role_key', 'rescuer')->value('role_id');

        if (! $rescuerRoleId) {
            $this->command?->warn('Rescuer roster was not seeded because the rescuer role is missing.');

            return;
        }

        $mambalingPurokId = DB::table('addresses')
            ->where('barangay_name', 'Mambaling')
            ->orderBy('address_id')
            ->value('address_id');

        DB::transaction(function () use ($rescuerRoleId, $mambalingPurokId): void {
            foreach ($this->teamDefaults as $teamCode => $team) {
                $teamId = $this->ensureTeam($teamCode, $team, $mambalingPurokId);
                $leaderResponderId = null;

                foreach ($this->roster[$teamCode] as $index => $person) {
                    $responderId = $this->ensureResponder($teamCode, $teamId, $person, $index + 1, $rescuerRoleId);

                    if ($index === 0) {
                        $leaderResponderId = $responderId;
                    }
                }

                DB::table('rescue_teams')
                    ->where('team_id', $teamId)
                    ->update([
                        'leader_responder_id' => $leaderResponderId,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    private function ensureTeam(string $teamCode, array $team, ?int $mambalingPurokId): int
    {
        $existing = DB::table('rescue_teams')
            ->where('team_code', $teamCode)
            ->orWhere('team_name', $team['team_name'])
            ->first();

        $data = [
            'team_code' => $teamCode,
            'team_name' => $team['team_name'],
            'team_type' => $team['team_type'],
            'assigned_purok_id' => $mambalingPurokId,
            'duty_status' => 'standby',
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('rescue_teams')
                ->where('team_id', $existing->team_id)
                ->update($data);

            return (int) $existing->team_id;
        }

        $teamId = $this->nextId('rescue_teams', 'team_id');

        DB::table('rescue_teams')->insert(array_merge($data, [
            'team_id' => $teamId,
            'created_at' => now(),
        ]));

        return $teamId;
    }

    private function ensureResponder(string $teamCode, int $teamId, array $person, int $sequence, int $rescuerRoleId): int
    {
        [$firstName, $middleInitial, $lastName, $gender, $bloodType, $mobile, $iceName, $iceMobile] = $person;

        $accountId = sprintf('BDRRM-%s-%03d', $teamCode, $sequence);
        $fullName = trim($firstName.' '.$middleInitial.' '.$lastName);
        $displayUsername = $this->uniqueDisplayUsername($firstName, $lastName, $accountId);

        $existing = DB::table('responders')
            ->where('username', $accountId)
            ->orWhere('responder_code', $accountId)
            ->first();

        $userId = $existing?->user_id ?: 'USR-RESCUER-'.$accountId;
        $existingUser = DB::table('users')->where('user_id', $userId)->first();
        $userData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $fullName,
            'username' => $displayUsername,
            'email' => strtolower(str_replace(' ', '.', $firstName.'.'.$lastName)).'@resqperation.local',
            'role_id' => $rescuerRoleId,
            'contact_number' => $mobile,
            'is_active' => 1,
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        if ($existingUser) {
            DB::table('users')->where('user_id', $userId)->update($userData);
        } else {
            DB::table('users')->insert(array_merge($userData, [
                'user_id' => $userId,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'must_change_password' => 1,
                'temp_password' => self::DEFAULT_PASSWORD,
                'created_at' => now(),
            ]));
        }

        $responderData = [
            'user_id' => $userId,
            'responder_code' => $accountId,
            'created_by_admin_id' => 'USR-HQ-2024035500',
            'team_id' => $teamId,
            'username' => $accountId,
            'full_name' => $fullName,
            'title' => 'Responder',
            'contact_number' => $mobile,
            'emergency_contact_name' => $iceName,
            'emergency_contact_number' => $iceMobile,
            'date_of_birth' => $this->birthDateFor($sequence),
            'gender' => $gender,
            'blood_type' => $bloodType,
            'address' => $this->mambalingAddress($sequence),
            'skills' => $this->teamDefaults[$teamCode]['skills'],
            'training_notes' => 'ICS orientation, BDRRM response protocol, first aid briefing, radio procedure',
            'certification_reference' => sprintf('BDRRM-2026-%s-%03d', $teamCode, $sequence),
            'equipment_notes' => $this->teamDefaults[$teamCode]['equipment'],
            'is_validated' => 1,
            'is_deployed' => 0,
            'duty_status' => 'standby',
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('responders')
                ->where('responder_id', $existing->responder_id)
                ->update($responderData);

            return (int) $existing->responder_id;
        }

        $responderId = $this->nextId('responders', 'responder_id');

        DB::table('responders')->insert(array_merge($responderData, [
            'responder_id' => $responderId,
            'password_hash' => Hash::make(self::DEFAULT_PASSWORD),
            'created_at' => now(),
        ]));

        return $responderId;
    }

    private function uniqueDisplayUsername(string $firstName, string $lastName, string $accountId): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9.]+/i', '', $firstName.'.'.$lastName));
        $username = $base;
        $counter = 2;

        while ($this->displayUsernameExists($username, $accountId)) {
            $username = $base.$counter;
            $counter++;
        }

        return $username;
    }

    private function displayUsernameExists(string $username, string $accountId): bool
    {
        $userId = 'USR-RESCUER-'.$accountId;

        return DB::table('users')
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->where('user_id', '<>', $userId)
            ->exists();
    }

    private function nextId(string $table, string $column): int
    {
        $currentMax = (int) DB::table($table)->max($column);

        return max($currentMax + 1, 1);
    }

    private function birthDateFor(int $sequence): string
    {
        $year = 1988 + ($sequence % 12);
        $month = str_pad((string) (($sequence % 9) + 1), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string) (10 + $sequence), 2, '0', STR_PAD_LEFT);

        return "{$year}-{$month}-{$day}";
    }

    private function mambalingAddress(int $sequence): string
    {
        $areas = [
            'Sitio Alaska',
            'Sitio Mahayahay',
            'Sitio San Roque',
            'Sitio Lawis',
            'Sitio Puntod',
            'Sitio Centro',
        ];

        $area = $areas[($sequence - 1) % count($areas)];

        return "{$area}, Barangay Mambaling, Cebu City";
    }
}
