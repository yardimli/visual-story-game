<?php
namespace App\Services;

class PollingSnapshot
{
    public function response(\Illuminate\Http\Request $request, array $data)
    {
        $version=hash('sha256',json_encode($data));
        return response()->json($request->query('poll_version') === $version
            ? ['unchanged'=>true,'version'=>$version]
            : $data+['version'=>$version])->header('Cache-Control','private, no-store');
    }
}
