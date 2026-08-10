<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\Latetime;
use App\Models\Overtime;
use App\Models\Leave;
use App\Models\Check;
use App\Models\FingerDevices;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // 1. Create Admin & User Roles
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator']
        );

        $empRole = Role::firstOrCreate(
            ['slug' => 'emp'],
            ['name' => 'Employee']
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@ams.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin@ams.com'),
            ]
        );
        $admin->roles()->sync([$adminRole->id]);

        // 2. Create Work Schedules
        $schedules = [
            'morning' => Schedule::firstOrCreate(
                ['slug' => 'morning-shift'],
                ['time_in' => '08:00:00', 'time_out' => '17:00:00']
            ),
            'day' => Schedule::firstOrCreate(
                ['slug' => 'day-shift'],
                ['time_in' => '09:00:00', 'time_out' => '18:00:00']
            ),
            'evening' => Schedule::firstOrCreate(
                ['slug' => 'evening-shift'],
                ['time_in' => '14:00:00', 'time_out' => '23:00:00']
            ),
        ];

        // 3. Create Employees
        $employeeData = [
            ['name' => 'Rahim Ahmed', 'position' => 'Senior Software Engineer', 'email' => 'rahim@ams.com', 'pin' => '1234', 'shift' => 'day'],
            ['name' => 'Karim Chowdhury', 'position' => 'Project Manager', 'email' => 'karim@ams.com', 'pin' => '1234', 'shift' => 'morning'],
            ['name' => 'Nusrat Jahan', 'position' => 'HR Executive', 'email' => 'nusrat@ams.com', 'pin' => '1234', 'shift' => 'day'],
            ['name' => 'Tariqul Islam', 'position' => 'DevOps Engineer', 'email' => 'tariqul@ams.com', 'pin' => '1234', 'shift' => 'day'],
            ['name' => 'Fatema Akter', 'position' => 'UI/UX Designer', 'email' => 'fatema@ams.com', 'pin' => '1234', 'shift' => 'day'],
            ['name' => 'Tanvir Hossain', 'position' => 'Backend Developer', 'email' => 'tanvir@ams.com', 'pin' => '1234', 'shift' => 'day'],
            ['name' => 'Sabrina Rahman', 'position' => 'QA Specialist', 'email' => 'sabrina@ams.com', 'pin' => '1234', 'shift' => 'morning'],
            ['name' => 'Mahmud Hasan', 'position' => 'Frontend Developer', 'email' => 'mahmud@ams.com', 'pin' => '1234', 'shift' => 'day'],
            ['name' => 'Farhana Islam', 'position' => 'Marketing Lead', 'email' => 'farhana@ams.com', 'pin' => '1234', 'shift' => 'day'],
            ['name' => 'Shakil Ahmed', 'position' => 'System Analyst', 'email' => 'shakil@ams.com', 'pin' => '1234', 'shift' => 'evening'],
        ];

        $employees = [];
        foreach ($employeeData as $emp) {
            $employee = Employee::firstOrCreate(
                ['email' => $emp['email']],
                [
                    'name' => $emp['name'],
                    'position' => $emp['position'],
                    'pin_code' => bcrypt($emp['pin']),
                ]
            );
            
            if ($employee->schedules()->count() == 0) {
                $employee->schedules()->attach($schedules[$emp['shift']]->id);
            }
            $employees[] = $employee;
        }

        // 4. Create Biometric Fingerprint Devices
        FingerDevices::firstOrCreate(
            ['serialNumber' => 'A8N5192261203'],
            ['name' => 'ZKTeco K40 (Main Entrance)', 'ip' => '192.168.1.201']
        );
        FingerDevices::firstOrCreate(
            ['serialNumber' => 'ZKT-8832002'],
            ['name' => 'Server Room Entrance', 'ip' => '192.168.1.202']
        );

        // 5. Seed Attendance, Latetime, Overtime, Leave & Check records for the past 14 days
        $startDate = Carbon::now()->subDays(14);
        $endDate = Carbon::now();

        for ($date = clone $startDate; $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $isWeekend = $date->isWeekend();

            foreach ($employees as $index => $employee) {
                $sched = $employee->schedules->first() ?: $schedules['day'];
                $schedTimeIn = Carbon::parse($dateString . ' ' . $sched->time_in);
                $schedTimeOut = Carbon::parse($dateString . ' ' . $sched->time_out);

                if ($isWeekend) {
                    // Skip weekends or assign light overtime occasionally
                    continue;
                }

                // Randomize attendance pattern: 70% On-time, 20% Late, 10% Leave
                $rand = rand(1, 100);

                if ($rand <= 10) {
                    // Leave record
                    Leave::create([
                        'uid' => $employee->id,
                        'emp_id' => $employee->id,
                        'state' => 0,
                        'leave_time' => $sched->time_out,
                        'leave_date' => $dateString,
                        'status' => 1,
                        'type' => 1,
                    ]);
                } else if ($rand <= 30) {
                    // Late arrival (15 - 45 mins late)
                    $lateMinutes = rand(15, 45);
                    $actualCheckIn = (clone $schedTimeIn)->addMinutes($lateMinutes);
                    $actualCheckOut = (clone $schedTimeOut)->addMinutes(rand(0, 30));

                    Attendance::create([
                        'uid' => $employee->id,
                        'emp_id' => $employee->id,
                        'state' => 0,
                        'attendance_time' => $actualCheckIn->format('H:i:s'),
                        'attendance_date' => $dateString,
                        'status' => 0, // Late status
                        'type' => 0,
                    ]);

                    Latetime::create([
                        'emp_id' => $employee->id,
                        'duration' => sprintf('%02d:%02d:00', floor($lateMinutes / 60), $lateMinutes % 60),
                        'latetime_date' => $dateString,
                    ]);

                    Check::create([
                        'emp_id' => $employee->id,
                        'attendance_time' => $actualCheckIn->format('Y-m-d H:i:s'),
                        'leave_time' => $actualCheckOut->format('Y-m-d H:i:s'),
                    ]);
                } else {
                    // On-time arrival (5 mins early to on time)
                    $earlyMinutes = rand(0, 10);
                    $actualCheckIn = (clone $schedTimeIn)->subMinutes($earlyMinutes);
                    
                    // Overtime check (30% chance of 1-2 hours overtime)
                    $overtimeMinutes = (rand(1, 100) <= 30) ? rand(60, 120) : 0;
                    $actualCheckOut = (clone $schedTimeOut)->addMinutes($overtimeMinutes);

                    Attendance::create([
                        'uid' => $employee->id,
                        'emp_id' => $employee->id,
                        'state' => 0,
                        'attendance_time' => $actualCheckIn->format('H:i:s'),
                        'attendance_date' => $dateString,
                        'status' => 1, // On time status
                        'type' => 0,
                    ]);

                    if ($overtimeMinutes > 0) {
                        Overtime::create([
                            'emp_id' => $employee->id,
                            'duration' => sprintf('%02d:%02d:00', floor($overtimeMinutes / 60), $overtimeMinutes % 60),
                            'overtime_date' => $dateString,
                        ]);
                    }

                    Check::create([
                        'emp_id' => $employee->id,
                        'attendance_time' => $actualCheckIn->format('Y-m-d H:i:s'),
                        'leave_time' => $actualCheckOut->format('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }
}

