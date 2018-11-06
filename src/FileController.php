<?php

namespace YiluTech\FileUpload;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class FileController extends BaseController
{
    public function move(Request $request)
    {
        $this->validate($request, [
            'paths' => 'required|array|min:1',
            'instance' => 'required',
            'bucket' => 'required|string|max:16',
        ]);
        $server = new Server($request->input('bucket'), $request->input('instance'));
        try {
            foreach ($request->input('paths') as $path) {
                $argv = is_array($path) ? [$path['from'], $path['to']] : [$path];
                if (!$server->move(...$argv))
                    throw new \Exception();
            }

            return 'success';
        } catch (\Exception $exception) {
            $server->rollBack();
            return 'fail';
        }
    }

    public function delete(Request $request)
    {
        $this->validate($request, [
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
            'instance' => 'required',
            'bucket' => 'required|string|max:16',
        ]);

        $server = new Server($request->input('bucket'), $request->input('instance'));

        return $server->delete($request->input('paths')) ? 'success' : 'fail';
    }

    public function recover(Request $request)
    {
        $this->validate($request, [
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
            'instance' => 'required',
            'bucket' => 'required|string|max:16',
        ]);

        $server = new Server($request->input('bucket'), $request->input('instance'));

        try {
            foreach ($request->input('paths') as $path)
                if (!$server->recovery($path))
                    throw new \Exception();
            return 'success';
        } catch (\Exception $exception) {
            $server->rollBack();
            return 'fail';
        }
    }
}
