<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        return view('devices.index', [
            'devices' => Device::where('user_id', $request->user()->id)
                ->withCount('activityLogs')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $device = Device::create([
            'user_id'  => $request->user()->id,
            'name'     => $data['name'],
            'platform' => 'windows',
        ]);

        return redirect()->route('devices.index')
            ->with('new_token', $device->rotateToken())
            ->with('new_device', $device->name);
    }

    /** Перевыпуск токена: старый перестаёт работать. */
    public function token(Device $device)
    {
        Gate::authorize('update', $device);

        return redirect()->route('devices.index')
            ->with('new_token', $device->rotateToken())
            ->with('new_device', $device->name);
    }

    public function destroy(Device $device)
    {
        Gate::authorize('delete', $device);

        $device->delete(); // история активности устройства удаляется каскадом

        return back()->with('status', 'Устройство удалено вместе с собранной активностью.');
    }
}
