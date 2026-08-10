<?php

namespace App\Http\Requests\FingerDevice;

use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroy extends FormRequest
{
    public function rules()
    {
<?php

namespace App\Http\Requests\FingerDevice;

use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroy extends FormRequest
{
    public function rules()
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'exists:finger_devices,id',
        ];
    }

    public function authorize()
    {
        return auth()->check();
    }
}
