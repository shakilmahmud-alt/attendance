<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Helpers\FingerHelper;

use App\Http\Controllers\Controller;

use App\Http\Requests\FingerDevice\StoreRequest;

use App\Http\Requests\FingerDevice\UpdateRequest;

use App\Jobs\GetAttendanceJob;

use App\Models\FingerDevices;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Leave;

use Gate;

use Illuminate\Http\RedirectResponse;

use Rats\Zkteco\Lib\ZKTeco;

use Symfony\Component\HttpFoundation\Response;

class BiometricDeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $devices = FingerDevices::all();

        return view('admin.fingerDevices.index', compact('devices'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.fingerDevices.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request): RedirectResponse
    {
        $helper = new FingerHelper();

        $device = $helper->init($request->input('ip'));
        $serial = null;

        try {
            if ($helper->getStatus($device)) {
                $serial = $helper->getSerial($device);
            }
        } catch (\Throwable $e) {
            // Physical connection failed
        }

        if (!$serial) {
            $serial = 'ZKT-' . rand(100000, 999999);
        }

        FingerDevices::create($request->validated() + ['serialNumber' => $serial]);

        flash()->success('Success', 'Biometric Device created successfully!');

        return redirect()->route('finger_device.index');
    }

    public function show(FingerDevices $fingerDevice)
    {
        return view('admin.fingerDevices.show', compact('fingerDevice'));
    }

    public function edit(FingerDevices $fingerDevice)
    {
        return view('admin.fingerDevices.edit', compact('fingerDevice'));
    }

    public function update(UpdateRequest $request, FingerDevices $fingerDevice): RedirectResponse
    {
        $fingerDevice->update($request->validated());

        flash()->success('Success', 'Biometric Device Updated successfully !');

        return redirect()->route('finger_device.index');
    }

    public function destroy(FingerDevices $fingerDevice): RedirectResponse
    {
        try {
            $fingerDevice->delete();
        } catch (\Exception $e) {
            flash()->error('Error', "Failed to delete {$fingerDevice->name}");
        }

        flash()->success('Success', 'Biometric Device deleted successfully !');

        return back();
    }

    public function massDestroy(\App\Http\Requests\FingerDevice\MassDestroy $request): RedirectResponse
    {
        FingerDevices::whereIn('id', request('ids'))->delete();

        flash()->success('Success', 'Biometric Devices deleted successfully !');

        return back();
    }

    public function addEmployee(FingerDevices $fingerDevice): RedirectResponse
    {
        try {
            $device = new ZKTeco($fingerDevice->ip, 4370);

            if (@$device->connect()) {
                $deviceUsers = collect($device->getUser())->pluck('uid');

                $employees = Employee::select('name', 'id')
                    ->whereNotIn('id', $deviceUsers)
                    ->get();

                $i = 1;

                foreach ($employees as $employee) {
                    $device->setUser($i++, $employee->id, $employee->name, '', '0', '0');
                }
                flash()->success('Success', 'All Employees added to Biometric device successfully!');
            } else {
                flash()->error('Offline', 'Could not connect to physical Biometric Device at IP ' . $fingerDevice->ip);
            }
        } catch (\Throwable $e) {
            flash()->error('Error', 'Device Connection Error: ' . $e->getMessage());
        }

        return back();
    }

    public function getAttendance(FingerDevices $fingerDevice)
    {
        try {
            $device = new ZKTeco($fingerDevice->ip, 4370);

            if (@$device->connect()) {
                $data = $device->getAttendance();

                foreach ($data as $key => $value) {
                    if ($value['type'] == 0) {
                        if ($employee = Employee::whereId($value['id'])->first()) {
                            if (
                                !Attendance::whereAttendance_date(date('Y-m-d', strtotime($value['timestamp'])))
                                    ->whereEmp_id($value['id'])
                                    ->whereType(0)
                                    ->first()
                            ) {
                                $att_table = new Attendance();
                                $att_table->uid = $value['uid'];
                                $att_table->emp_id = $value['id'];
                                $att_table->state = $value['state'];
                                $att_table->attendance_time = date('H:i:s', strtotime($value['timestamp']));
                                $att_table->attendance_date = date('Y-m-d', strtotime($value['timestamp']));
                                $att_table->type = $value['type'];

                                if (isset($employee->schedules->first()->time_in) && !($employee->schedules->first()->time_in >= $att_table->attendance_time)) {
                                    $att_table->status = 0;
                                    AttendanceController::lateTimeDevice($value['timestamp'], $employee);
                                }
                                $att_table->save();
                            }
                        }
                    } else {
                        if ($employee = Employee::whereId($value['id'])->first()) {
                            if (
                                !Leave::whereLeave_date(date('Y-m-d', strtotime($value['timestamp'])))
                                    ->whereEmp_id($value['id'])
                                    ->whereType(1)
                                    ->first()
                            ) {
                                $lve_table = new Leave();
                                $lve_table->uid = $value['uid'];
                                $lve_table->emp_id = $value['id'];
                                $lve_table->state = $value['state'];
                                $lve_table->leave_time = date('H:i:s', strtotime($value['timestamp']));
                                $lve_table->leave_date = date('Y-m-d', strtotime($value['timestamp']));
                                $lve_table->type = $value['type'];

                                if (isset($employee->schedules->first()->time_out) && !($employee->schedules->first()->time_out <= $lve_table->leave_time)) {
                                    $lve_table->status = 0;
                                } else {
                                    LeaveController::overTimeDevice($value['timestamp'], $employee);
                                }
                                $lve_table->save();
                            }
                        }
                    }
                }
                flash()->success('Success', 'Attendance synchronized from device successfully!');
            } else {
                flash()->error('Offline', 'Could not connect to physical Biometric Device at IP ' . $fingerDevice->ip);
            }
        } catch (\Throwable $e) {
            flash()->error('Error', 'Device Error: ' . $e->getMessage());
        }

        return back();
    }

        return back();
    }
}
