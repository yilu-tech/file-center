<?php

namespace YiluTech\FileCenter;

use Illuminate\Http\Request;

class FileController
{
    public function move(Request $request)
    {
        app('validator')->make($request->input(), [
            'paths' => 'required|array|min:1',
            'bucket' => 'required|string|max:16',
            'prefix' => 'nullable|string|max:64'
        ])->validate();
        $server = new Server($request->input('bucket'), $request->input('prefix'));
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
        app('validator')->make($request->input(), [
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
            'bucket' => 'required|string|max:16',
        ])->validate();

        $server = new Server($request->input('bucket'), $request->input('prefix'));
        
        return $server->delete($request->input('paths')) ? 'success' : 'fail';
    }

    public function recover(Request $request)
    {
        app('validator')->make($request->input(), [
            'paths' => 'required|array|min:1',
            'paths.*' => 'string',
            'bucket' => 'required|string|max:16',
        ])->validate();

        $server = new Server($request->input('bucket'), $request->input('prefix'));

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
